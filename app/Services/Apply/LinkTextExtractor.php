<?php

namespace App\Services\Apply;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Ambil teks dari halaman web (profil LinkedIn publik, portfolio, halaman "about").
 *
 * Guardrail PRD §10: JANGAN berasumsi isi link yang gagal diakses
 * (login-wall, 403, timeout, halaman kosong) — lempar error yang jelas
 * supaya user paste manual.
 */
class LinkTextExtractor
{
    private const MAX_BYTES = 3_000_000;   // batas ukuran halaman (3 MB)
    private const MIN_CHARS = 150;         // di bawah ini dianggap tidak berguna

    /**
     * Situs yang hampir pasti menolak pembacaan otomatis (butuh login /
     * memblokir bot). Diberi pesan khusus supaya user tidak bingung.
     */
    private const AUTH_WALL_HOSTS = [
        'linkedin.com' => 'LinkedIn',
        'facebook.com' => 'Facebook',
        'instagram.com' => 'Instagram',
        'x.com' => 'X (Twitter)',
        'twitter.com' => 'X (Twitter)',
        'jobstreet.co.id' => 'JobStreet',
        'glints.com' => 'Glints',
        'kalibrr.com' => 'Kalibrr',
    ];

    /**
     * @return array{text: string, title: string|null, url: string, chars: int}
     * @throws \RuntimeException kalau halaman tidak bisa dibaca
     */
    public function extract(string $url): array
    {
        $url = trim($url);

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('URL tidak valid: ' . $url);
        }

        if ($blocked = $this->authWallSite($url)) {
            throw new \RuntimeException(
                "Halaman {$blocked} memerlukan login, jadi isinya tidak bisa dibaca otomatis. " .
                'Silakan buka link-nya, copy isi profil Anda, lalu tempel di tab Teks.'
            );
        }

        try {
            $resp = Http::timeout(25)
                ->withHeaders([
                    // Beberapa situs menolak UA default.
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'id,en;q=0.9',
                ])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException(
                'Tidak bisa membuka link tersebut (koneksi gagal / timeout). ' .
                'Coba periksa URL-nya, atau tempel isinya manual lewat tab Teks.'
            );
        }

        $status = $resp->status();

        if (in_array($status, [401, 403])) {
            throw new \RuntimeException(
                "Link ini butuh login (HTTP {$status}) — halaman tidak bisa dibaca otomatis. " .
                'Silakan buka link-nya, copy isinya, lalu tempel di tab Teks.'
            );
        }
        if ($status === 404) {
            throw new \RuntimeException('Halaman tidak ditemukan (404). Periksa kembali URL-nya.');
        }
        if ($resp->failed()) {
            throw new \RuntimeException("Gagal membuka link (HTTP {$status}). Tempel isinya manual lewat tab Teks.");
        }

        $html = $resp->body();
        if (strlen($html) > self::MAX_BYTES) {
            $html = substr($html, 0, self::MAX_BYTES);
        }

        $title = null;
        $text = $this->htmlToText($html, $title);

        if (mb_strlen($text) < self::MIN_CHARS) {
            throw new \RuntimeException(
                'Isi halaman ini hampir kosong setelah dibaca (kemungkinan konten dimuat lewat JavaScript ' .
                'atau butuh login). Silakan copy isinya dan tempel di tab Teks.'
            );
        }

        return [
            'text' => $text,
            'title' => $title,
            'url' => $url,
            'chars' => mb_strlen($text),
        ];
    }

    /**
     * Bersihkan HTML → teks yang enak dibaca LLM.
     */
    private function htmlToText(string $html, ?string &$title): string
    {
        // Deteksi login-wall sebelum konten dipotong
        if ($this->looksLikeLoginWall($html)) {
            throw new \RuntimeException(
                'Halaman ini meminta login (login-wall), jadi isinya tidak bisa dibaca otomatis. ' .
                'Silakan copy isi profil Anda, lalu tempel di tab Teks.'
            );
        }

        $crawler = new Crawler($html);

        $title = $crawler->filter('title')->count() ? trim($crawler->filter('title')->text()) : null;

        // Buang elemen yang tidak berisi konten
        foreach (['script', 'style', 'noscript', 'svg', 'iframe', 'nav', 'footer', 'form', 'head'] as $tag) {
            $crawler->filter($tag)->each(function (Crawler $node) {
                $node->getNode(0)->parentNode?->removeChild($node->getNode(0));
            });
        }

        $body = $crawler->filter('body')->count() ? $crawler->filter('body')->html() : $crawler->html();

        // Blok → baris baru
        $body = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)[^>]*>#i', "\n", (string) $body);
        $body = preg_replace('#<li[^>]*>#i', "\n- ", $body);

        $text = strip_tags((string) $body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Rapikan spasi
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/^\s+|\s+$/m', '', $text);

        return trim($text);
    }

    private function looksLikeLoginWall(string $html): bool
    {
        $h = mb_strtolower($html);
        $markers = [
            'authwall',
            'sign in to continue',
            'log in to continue',
            'join now to see',
            'masuk untuk melanjutkan',
            'please log in',
        ];

        foreach ($markers as $m) {
            if (str_contains($h, $m)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nama situs kalau host-nya termasuk yang pasti butuh login.
     */
    private function authWallSite(string $url): ?string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        foreach (self::AUTH_WALL_HOSTS as $domain => $label) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return $label;
            }
        }

        return null;
    }
}
