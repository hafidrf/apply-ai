<?php

namespace Tests\Feature;

use App\Models\LlmKey;
use App\Models\User;
use App\Services\Llm\LlmClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kemampuan vision: dikirim sebagai gambar ke model, bukan di-OCR.
 */
class VisionCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_known_vision_model_is_detected_from_config(): void
    {
        $key = new LlmKey(['provider' => '9router', 'default_model' => '9routeragent']);

        $this->assertTrue(LlmClientFactory::resolveVision($key, '9routeragent'));
    }

    public function test_other_9router_models_are_detected(): void
    {
        foreach (['cmc/Qwen/Qwen3.8-Max', 'gemini/gemini-3.6-flash', 'oc/nemotron-3-ultra-free'] as $model) {
            $key = new LlmKey(['provider' => '9router', 'default_model' => $model]);
            $this->assertTrue(LlmClientFactory::resolveVision($key, $model), "Model: {$model}");
        }
    }

    public function test_text_only_model_is_not_vision(): void
    {
        $key = new LlmKey(['provider' => '9router', 'default_model' => 'ds/deepseek-v4.1-flash']);

        $this->assertFalse(LlmClientFactory::resolveVision($key, 'ds/deepseek-v4.1-flash'));
    }

    public function test_felidaeai_is_not_vision(): void
    {
        $key = new LlmKey(['provider' => 'felidaeai', 'default_model' => 'FelidaeAI-Omni-3.6']);

        $this->assertFalse(LlmClientFactory::resolveVision($key, 'FelidaeAI-Omni-3.6'));
    }

    /**
     * DeepSeek resmi: deepseek-flash menerima gambar (baca screenshot),
     * deepseek-v4-pro TIDAK. Nama lama deepseek-chat/reasoner sudah usang.
     */
    public function test_deepseek_flash_is_vision(): void
    {
        $key = new LlmKey(['provider' => 'deepseek', 'default_model' => 'deepseek-flash']);

        $this->assertTrue(LlmClientFactory::resolveVision($key, 'deepseek-flash'));
        $this->assertTrue(LlmClientFactory::resolveVision($key, 'deepseek-v4-flash-vision-exp'));
    }

    public function test_deepseek_v4_pro_is_not_vision(): void
    {
        $key = new LlmKey(['provider' => 'deepseek', 'default_model' => 'deepseek-v4-pro']);

        $this->assertFalse(LlmClientFactory::resolveVision($key, 'deepseek-v4-pro'));
    }

    public function test_deepseek_provider_uses_current_model_names(): void
    {
        $models = config('llm.providers.deepseek.models');

        // Nama lama sudah tidak didukung DeepSeek
        $this->assertNotContains('deepseek-chat', $models);
        $this->assertNotContains('deepseek-reasoner', $models);

        $this->assertContains('deepseek-flash', $models);
        $this->assertContains('deepseek-v4-pro', $models);
    }

    /** DeepSeek-flash adalah model thinking → thinking harus dimatikan. */
    public function test_deepseek_disables_thinking(): void
    {
        $extra = config('llm.providers.deepseek.extra_body');

        $this->assertSame(['type' => 'disabled'], $extra['thinking']);
    }

    public function test_9router_deepseek_routes_are_vision(): void
    {
        foreach ([
            'ds/deepseek-v4-pro',
            'ds/deepseek-v4-flash',
            'cmc/deepseek/deepseek-v4-pro',
            'cmc/deepseek/deepseek-v4-flash',
            'nvidia/deepseek-ai/deepseek-v4-pro',
        ] as $model) {
            $key = new LlmKey(['provider' => '9router', 'default_model' => $model]);
            $this->assertTrue(LlmClientFactory::resolveVision($key, $model), "Model: {$model}");
        }
    }

    public function test_manual_override_beats_config_detection(): void
    {
        // Model yang tidak dikenal, tapi user menandai bisa gambar
        $key = new LlmKey([
            'provider' => '9router',
            'default_model' => 'model-baru-tak-dikenal',
            'supports_vision' => true,
        ]);

        $this->assertTrue(LlmClientFactory::resolveVision($key, 'model-baru-tak-dikenal'));

        // Dan sebaliknya
        $key2 = new LlmKey([
            'provider' => '9router',
            'default_model' => '9routeragent',
            'supports_vision' => false,
        ]);

        $this->assertFalse(LlmClientFactory::resolveVision($key2, '9routeragent'));
    }

    public function test_key_api_reports_vision_capability(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => '9router',
            'api_key' => 'sk-test',
            'default_model' => '9routeragent',
        ])->assertStatus(201)->assertJsonPath('can_read_images', true);

        $this->actingAs($user, 'sanctum')->postJson('/api/llm-keys', [
            'provider' => '9router',
            'api_key' => 'sk-test',
            'default_model' => 'ds/deepseek-v4.1-flash',
        ])->assertStatus(201)->assertJsonPath('can_read_images', false);
    }

    public function test_catalog_exposes_vision_models(): void
    {
        $resp = $this->actingAs($this->user(), 'sanctum')->getJson('/api/llm/catalog')->assertOk();

        $router = collect($resp->json('providers'))->firstWhere('id', '9router');
        $this->assertContains('9routeragent', $router['vision_models']);

        $felidae = collect($resp->json('providers'))->firstWhere('id', 'felidaeai');
        $this->assertSame([], $felidae['vision_models']);
    }

    public function test_sending_image_to_text_only_model_is_rejected_with_help(): void
    {
        $user = $this->user();
        $key = $user->llmKeys()->create([
            'provider' => '9router',
            'api_key' => 'sk-test',
            'default_model' => 'ds/deepseek-v4.1-flash',
            'is_default' => true,
        ]);

        // create() tidak butuh ekstensi GD (image() butuh)
        $file = \Illuminate\Http\UploadedFile::fake()->create('lowongan.png', 12, 'image/png');

        $this->actingAs($user, 'sanctum')
            ->post('/api/jobs', [
                'raw_text' => '',
                'llm_key_id' => $key->id,
                'images' => [$file],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'vision_not_supported');
    }
}
