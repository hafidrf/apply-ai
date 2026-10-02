<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR4 — ChannelDetector: email|linkedin|whatsapp|portal.
 * Instruksi eksplisit di lowongan menang atas heuristik format.
 */
class ChannelDetector
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @return array{kanal: ?string, alasan: string, yakin: bool}
     */
    public function detect(array $parsedJob, string $rawText, string $lang = 'id'): array
    {
        $user = "=== TEKS LOWONGAN ===\n{$rawText}\n\n"
            . "=== HASIL PARSE ===\n" . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $data = $this->askJson(
            $this->client,
            PromptLibrary::channelDetect($lang),
            $user,
            ['max_tokens' => 1024]
        );

        return [
            'kanal' => $data['kanal'] ?? null,
            'alasan' => $data['alasan'] ?? '',
            'yakin' => (bool) ($data['yakin'] ?? false),
        ];
    }
}
