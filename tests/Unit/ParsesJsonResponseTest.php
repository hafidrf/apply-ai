<?php

namespace Tests\Unit;

use App\Services\Apply\ParsesJsonResponse;
use PHPUnit\Framework\TestCase;

class ParsesJsonResponseTest extends TestCase
{
    private function parse(string $raw): ?array
    {
        // Trait butuh instance — pakai anonymous class
        $obj = new class {
            use ParsesJsonResponse;
        };

        return $obj::extractJson($raw);
    }

    public function test_parses_plain_json(): void
    {
        $this->assertSame(['a' => 1], $this->parse('{"a": 1}'));
    }

    public function test_strips_markdown_json_fence(): void
    {
        $raw = "```json\n{\"kanal\": \"email\", \"yakin\": true}\n```";
        $this->assertSame(['kanal' => 'email', 'yakin' => true], $this->parse($raw));
    }

    public function test_strips_plain_markdown_fence(): void
    {
        $raw = "```\n{\"a\": \"b\"}\n```";
        $this->assertSame(['a' => 'b'], $this->parse($raw));
    }

    public function test_extracts_json_from_surrounding_prose(): void
    {
        $raw = "Tentu, berikut hasilnya:\n{\"pesan\": \"Halo\", \"jumlah_kata\": 1}\nSemoga membantu!";
        $result = $this->parse($raw);
        $this->assertSame('Halo', $result['pesan']);
        $this->assertSame(1, $result['jumlah_kata']);
    }

    public function test_handles_nested_objects(): void
    {
        $raw = '{"outer": {"inner": {"deep": [1,2,3]}}}';
        $this->assertSame(['outer' => ['inner' => ['deep' => [1, 2, 3]]]], $this->parse($raw));
    }

    public function test_handles_braces_inside_strings(): void
    {
        $raw = '{"pesan": "Gunakan {placeholder} di sini", "n": 1}';
        $result = $this->parse($raw);
        $this->assertSame('Gunakan {placeholder} di sini', $result['pesan']);
    }

    public function test_handles_escaped_quotes(): void
    {
        $raw = '{"pesan": "Dia berkata \"halo\" tadi"}';
        $this->assertSame('Dia berkata "halo" tadi', $this->parse($raw)['pesan']);
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull($this->parse('ini bukan json sama sekali'));
    }

    public function test_handles_unicode_indonesian(): void
    {
        $raw = '{"pesan": "Selamat siang! Saya tertarik 😊", "kata": 7}';
        $this->assertSame('Selamat siang! Saya tertarik 😊', $this->parse($raw)['pesan']);
    }
}
