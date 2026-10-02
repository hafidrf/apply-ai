<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu peristiwa pada pesan lamaran — dipakai untuk membangun halaman Riwayat.
 *
 * event: generated | variant | revised | copied
 * kind : utama | varian
 */
class MessageEvent extends Model
{
    public const GENERATED = 'generated';
    public const VARIANT = 'variant';
    public const REVISED = 'revised';
    public const COPIED = 'copied';

    protected $fillable = [
        'user_id', 'job_id', 'event', 'kind', 'channel',
        'position', 'company', 'message_text', 'instruction', 'word_count',
    ];

    protected $casts = [
        'word_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Catat snapshot pesan sebagai riwayat.
     * Mengembalikan null kalau tidak ada teks yang bisa dicatat (biar tidak ada entri kosong).
     */
    public static function record(
        Job $job,
        ?Draft $draft,
        string $event,
        string $kind = 'utama',
        ?string $instruction = null,
    ): ?self
    {
        $text = $kind === 'varian' ? $draft?->variant_text : $draft?->message_text;

        if (trim((string) $text) === '') {
            return null;
        }

        return self::create([
            'user_id' => $job->user_id,
            'job_id' => $job->id,
            'event' => $event,
            'kind' => $kind,
            'channel' => $draft?->channel ?? $job->channel,
            'position' => $job->position,
            'company' => $job->company,
            'message_text' => $text,
            'word_count' => str_word_count(strip_tags((string) $text)),
            'instruction' => $instruction,
        ]);
    }
}
