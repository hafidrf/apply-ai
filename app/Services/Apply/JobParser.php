<?php

namespace App\Services\Apply;

use App\Services\Llm\LlmClient;
use App\Services\Apply\Prompts\PromptLibrary;

/**
 * FR3/FR5 — JobParser: teks lowongan / hasil OCR → info terstruktur.
 */
class JobParser
{
    use ParsesJsonResponse;

    public function __construct(private LlmClient $client)
    {
    }

    /**
     * @param  array<int, string>  $images  data URL gambar (data:image/png;base64,...)
     */
    public function parse(
        string $rawText,
        string $inputType = 'text',
        string $lang = 'id',
        array $images = [],
    ): array {
        $hasImages = $images !== [];

        $instruction = "JENIS INPUT: {$inputType}"
            . ($hasImages
                ? "\nPERHATIAN: ada " . count($images) . " GAMBAR lowongan yang dilampirkan. "
                    . 'Baca gambar-gambar itu secara langsung — fokus pada area lowongan (posisi, perusahaan, '
                    . 'requirement, cara melamar). ABAIKAN elemen antarmuka yang tidak relevan seperti tab browser, '
                    . 'bookmark, taskbar, jam, ikon desktop, atau nama file.'
                : '')
            . ($inputType === 'screenshot' && !$hasImages
                ? "\nPERHATIAN: teks ini hasil OCR screenshot, mungkin berantakan/buram. "
                    . 'Jangan menebak bagian yang tidak terbaca — biarkan field-nya null. Jangan bertanya balik.'
                : '');

        $textPart = $instruction . ($rawText !== '' ? "\n\n=== TEKS / ISI ===\n{$rawText}" : '');

        // Kalau ada gambar, kirim sebagai konten multimodal
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
            PromptLibrary::jobParse($lang),
            $user,
            ['max_tokens' => 8192]
        );

        $data['requirement_wajib'] = $data['requirement_wajib'] ?? [];
        $data['requirement_nice'] = $data['requirement_nice'] ?? [];
        $data['tanggung_jawab'] = $data['tanggung_jawab'] ?? [];

        // Lowongan juga tanpa crosscheck — buang kalau model tetap mengirimkannya
        unset($data['perlu_konfirmasi']);

        return $data;
    }
}
