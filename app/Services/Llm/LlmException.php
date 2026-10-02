<?php

namespace App\Services\Llm;

class LlmException extends \RuntimeException
{
    public static function connection(string $provider, string $detail): self
    {
        return new self("[{$provider}] Gagal terhubung: {$detail}");
    }

    public static function auth(string $provider): self
    {
        return new self("[{$provider}] API key ditolak (401/403). Periksa key di halaman Settings.");
    }

    public static function rateLimit(string $provider): self
    {
        return new self("[{$provider}] Rate limit / kuota habis (429). Coba lagi nanti atau ganti provider.");
    }

    public static function badResponse(string $provider, string $detail): self
    {
        return new self("[{$provider}] Respons tidak valid: {$detail}");
    }
}
