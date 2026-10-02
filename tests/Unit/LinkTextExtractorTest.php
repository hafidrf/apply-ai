<?php

namespace Tests\Unit;

use App\Services\Apply\LinkTextExtractor;
use PHPUnit\Framework\TestCase;

class LinkTextExtractorTest extends TestCase
{
    /**
     * Situs ber-login harus ditolak SEBELUM request jaringan
     * (guardrail PRD §10: jangan berasumsi isi link yang gagal diakses).
     */
    public function test_linkedin_is_rejected_with_clear_message(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/LinkedIn/i');

        (new LinkTextExtractor())->extract('https://www.linkedin.com/in/someone');
    }

    public function test_other_auth_wall_sites_are_rejected(): void
    {
        foreach ([
            'https://facebook.com/profil',
            'https://www.instagram.com/username',
            'https://x.com/username',
            'https://www.jobstreet.co.id/lowongan',
        ] as $url) {
            try {
                (new LinkTextExtractor())->extract($url);
                $this->fail("Seharusnya ditolak: {$url}");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('login', $e->getMessage(), "URL: {$url}");
            }
        }
    }

    public function test_subdomain_of_auth_wall_site_is_also_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        (new LinkTextExtractor())->extract('https://id.linkedin.com/in/someone');
    }

    public function test_invalid_url_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        (new LinkTextExtractor())->extract('ht!tp://bukan url');
    }

    public function test_normal_host_is_not_treated_as_auth_wall(): void
    {
        // Tidak boleh ditolak sebagai auth-wall — kegagalan koneksi
        // adalah masalah berbeda (dan itu ok untuk test ini).
        try {
            (new LinkTextExtractor())->extract('https://portfolio-contoh-yang-tidak-ada-xyz.example');
            $this->fail('Domain contoh seharusnya gagal konek');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('memerlukan login', $e->getMessage());
        }
    }
}
