<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    public const STATUSES = ['pending', 'parsed', 'matched', 'composed', 'failed'];

    protected $table = 'job_postings';

    protected $fillable = [
        'user_id', 'profile_id', 'raw_input_type', 'raw_text', 'ocr_text', 'input_meta', 'channel',
        'position', 'company', 'parsed', 'matching', 'reframes', 'status', 'error',
    ];

    protected $casts = [
        'parsed' => 'array',
        'matching' => 'array',
        'reframes' => 'array',
        'input_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Selalu dari yang TERBARU — UI memakai drafts[0] sebagai pesan aktif,
     * jadi urutan ascending bikin pesan lama yang tampil setelah generate ulang.
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class)->latest('id');
    }
}
