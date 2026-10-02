<?php

namespace Tests\Unit;

use App\Services\Apply\InputTextBuilder;
use PHPUnit\Framework\TestCase;

class InputTextBuilderTest extends TestCase
{
    public function test_detects_urls_in_pasted_text(): void
    {
        $text = 'Lowongan menarik, detail di https://contoh.com/lowongan ini ya.';

        $this->assertSame(['https://contoh.com/lowongan'], InputTextBuilder::detectUrls($text));
    }

    public function test_detects_multiple_urls(): void
    {
        $text = "Cek https://a.com dan https://b.com/x?y=1 ya";

        $this->assertSame(['https://a.com', 'https://b.com/x?y=1'], InputTextBuilder::detectUrls($text));
    }

    public function test_deduplicates_repeated_urls(): void
    {
        $text = 'https://sama.com lalu https://sama.com lagi';

        $this->assertSame(['https://sama.com'], InputTextBuilder::detectUrls($text));
    }

    public function test_ignores_trailing_punctuation(): void
    {
        $text = 'Lihat (https://contoh.com/lowongan), terima kasih.';

        $this->assertSame(['https://contoh.com/lowongan'], InputTextBuilder::detectUrls($text));
    }

    public function test_returns_empty_when_no_url(): void
    {
        $this->assertSame([], InputTextBuilder::detectUrls('Tidak ada link di sini.'));
    }

    public function test_throws_when_nothing_to_process(): void
    {
        $this->expectException(\RuntimeException::class);

        (new InputTextBuilder())->build('   ', [], false);
    }

    public function test_text_only_input_is_passed_through(): void
    {
        $text = 'Dibutuhkan Frontend Developer di PT Contoh.';
        $result = (new InputTextBuilder())->build($text, [], false);

        $this->assertSame($text, $result['text']);
        $this->assertSame(mb_strlen($text), $result['meta']['total_chars']);
        $this->assertSame('text', $result['meta']['sources'][0]['type']);
    }

    public function test_unreadable_link_is_a_note_not_a_failure(): void
    {
        // Teks lowongan tetap dipakai walau link-nya gagal dibaca
        $result = (new InputTextBuilder())->build(
            'Lowongan Frontend Developer. Detail: https://www.linkedin.com/in/seseorang',
            [],
            true,
        );

        $this->assertStringContainsString('Frontend Developer', $result['text']);

        $linkSource = collect($result['meta']['sources'])->firstWhere('type', 'link');
        $this->assertNotNull($linkSource);
        $this->assertNotNull($linkSource['error'], 'Link LinkedIn harus tercatat gagal');
        $this->assertNotEmpty($result['meta']['notes']);
    }
}
