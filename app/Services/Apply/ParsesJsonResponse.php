<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;

/**
 * Helper: panggil LLM dan parse balasan JSON dengan pembersihan umum
 * (model kadang membalas dengan markdown fence meski dilarang).
 */
trait ParsesJsonResponse
{
    /**
     * @param  string|array  $user  teks, atau array part multimodal
     *                              ([['type'=>'text',...], ['type'=>'image_url',...]])
     */
    protected function askJson(LlmClient $client, string $system, string|array $user, array $options = []): array
    {
        $raw = $client->chat(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            ['temperature' => 0.2, 'max_tokens' => $options['max_tokens'] ?? 8192] + $options
        );

        $json = self::extractJson($raw);

        if (!is_array($json)) {
            throw new \RuntimeException('LLM tidak mengembalikan JSON valid: ' . mb_substr($raw, 0, 300));
        }

        return $json;
    }

    public static function extractJson(string $raw): ?array
    {
        $text = trim($raw);

        // Buang markdown fence ```json ... ```
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        // Coba langsung
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Cari objek JSON pertama { ... } berpasangan
        $start = strpos($text, '{');
        if ($start !== false) {
            $depth = 0;
            $inStr = false;
            $esc = false;
            for ($i = $start; $i < strlen($text); $i++) {
                $ch = $text[$i];
                if ($esc) { $esc = false; continue; }
                if ($ch === '\\') { $esc = true; continue; }
                if ($ch === '"') { $inStr = !$inStr; continue; }
                if ($inStr) continue;
                if ($ch === '{') $depth++;
                if ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $candidate = substr($text, $start, $i - $start + 1);
                        $decoded = json_decode($candidate, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                    }
                }
            }
        }

        return null;
    }
}
