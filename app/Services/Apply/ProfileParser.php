<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR1 — ProfileParser: teks bebas / hasil ekstraksi PDF / isi link / gambar → profil terstruktur.
 * Profil yang dihasilkan LANGSUNG SIAP PAKAI (tidak ada tahap crosscheck/tanya-jawab).
 */
class ProfileParser
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @param  array<int, string>  $images  data URL gambar (data:image/png;base64,...)
     * @return array profil terstruktur (skema PromptLibrary::profileParse)
     */
    public function parse(
        string $rawInput,
        string $source = 'text',
        string $lang = 'id',
        array $images = [],
    ): array {
        $hasImages = $images !== [];

        $instruction = "SUMBER: {$source}";
        if ($hasImages) {
            $instruction .= "\nPERHATIAN: ada " . count($images) . ' GAMBAR yang dilampirkan (bisa berisi CV, sertifikat, '
                . 'portfolio, atau tangkapan layar profil). Baca langsung dari gambar. ABAIKAN elemen antarmuka yang '
                . 'tidak relevan (tab browser, bookmark, taskbar, jam, ikon desktop, menu aplikasi, tombol like/share).';
        }

        $textPart = $instruction . ($rawInput !== '' ? "\n\n=== INPUT MENTAH ===\n{$rawInput}" : '');

        if ($hasImages) {
            $content = [['type' => 'text', 'text' => $textPart]];
            foreach ($images as $dataUrl) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]];
            }
            $user = $content;
        } else {
            $user = $textPart;
        }

        $data = $this->askJson(
            $this->client,
            PromptLibrary::profileParse($lang),
            $user,
            ['max_tokens' => 8192]
        );

        // Normalisasi field wajib agar akses FE aman
        $data['kontak'] = $data['kontak'] ?? [];
        $data['pendidikan'] = $data['pendidikan'] ?? [];
        $data['pengalaman'] = $data['pengalaman'] ?? [];
        $data['skills'] = $data['skills'] ?? ['teknis' => [], 'tools' => [], 'soft' => []];
        $data['proyek'] = $data['proyek'] ?? [];
        $data['sertifikasi'] = $data['sertifikasi'] ?? [];
        $data['bahasa'] = $data['bahasa'] ?? [];

        // Field "kekuatan profil" — dipakai untuk menampilkan & matching yang lebih tajam
        $data['keunggulan'] = $data['keunggulan'] ?? [];
        $data['kata_kunci_ats'] = $data['kata_kunci_ats'] ?? [];
        $data['preferensi'] = $data['preferensi'] ?? [];
        $data['headline'] = $data['headline'] ?? null;

        // Profil tidak lagi memakai crosscheck — buang kalau model tetap mengirimkannya
        unset($data['perlu_konfirmasi']);

        return $data;
    }
}
