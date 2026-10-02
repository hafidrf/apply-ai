<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR6 — RequirementMatcher: petakan requirement wajib → bukti profil + kata kunci ATS.
 */
class RequirementMatcher
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @param array $profile profil terstruktur (hasil ProfileParser)
     * @param array $parsedJob hasil JobParser
     */
    public function match(array $profile, array $parsedJob, string $lang = 'id'): array
    {
        $user = "=== PROFIL KANDIDAT ===\n"
            . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== LOWONGAN ===\n"
            . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $data = $this->askJson(
            $this->client,
            PromptLibrary::requirementMatch($lang),
            $user,
            ['max_tokens' => 8192]
        );

        $data['matches'] = $data['matches'] ?? [];
        $data['kata_kunci_ats'] = $data['kata_kunci_ats'] ?? [];
        $data['kekuatan_utama'] = $data['kekuatan_utama'] ?? [];

        return $data;
    }
}
