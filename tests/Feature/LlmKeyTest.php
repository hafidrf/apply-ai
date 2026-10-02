<?php

namespace Tests\Feature;

use App\Models\LlmKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BYOK — keamanan key & validasi provider.
 */
class LlmKeyTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_catalog_lists_providers(): void
    {
        $resp = $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/llm/catalog');

        $resp->assertOk();
        $ids = collect($resp->json('providers'))->pluck('id')->all();
        $this->assertContains('9router', $ids);
        $this->assertContains('felidaeai', $ids);
        $this->assertContains('gemini', $ids);
        $this->assertContains('deepseek', $ids);
        $this->assertContains('openrouter', $ids);
        $this->assertContains('custom', $ids);
    }

    public function test_can_store_key_and_it_is_encrypted_at_rest(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => 'felidaeai',
            'api_key' => 'sk-secret-abcdef123456',
            'default_model' => 'FelidaeAI-Omni-3.6',
            'is_default' => true,
        ])->assertStatus(201);

        // Nilai di DB tidak boleh plain text
        $raw = \DB::table('llm_keys')->value('api_key');
        $this->assertNotSame('sk-secret-abcdef123456', $raw);

        // Tapi model bisa membacanya kembali
        $this->assertSame('sk-secret-abcdef123456', LlmKey::first()->api_key);
    }

    public function test_key_is_masked_in_api_response(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => 'felidaeai',
            'api_key' => 'sk-secret-abcdef123456',
        ])->assertStatus(201)
            ->assertJsonPath('masked_key', 'sk-s...3456')
            ->assertJsonMissingPath('api_key');

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/llm-keys')->assertOk();
        $this->assertStringNotContainsString('abcdef', $list->getContent());
    }

    public function test_unknown_provider_rejected(): void
    {
        $this->actingAs($this->user(), 'sanctum')->postJson('/api/llm-keys', [
            'provider' => 'tidak-ada',
            'api_key' => 'x',
        ])->assertStatus(422);
    }

    public function test_empty_key_rejected_except_ollama(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => 'felidaeai',
            'api_key' => '',
        ])->assertStatus(422);

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => 'ollama',
        ])->assertStatus(201);
    }

    public function test_user_cannot_access_other_users_key(): void
    {
        $owner = $this->user();
        $other = $this->user();

        $key = $owner->llmKeys()->create([
            'provider' => 'felidaeai',
            'api_key' => 'sk-owner',
        ]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/llm-keys/{$key->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('llm_keys', ['id' => $key->id]);
    }
}
