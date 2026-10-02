<?php

namespace App\Services\Llm;

interface LlmClient
{
    /**
     * Kirim chat completion dan kembalikan teks konten pertama.
     *
     * @param array $messages [['role' => 'system'|'user'|'assistant', 'content' => string], ...]
     * @param array $options  ['max_tokens' => int, 'temperature' => float, 'timeout' => int]
     * @return string konten balasan
     * @throws LlmException saat gagal (koneksi, auth, rate limit, dsb.)
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Ping kecil untuk tes koneksi & key. Return pesan sukses atau throw.
     */
    public function ping(): string;

    /**
     * Daftar model yang tersedia (jika endpoint mendukung).
     * @return string[]
     */
    public function listModels(): array;

    /**
     * Apakah model aktif bisa menerima input gambar.
     */
    public function supportsVision(): bool;
}
