<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR6+FR7 — Match & Reframe dalam satu panggilan LLM.
 *
 * Pipeline memanggil LLM beberapa kali (match+reframe, compose, varian).
 * Menggabungkan match & reframe menghemat 1 panggilan → risiko rate limit
 * di provider gratis turun signifikan.
 */
class MatchAndReframe
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @return array{matches: array, kata_kunci_ats: array, kekuatan_utama: array, reframes: array}
     */
    public function run(array $profile, array $parsedJob, string $lang = 'id'): array
    {
        $user = "=== PROFIL KANDIDAT (satu-satunya sumber fakta) ===\n"
            . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== LOWONGAN ===\n"
            . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $data = $this->askJson(
            $this->client,
            PromptLibrary::matchAndReframe($lang),
            $user,
            ['max_tokens' => 8192]
        );

        $matches = $data['matches'] ?? [];

        // Pisahkan reframe dari matches agar konsumen lama tetap kompatibel
        $reframes = collect($matches)
            ->filter(fn ($m) => !empty($m['reframe']['kalimat']))
            ->map(fn ($m) => [
                'requirement' => $m['requirement'] ?? '',
                'teknik' => $m['reframe']['teknik'] ?? null,
                'reframe' => $m['reframe']['kalimat'],
                'dasar_fakta' => $m['reframe']['dasar_fakta'] ?? null,
            ])
            ->values()
            ->all();

        // Bersihkan struktur matches untuk disimpan (buang reframe nested)
        $cleanMatches = collect($matches)->map(function ($m) {
            unset($m['reframe']);
            return $m;
        })->values()->all();

        return [
            'matches' => $cleanMatches,
            'kata_kunci_ats' => $data['kata_kunci_ats'] ?? [],
            'kekuatan_utama' => $data['kekuatan_utama'] ?? [],
            'reframes' => ['reframes' => $reframes],
        ];
    }
}
