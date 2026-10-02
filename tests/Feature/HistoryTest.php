<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\MessageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat lamaran: setiap kali pesan dibuat/disalin, satu snapshot dicatat
 * dan bisa dilihat lagi nanti (dikelompokkan per bulan).
 */
class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $profile = $user->profiles()->create([
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'source' => 'text',
            'data' => ['nama' => 'Test'],
            'confirmed_at' => now(),
        ]);
        $job = Job::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'raw_input_type' => 'text',
            'raw_text' => 'Lowongan contoh.',
            'position' => 'Product Engineer',
            'company' => 'Auctus',
            'channel' => 'dm',
            'status' => 'composed',
        ]);
        $draft = $job->drafts()->create([
            'channel' => 'dm',
            'message_text' => 'Halo, saya tertarik dengan posisi ini.',
            'notes' => ['jumlah_kata' => 7],
        ]);

        return [$user, $job, $draft];
    }

    public function test_copy_records_history_entry(): void
    {
        [$user, $job] = $this->fixture();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/copied", ['kind' => 'utama'])
            ->assertStatus(201)
            ->assertJsonPath('event', 'copied')
            ->assertJsonPath('kind', 'utama')
            ->assertJsonPath('position', 'Product Engineer')
            ->assertJsonPath('company', 'Auctus')
            ->assertJsonPath('channel', 'dm');

        $this->assertDatabaseHas('message_events', [
            'job_id' => $job->id,
            'user_id' => $user->id,
            'event' => 'copied',
            'message_text' => 'Halo, saya tertarik dengan posisi ini.',
        ]);
    }

    public function test_copying_variant_records_variant_text(): void
    {
        [$user, $job, $draft] = $this->fixture();
        $draft->variant_text = 'Versi lebih santai.';
        $draft->save();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/copied", ['kind' => 'varian'])
            ->assertStatus(201)
            ->assertJsonPath('kind', 'varian')
            ->assertJsonPath('message_text', 'Versi lebih santai.');
    }

    public function test_copy_without_message_is_rejected(): void
    {
        $user = User::factory()->create();
        $job = Job::create([
            'user_id' => $user->id,
            'raw_input_type' => 'text',
            'raw_text' => 'Belum digenerate.',
            'status' => 'parsed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/copied", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Belum ada pesan untuk dicatat.');
    }

    public function test_user_cannot_log_copy_for_other_users_job(): void
    {
        [, $job] = $this->fixture();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/copied", [])
            ->assertStatus(403);
    }

    public function test_history_is_grouped_by_month_newest_first(): void
    {
        [$user, $job, $draft] = $this->fixture();

        $old = MessageEvent::record($job, $draft, MessageEvent::GENERATED);
        $old->created_at = '2026-08-15 09:00:00';
        $old->saveQuietly();

        $new = MessageEvent::record($job, $draft, MessageEvent::COPIED);
        $new->created_at = '2026-09-28 09:00:00';
        $new->saveQuietly();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/history')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('months.0.key', '2026-09')
            ->assertJsonPath('months.0.count', 1)
            ->assertJsonPath('months.0.events.0.event', 'copied')
            ->assertJsonPath('months.1.key', '2026-08')
            ->assertJsonPath('months.1.events.0.event', 'generated');
    }

    public function test_history_only_contains_own_events(): void
    {
        [$user, $job, $draft] = $this->fixture();
        MessageEvent::record($job, $draft, MessageEvent::GENERATED);

        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/history')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_history_entry_can_be_deleted(): void
    {
        [$user, $job, $draft] = $this->fixture();
        $event = MessageEvent::record($job, $draft, MessageEvent::COPIED);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/history/{$event->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('message_events', ['id' => $event->id]);
    }

    public function test_user_cannot_delete_other_users_history(): void
    {
        [$user, $job, $draft] = $this->fixture();
        $event = MessageEvent::record($job, $draft, MessageEvent::COPIED);

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/history/{$event->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('message_events', ['id' => $event->id]);
    }

    public function test_revision_requires_an_instruction(): void
    {
        [$user, $job] = $this->fixture();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/revise", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instruction']);
    }

    public function test_revision_instruction_is_saved_in_history(): void
    {
        [$user, $job, $draft] = $this->fixture();
        $instruction = 'Buat lebih ringkas, tetap sebut PostgreSQL.';

        MessageEvent::record($job, $draft, MessageEvent::REVISED, 'utama', $instruction);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/history')
            ->assertOk()
            ->assertJsonPath('months.0.events.0.event', 'revised')
            ->assertJsonPath('months.0.events.0.instruction', $instruction)
            ->assertJsonPath('months.0.events.0.message_text', 'Halo, saya tertarik dengan posisi ini.');
    }

    public function test_user_cannot_revise_another_users_job(): void
    {
        [, $job] = $this->fixture();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/revise", ['instruction' => 'Buat lebih natural'])
            ->assertStatus(403);
    }
}
