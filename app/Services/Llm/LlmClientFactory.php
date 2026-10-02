<?php

namespace App\Services\Llm;

use App\Models\LlmKey;

/**
 * Membuat LlmClient sesuai provider & key milik user (BYOK).
 */
class LlmClientFactory
{
    public static function make(LlmKey $key): LlmClient
    {
        $provider = config("llm.providers.{$key->provider}");

        if ($provider === null) {
            throw new \InvalidArgumentException("Provider tidak dikenal: {$key->provider}");
        }

        $clientType = $provider['client'];
        $baseUrl = $key->base_url ?: ($provider['base_url'] ?? null);
        $model = $key->default_model ?: ($provider['models'][0] ?? null);
        $vision = self::resolveVision($key, $model);

        if ($clientType === 'openai-compat') {
            if (empty($baseUrl)) {
                throw new \InvalidArgumentException("Base URL wajib diisi untuk provider {$key->provider}.");
            }

            return new OpenAICompatibleClient(
                $key->provider,
                $key->api_key ?? '',
                $baseUrl,
                config('llm.default_timeout', 120),
                $model,
                $vision,
                (array) ($provider['extra_body'] ?? []),
            );
        }

        if ($clientType === 'gemini') {
            return new GeminiClient(
                $key->provider,
                $key->api_key ?? '',
                $baseUrl,
                config('llm.default_timeout', 120),
                $model,
                $vision,
            );
        }

        throw new \InvalidArgumentException("Tipe klien tidak dikenal: {$clientType}");
    }

    /**
     * Kemampuan vision: override manual di key menang atas deteksi config.
     */
    public static function resolveVision(LlmKey $key, ?string $model): bool
    {
        if ($key->supports_vision !== null) {
            return (bool) $key->supports_vision;
        }

        $visionModels = config("llm.providers.{$key->provider}.vision_models", []);

        return $model !== null && in_array($model, $visionModels, true);
    }

    /**
     * Apakah provider ini punya setidaknya satu model vision (untuk UI).
     */
    public static function providerHasVision(string $providerId): bool
    {
        return count(config("llm.providers.{$providerId}.vision_models", [])) > 0;
    }

    /**
     * Daftar provider yang bisa dipilih user (untuk UI settings).
     */
    public static function providerCatalog(): array
    {
        return collect(config('llm.providers'))
            ->map(fn ($p, $id) => [
                'id' => $id,
                'label' => $p['label'],
                'client' => $p['client'],
                'base_url' => $p['base_url'],
                'models' => $p['models'],
                'vision_models' => $p['vision_models'] ?? [],
                'key_hint' => $p['key_hint'],
            ])
            ->values()
            ->all();
    }
}
