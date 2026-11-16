<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtp extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'challenge_id',
        'phone',
        'user_id',
        'otp_hash',
        'expires_at',
        'last_sent_at',
        'resend_available_at',
        'attempts',
        'used_at',
        'remember',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'resend_available_at' => 'datetime',
            'used_at' => 'datetime',
            'remember' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
