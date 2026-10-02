<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR7 — GapReframer: reframing persuasif untuk requirement gap/sebagian.
 * Selalu berbasis fakta di profil; null jika tidak bisa jujur.
 */
class GapReframer
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @param array $matches hasil RequirementMatcher
     * @param array $profile profil terstruktur
     */
    public function reframe(array $matches, array $profile, string $lang = 'id'): array
    {
        // Hanya proses yang gap/sebagian — yang match tidak perlu reframe
        $needs = collect($matches['matches'] ?? [])
            ->filter(fn ($m) => in_array($m['status'] ?? '', ['gap', 'sebagian']))
            ->values()
            ->all();

        if (empty($needs)) {
            return ['reframes' => []];
        }

        $user = "=== PROFIL KANDIDAT (satu-satunya sumber fakta) ===\n"
            . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== REQUIREMENT YANG PERLU DIREFRAME ===\n"
            . json_encode($needs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $data = $this->askJson(
            $this->client,
            PromptLibrary::gapReframe($lang),
            $user,
            ['max_tokens' => 8192]
        );

        $data['reframes'] = $data['reframes'] ?? [];

        return $data;
    }
}
