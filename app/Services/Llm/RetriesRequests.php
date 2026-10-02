<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;

/**
 * Retry dengan exponential backoff untuk error transien:
 * 429 (rate limit), 5xx, dan koneksi terputus.
 *
 * Pipeline apply melakukan beberapa panggilan LLM berurutan, jadi satu
 * blip jaringan bisa menggagalkan seluruh proses — retry di sini penting.
 */
trait RetriesRequests
{
    private int $maxAttempts = 3;

    /** Delay antar percobaan (detik). */
    private array $backoff = [2, 5, 10];

    /**
     * @param callable():array $fn  fungsi yang mengembalikan array respons
     * @throws LlmException
     */
    private function withRetry(callable $fn): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $fn();
            } catch (LlmException $e) {
                $attempt++;
                if ($attempt >= $this->maxAttempts || !$this->isTransient($e)) {
                    throw $e;
                }
                sleep($this->backoff[min($attempt - 1, count($this->backoff) - 1)]);
            } catch (ConnectionException $e) {
                $attempt++;
                if ($attempt >= $this->maxAttempts) {
                    throw LlmException::connection($this->providerId, $e->getMessage());
                }
                sleep($this->backoff[min($attempt - 1, count($this->backoff) - 1)]);
            }
        }
    }

    private function isTransient(LlmException $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, 'Rate limit')
            || str_contains($m, 'Gagal terhubung')
            || str_contains($m, 'HTTP 5')
            || str_contains($m, 'Connection was reset')
            || str_contains($m, 'timed out');
    }
}
