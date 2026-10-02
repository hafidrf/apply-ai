<?php

namespace App\Services\Apply;

use Smalot\PdfParser\Parser as PdfParserLib;

/**
 * FR1 — Ekstrak teks dari PDF CV (pure PHP, aman untuk shared hosting).
 */
class PdfTextExtractor
{
    public function extract(string $pdfPath): string
    {
        $parser = new PdfParserLib();
        $pdf = $parser->parseFile($pdfPath);

        return $pdf->getText();
    }

    /**
     * Banyak PDF sekaligus → satu teks gabungan, diberi penanda per file
     * supaya model tahu mana CV, mana portfolio, mana sertifikat.
     *
     * @param  array<int, array{name: string, path: string}>  $files
     * @return array{text: string, files: array<int, array{name: string, chars: int, error: string|null}>, total_chars: int}
     */
    public function extractMany(array $files): array
    {
        $parts = [];
        $report = [];
        $total = 0;

        foreach ($files as $i => $file) {
            $name = $file['name'];
            $num = $i + 1;

            try {
                $text = trim($this->extract($file['path']));
            } catch (\Throwable $e) {
                $report[] = ['name' => $name, 'chars' => 0, 'error' => 'Gagal dibaca: ' . $e->getMessage()];
                continue;
            }

            if ($text === '') {
                $report[] = [
                    'name' => $name,
                    'chars' => 0,
                    'error' => 'Tidak ada teks yang bisa diekstrak (kemungkinan PDF hasil scan/gambar — perlu OCR).',
                ];
                continue;
            }

            $chars = mb_strlen($text);
            $total += $chars;

            $parts[] = "===== DOKUMEN {$num}: {$name} =====\n{$text}";
            $report[] = ['name' => $name, 'chars' => $chars, 'error' => null];
        }

        return [
            'text' => implode("\n\n", $parts),
            'files' => $report,
            'total_chars' => $total,
        ];
    }
}
