<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmKey extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'label', 'api_key', 'base_url', 'default_model', 'supports_vision', 'is_default',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_default' => 'boolean',
        'supports_vision' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Key yang dimask untuk dikirim ke frontend: sk-...xyz
     */
    public function maskedKey(): string
    {
        $key = $this->api_key ?? '';
        if ($key === '') {
            return '';
        }

        return mb_substr($key, 0, 4) . '...' . mb_substr($key, -4);
    }
}
