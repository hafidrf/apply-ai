<?php

namespace App\Services\Apply;

/**
 * Gabungkan berbagai bentuk input (teks + link + PDF + hasil OCR) menjadi satu teks.
 * Dipakai bersama oleh PROFIL dan LOWONGAN.
 *
 * Gambar/foto TIDAK di sini — gambar dikirim apa adanya ke model vision,
 * atau di-OCR di browser sebagai fallback.
 */
class InputTextBuilder
{
    public function __construct(
        private LinkTextExtractor $links = new LinkTextExtractor(),
        private PdfTextExtractor $pdfs = new PdfTextExtractor(),
    ) {
    }

    /** URL di dalam teks yang di-paste user. */
    public static function detectUrls(string $text): array
    {
        preg_match_all('#https?://[^\s<>"\'\])]+#i', $text, $m);

        return array_values(array_unique($m[0] ?? []));
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $pdfFiles
     * @param  bool  $autoLinks  baca URL yang ditemukan di dalam rawText
     * @return array{text: string, meta: array}
     *
     * @throws \RuntimeException kalau tidak ada satu pun isi yang bisa dipakai
     */
    public function build(string $rawText, array $pdfFiles = [], bool $autoLinks = true): array
    {
        $parts = [];
        $meta = ['sources' => [], 'notes' => []];

        // 1. Teks yang di-paste user
        $rawText = trim($rawText);
        if ($rawText !== '') {
            // Buang URL "telanjang" saja dari teks supaya tidak dikirim dobel,
            // tapi hanya kalau barisnya memang cuma URL.
            $parts[] = $rawText;
            $meta['sources'][] = ['type' => 'text', 'chars' => mb_strlen($rawText)];
        }

        // 2. PDF lampiran
        if ($pdfFiles !== []) {
            $result = $this->pdfs->extractMany($pdfFiles);

            foreach ($result['files'] as $f) {
                $meta['sources'][] = [
                    'type' => 'pdf',
                    'name' => $f['name'],
                    'chars' => $f['chars'],
                    'error' => $f['error'],
                ];
                if ($f['error']) {
                    $meta['notes'][] = "PDF \"{$f['name']}\" gagal dibaca: {$f['error']}";
                }
            }

            if (trim($result['text']) !== '') {
                $parts[] = $result['text'];
            }
        }

        // 3. Link
        $urls = [];
        if ($autoLinks && $rawText !== '') {
            $urls = self::detectUrls($rawText);
        }

        foreach ($urls as $url) {
            try {
                $r = $this->links->extract($url);
                $parts[] = "===== ISI LINK: {$url} =====\n{$r['text']}";
                $meta['sources'][] = [
                    'type' => 'link',
                    'url' => $r['url'],
                    'title' => $r['title'],
                    'chars' => $r['chars'],
                    'error' => null,
                ];
            } catch (\RuntimeException $e) {
                // Link gagal TIDAK menggagalkan seluruh proses — cukup dicatat,
                // karena teks yang di-paste mungkin sudah berisi info lowongan.
                $meta['sources'][] = [
                    'type' => 'link',
                    'url' => $url,
                    'chars' => 0,
                    'error' => $e->getMessage(),
                ];
                $meta['notes'][] = $e->getMessage();
            }
        }

        $text = trim(implode("\n\n", $parts));

        if ($text === '') {
            throw new \RuntimeException(
                $meta['notes'] !== []
                    ? implode(' ', $meta['notes'])
                    : 'Tidak ada isi yang bisa diproses. Paste teks lowongan, lampirkan gambar/PDF, atau masukkan link.'
            );
        }

        $meta['total_chars'] = mb_strlen($text);

        return ['text' => $text, 'meta' => $meta];
    }
}
