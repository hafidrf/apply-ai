<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

/**
 * Adapter untuk semua endpoint OpenAI-compatible:
 * FelidaeAI, DeepSeek, OpenRouter, Groq, Ollama, custom base URL.
 */
class OpenAICompatibleClient implements LlmClient
{
    use RetriesRequests;

    public function __construct(
        private string $providerId,
        private string $apiKey,
        private string $baseUrl,
        private int $timeout = 120,
        private ?string $model = null,
        private bool $vision = false,
        private array $extraBody = [],
    ) {
    }

    public function supportsVision(): bool
    {
        return $this->vision;
    }

    public function chat(array $messages, array $options = []): string
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        // Parameter tambahan khas provider (mis. matikan thinking DeepSeek).
        // Ditaruh paling akhir supaya bisa menimpa nilai default di atas.
        if ($this->extraBody !== []) {
            $payload = array_replace($payload, $this->extraBody);
        }

        $resp = $this->post('/chat/completions', $payload, $options['timeout'] ?? $this->timeout);

        $message = $resp['choices'][0]['message'] ?? [];
        $content = $message['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            $reasoning = trim((string) ($message['reasoning_content'] ?? ''));
            $finish = $resp['choices'][0]['finish_reason'] ?? '?';

            // Model thinking kadang menghabiskan max_tokens untuk reasoning
            // sebelum menghasilkan jawaban final.
            if ($reasoning !== '' || $finish === 'length') {
                throw LlmException::badResponse(
                    $this->providerId,
                    'model mengembalikan reasoning tanpa jawaban final (finish_reason: ' . $finish . '). '
                    . 'Kemungkinan max_tokens terlalu kecil untuk model thinking ini.'
                );
            }

            throw LlmException::badResponse(
                $this->providerId,
                'choices[0].message.content kosong: ' . mb_substr(json_encode($resp) ?: '', 0, 300)
            );
        }

        return $content;
    }

    public function ping(): string
    {
        // max_tokens kecil bikin model thinking gagal (semua token terpakai
        // untuk reasoning) — pakai nilai yang cukup.
        return $this->chat(
            [['role' => 'user', 'content' => 'Reply with exactly: OK']],
            ['max_tokens' => 300, 'temperature' => 0]
        );
    }

    public function listModels(): array
    {
        $resp = $this->request('GET', '/models');

        return collect($resp['data'] ?? [])
            ->pluck('id')
            ->filter()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------

    private function post(string $path, array $payload, int $timeout): array
    {
        return $this->request('POST', $path, $payload, $timeout);
    }

    private function request(string $method, string $path, ?array $payload = null, ?int $timeout = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        return $this->withRetry(function () use ($method, $url, $payload, $timeout) {
            $http = Http::timeout($timeout ?? $this->timeout)
                ->withHeaders($this->headers());

            try {
                $resp = $payload !== null
                    ? $http->send($method, $url, ['json' => $payload])
                    : $http->send($method, $url);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw LlmException::connection($this->providerId, $e->getMessage());
            }

            if ($resp->status() === 401 || $resp->status() === 403) {
                throw LlmException::auth($this->providerId);
            }
            if ($resp->status() === 429) {
                throw LlmException::rateLimit($this->providerId);
            }
            if ($resp->failed()) {
                $body = mb_substr($resp->body(), 0, 500);
                throw LlmException::badResponse($this->providerId, "HTTP {$resp->status()}: {$body}");
            }

            return $resp->json() ?? [];
        });
    }

    private function headers(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        // Ollama lokal boleh tanpa key; jangan kirim header kosong.
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        // OpenRouter memakai header identifikasi opsional.
        if ($this->providerId === 'openrouter') {
            $headers['HTTP-Referer'] = config('app.url', 'https://apply.hafidrf.com');
            $headers['X-Title'] = 'Asisten Apply Kerja';
        }

        return $headers;
    }
}
