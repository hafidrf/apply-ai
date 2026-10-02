<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR8/FR9 — MessageComposer: susun pesan per kanal + packaging output.
 */
class MessageComposer
{
    use ParsesJsonResponse;

    /** Batas kata per kanal (PRD §9 + kanal tambahan "dm") — untuk validasi hasil. */
    public const WORD_LIMITS = [
        'email' => [120, 180],
        'linkedin' => [50, 90],
        'whatsapp' => [40, 70],
        'portal' => [100, 150],
        'dm' => [45, 80],
    ];

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @param array $profile profil terstruktur
     * @param array $parsedJob hasil JobParser
     * @param array $matching hasil RequirementMatcher
     * @param array $reframes hasil GapReframer
     * @param string $channel email|linkedin|whatsapp|portal
     * @param string $outputLang bahasa pesan (id|en) — default ikut preferensi/lowongan
     */
    public function compose(
        array $profile,
        array $parsedJob,
        array $matching,
        array $reframes,
        string $channel,
        string $outputLang = 'id',
    ): array {
        $user = "=== PROFIL KANDIDAT ===\n"
            . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== LOWONGAN ===\n"
            . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== HASIL MATCHING ===\n"
            . json_encode($matching, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== REFRAMING (gunakan secara halus untuk requirement gap) ===\n"
            . json_encode($reframes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nBAHASA PESAN: " . ($outputLang === 'en' ? 'English' : 'Bahasa Indonesia')
            . "\nKANAL TUJUAN: {$channel}";

        $data = $this->askJson(
            $this->client,
            PromptLibrary::compose($channel, $outputLang),
            $user,
            ['max_tokens' => 8192]
        );

        $message = $data['pesan'] ?? '';
        if ($message === '') {
            throw new \RuntimeException('Composer mengembalikan pesan kosong.');
        }

        return [
            'pesan' => $message,
            'jumlah_kata' => (int) ($data['jumlah_kata'] ?? str_word_count(strip_tags($message))),
        ];
    }

    /**
     * FR9 opsional: 1 varian nada alternatif.
     */
    public function composeVariant(
        array $profile,
        array $parsedJob,
        array $matching,
        array $reframes,
        string $channel,
        string $outputLang = 'id',
    ): ?string {
        try {
            $user = "=== PROFIL KANDIDAT ===\n"
                . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\n=== LOWONGAN ===\n"
                . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\n=== HASIL MATCHING ===\n"
                . json_encode($matching, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\n=== REFRAMING ===\n"
                . json_encode($reframes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . "\n\nBAHASA PESAN: " . ($outputLang === 'en' ? 'English' : 'Bahasa Indonesia')
                . "\nKANAL TUJUAN: {$channel}";

            $data = $this->askJson(
                $this->client,
                PromptLibrary::composeVariant($channel, $outputLang),
                $user,
                ['max_tokens' => 8192]
            );

            return $data['pesan'] ?? null;
        } catch (\Throwable $e) {
            // Varian bersifat opsional — jangan gagalkan alur utama
            return null;
        }
    }

    /** Revisi draft existing sesuai arahan bebas user. */
    public function revise(
        string $currentMessage,
        string $instruction,
        array $profile,
        array $parsedJob,
        string $channel,
        string $outputLang = 'id',
    ): array {
        $user = "=== INSTRUKSI REVISI USER ===\n{$instruction}"
            . "\n\n=== PESAN SAAT INI ===\n{$currentMessage}"
            . "\n\n=== PROFIL KANDIDAT (SUMBER FAKTA) ===\n"
            . json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\n=== LOWONGAN (SUMBER FAKTA) ===\n"
            . json_encode($parsedJob, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nBAHASA PESAN: " . ($outputLang === 'en' ? 'English' : 'Bahasa Indonesia')
            . "\nKANAL TUJUAN: {$channel}";

        $data = $this->askJson(
            $this->client,
            PromptLibrary::revise($channel, $outputLang),
            $user,
            ['max_tokens' => 8192]
        );

        $message = trim((string) ($data['pesan'] ?? ''));
        if ($message === '') {
            throw new \RuntimeException('AI mengembalikan pesan revisi kosong. Coba instruksi lain.');
        }

        return [
            'pesan' => $message,
            'jumlah_kata' => (int) ($data['jumlah_kata'] ?? str_word_count(strip_tags($message))),
        ];
    }

    /**
     * Validasi jumlah kata pesan inti terhadap batas PRD §9.
     * (Subject line email tidak dihitung.)
     */
    public static function validateWordCount(string $channel, string $message): bool
    {
        [$min, $max] = self::WORD_LIMITS[$channel] ?? [0, PHP_INT_MAX];

        // Untuk email: buang subject line (baris pertama jika berformat "Subject: ...")
        $body = preg_replace('/^subject:.*$/im', '', $message);

        $count = str_word_count(trim($body));

        return $count >= $min && $count <= $max;
    }
}
