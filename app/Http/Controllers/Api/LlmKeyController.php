<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LlmKey;
use App\Services\Llm\LlmClientFactory;
use App\Services\Llm\LlmException;
use Illuminate\Http\Request;

class LlmKeyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->llmKeys()->get()->map(fn (LlmKey $k) => $this->present($k))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:200'],
            'supports_vision' => ['nullable', 'boolean'],
            'is_default' => ['boolean'],
        ]);

        if (!config("llm.providers.{$data['provider']}")) {
            return response()->json(['message' => 'Provider tidak dikenal.'], 422);
        }
        if ($data['provider'] !== 'ollama' && empty($data['api_key'])) {
            return response()->json(['message' => 'API key wajib diisi untuk provider ini.'], 422);
        }

        $key = $request->user()->llmKeys()->create($data);

        if (!empty($data['is_default'])) {
            $request->user()->llmKeys()->whereKeyNot($key->id)->update(['is_default' => false]);
        }

        return response()->json($this->present($key->fresh()), 201);
    }

    public function update(Request $request, LlmKey $llmKey)
    {
        if ($llmKey->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:200'],
            'supports_vision' => ['nullable', 'boolean'],
            'is_default' => ['boolean'],
        ]);

        // Key kosong = jangan overwrite key lama
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $llmKey->update($data);

        if (!empty($data['is_default'])) {
            $request->user()->llmKeys()->whereKeyNot($llmKey->id)->update(['is_default' => false]);
        }

        return response()->json($this->present($llmKey->fresh()));
    }

    public function destroy(Request $request, LlmKey $llmKey)
    {
        if ($llmKey->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $llmKey->delete();

        return response()->json(['ok' => true]);
    }

    /** Tes koneksi: ping chat kecil ke provider. */
    public function test(Request $request, LlmKey $llmKey)
    {
        if ($llmKey->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $model = $request->input('model') ?: $llmKey->default_model;

        try {
            $client = LlmClientFactory::make($llmKey);
            $reply = $client->chat(
                [['role' => 'user', 'content' => 'Reply with exactly: OK']],
                ['max_tokens' => 10, 'temperature' => 0] + ($model ? ['model' => $model] : [])
            );

            return response()->json(['ok' => true, 'reply' => $reply]);
        } catch (LlmException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    /** Daftar model dari endpoint (jika didukung). */
    public function models(LlmKey $llmKey)
    {
        if ($llmKey->user_id !== auth()->id()) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        try {
            return response()->json(['models' => LlmClientFactory::make($llmKey)->listModels()]);
        } catch (LlmException $e) {
            return response()->json(['models' => [], 'error' => $e->getMessage()], 200);
        }
    }

    public function catalog()
    {
        return response()->json(['providers' => LlmClientFactory::providerCatalog()]);
    }

    private function present(LlmKey $k): array
    {
        return [
            'id' => $k->id,
            'provider' => $k->provider,
            'provider_label' => config("llm.providers.{$k->provider}.label") ?? $k->provider,
            'label' => $k->label,
            'masked_key' => $k->maskedKey(),
            'base_url' => $k->base_url,
            'default_model' => $k->default_model,
            'supports_vision' => $k->supports_vision,
            'can_read_images' => LlmClientFactory::resolveVision($k, $k->default_model),
            'vision_models' => config("llm.providers.{$k->provider}.vision_models", []),
            'is_default' => $k->is_default,
            'created_at' => $k->created_at?->toIso8601String(),
        ];
    }
}
