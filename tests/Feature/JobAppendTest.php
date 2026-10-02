<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\LlmKey;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lowongan bentuk thread / info terpisah: user bisa menambah bagian baru
 * dan lowongan diparse ulang dengan menggabungkan semuanya.
 */
class JobAppendTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $key = $user->llmKeys()->create([
            'provider' => '9router',
            'api_key' => 'sk-test',
            'default_model' => '9routeragent',
            'is_default' => true,
        ]);
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
            'raw_text' => 'Bagian pertama lowongan.',
            'status' => 'parsed',
        ]);

        return [$user, $key, $job];
    }

    public function test_append_rejects_empty_input(): void
    {
        [$user, , $job] = $this->fixture();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/append", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tidak ada bagian baru yang dikirim.');
    }

    public function test_user_cannot_append_to_other_users_job(): void
    {
        [, , $job] = $this->fixture();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/jobs/{$job->id}/append", ['raw_text' => 'Hack'])
            ->assertStatus(403);
    }

    public function test_images_require_a_vision_model(): void
    {
        [$user, $key, $job] = $this->fixture();
        $key->update(['default_model' => 'ds/deepseek-v4.1-flash']); // teks saja

        $file = \Illuminate\Http\UploadedFile::fake()->create('lanjutan.png', 10, 'image/png');

        $this->actingAs($user, 'sanctum')
            ->post("/api/jobs/{$job->id}/append", [
                'images' => [$file],
                'llm_key_id' => $key->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'vision_not_supported');
    }

    public function test_image_upload_is_validated(): void
    {
        [$user, , $job] = $this->fixture();

        $file = \Illuminate\Http\UploadedFile::fake()->create('dokumen.docx', 10);

        $this->actingAs($user, 'sanctum')
            ->post("/api/jobs/{$job->id}/append", ['images' => [$file]])
            ->assertStatus(422);
    }
}
