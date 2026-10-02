<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

/**
 * Adapter Google Gemini (generativelanguage API, format native).
 */
class GeminiClient implements LlmClient
{
    use RetriesRequests;

    public function __construct(
        private string $providerId,
        private string $apiKey,
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private int $timeout = 120,
        private ?string $model = null,
        private bool $vision = false,
    ) {
    }

    public function supportsVision(): bool
    {
        return $this->vision;
    }

    public function chat(array $messages, array $options = []): string
    {
        $system = collect($messages)
            ->filter(fn ($m) => ($m['role'] ?? '') === 'system')
            ->map(fn ($m) => $m['content'])
            ->implode("\n\n");

        $contents = collect($messages)
            ->filter(fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant']))
            ->map(fn ($m) => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => $this->toParts($m['content'] ?? ''),
            ])
            ->values()
            ->all();

        $model = $options['model'] ?? $this->model ?? 'gemini-2.0-flash';
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ],
        ];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if (isset($options['temperature'])) {
            $payload['generationConfig']['temperature'] = $options['temperature'];
        }

        $url = rtrim($this->baseUrl, '/')
            . "/models/{$model}:generateContent?key=" . urlencode($this->apiKey);

        try {
            $resp = Http::timeout($options['timeout'] ?? $this->timeout)->post($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw LlmException::connection($this->providerId, $e->getMessage());
        }

        if (in_array($resp->status(), [401, 403])) {
            throw LlmException::auth($this->providerId);
        }
        if ($resp->status() === 429) {
            throw LlmException::rateLimit($this->providerId);
        }
        if ($resp->failed()) {
            throw LlmException::badResponse($this->providerId, "HTTP {$resp->status()}: " . mb_substr($resp->body(), 0, 500));
        }

        $content = $resp->json('candidates.0.content.parts.0.text')
            ?? $resp->json('candidates.0.content.parts')[0]['text'] ?? null;

        if (!is_string($content) || $content === '') {
            throw LlmException::badResponse($this->providerId, 'balasan kosong: ' . mb_substr($resp->body(), 0, 300));
        }

        return $content;
    }

    public function ping(): string
    {
        return $this->chat(
            [['role' => 'user', 'content' => 'Reply with exactly: OK']],
            ['max_tokens' => 10, 'temperature' => 0]
        );
    }

    /**
     * Ubah content OpenAI-style (string atau array part) → format Gemini.
     */
    private function toParts(mixed $content): array
    {
        if (is_string($content)) {
            return [['text' => $content]];
        }

        $parts = [];
        foreach ((array) $content as $part) {
            $type = $part['type'] ?? null;

            if ($type === 'text') {
                $parts[] = ['text' => $part['text'] ?? ''];
                continue;
            }

            if ($type === 'image_url') {
                $dataUrl = $part['image_url']['url'] ?? '';
                if (preg_match('#^data:([^;]+);base64,(.*)$#s', $dataUrl, $m)) {
                    $parts[] = ['inline_data' => ['mime_type' => $m[1], 'data' => $m[2]]];
                }
            }
        }

        return $parts !== [] ? $parts : [['text' => '']];
    }

    public function listModels(): array
    {        $url = rtrim($this->baseUrl, '/') . '/models?key=' . urlencode($this->apiKey);

        try {
            $resp = Http::timeout($this->timeout)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw LlmException::connection($this->providerId, $e->getMessage());
        }

        if ($resp->failed()) {
            throw LlmException::badResponse($this->providerId, "HTTP {$resp->status()}");
        }

        return collect($resp->json('models') ?? [])
            ->pluck('name')
            ->map(fn ($n) => str_replace('models/', '', (string) $n))
            ->values()
            ->all();
    }
}
