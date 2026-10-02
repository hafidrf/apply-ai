<?php

namespace Tests\Unit;

use App\Services\Apply\MessageComposer;
use PHPUnit\Framework\TestCase;

/**
 * Guardrail PRD §9 — batas panjang pesan per kanal.
 */
class MessageComposerWordLimitTest extends TestCase
{
    public function test_email_within_range(): void
    {
        $msg = 'Subject: Lamaran' . "\n\n" . implode(' ', array_fill(0, 150, 'kata'));
        $this->assertTrue(MessageComposer::validateWordCount('email', $msg));
    }

    public function test_email_subject_line_excluded_from_count(): void
    {
        // 150 kata isi + subject panjang; subject tidak dihitung
        $msg = 'Subject: ' . implode(' ', array_fill(0, 50, 'subjek'))
            . "\n\n" . implode(' ', array_fill(0, 150, 'kata'));
        $this->assertTrue(MessageComposer::validateWordCount('email', $msg));
    }

    public function test_email_too_short_rejected(): void
    {
        $msg = implode(' ', array_fill(0, 100, 'kata'));
        $this->assertFalse(MessageComposer::validateWordCount('email', $msg));
    }

    public function test_email_too_long_rejected(): void
    {
        $msg = implode(' ', array_fill(0, 200, 'kata'));
        $this->assertFalse(MessageComposer::validateWordCount('email', $msg));
    }

    public function test_linkedin_range(): void
    {
        $ok = implode(' ', array_fill(0, 70, 'kata'));
        $tooLong = implode(' ', array_fill(0, 120, 'kata'));
        $this->assertTrue(MessageComposer::validateWordCount('linkedin', $ok));
        $this->assertFalse(MessageComposer::validateWordCount('linkedin', $tooLong));
    }

    public function test_whatsapp_range(): void
    {
        $ok = implode(' ', array_fill(0, 55, 'kata'));
        $tooShort = implode(' ', array_fill(0, 20, 'kata'));
        $this->assertTrue(MessageComposer::validateWordCount('whatsapp', $ok));
        $this->assertFalse(MessageComposer::validateWordCount('whatsapp', $tooShort));
    }

    public function test_portal_range(): void
    {
        $ok = implode(' ', array_fill(0, 120, 'kata'));
        $tooLong = implode(' ', array_fill(0, 200, 'kata'));
        $this->assertTrue(MessageComposer::validateWordCount('portal', $ok));
        $this->assertFalse(MessageComposer::validateWordCount('portal', $tooLong));
    }

    public function test_word_limits_match_prd_section_9(): void
    {
        $this->assertSame([120, 180], MessageComposer::WORD_LIMITS['email']);
        $this->assertSame([50, 90], MessageComposer::WORD_LIMITS['linkedin']);
        $this->assertSame([40, 70], MessageComposer::WORD_LIMITS['whatsapp']);
        $this->assertSame([100, 150], MessageComposer::WORD_LIMITS['portal']);
    }

    /** Kanal tambahan untuk lowongan dari sosial media (Threads/IG/X). */
    public function test_dm_channel_range(): void
    {
        $ok = implode(' ', array_fill(0, 60, 'kata'));
        $tooShort = implode(' ', array_fill(0, 20, 'kata'));
        $tooLong = implode(' ', array_fill(0, 120, 'kata'));

        $this->assertSame([45, 80], MessageComposer::WORD_LIMITS['dm']);
        $this->assertTrue(MessageComposer::validateWordCount('dm', $ok));
        $this->assertFalse(MessageComposer::validateWordCount('dm', $tooShort));
        $this->assertFalse(MessageComposer::validateWordCount('dm', $tooLong));
    }
}
