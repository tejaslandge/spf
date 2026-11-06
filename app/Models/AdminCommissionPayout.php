<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payout_code',
    'user_id',
    'period_month',
    'batch_no',
    'gross_commission',
    'net_amount',
    'status',
    'approved_at',
    'approved_by',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
    'paid_at',
    'processed_by',
    'payment_reference',
    'notes',
    'created_by',
])]
class AdminCommissionPayout extends Model
{
    protected static function booted(): void
    {
        static::creating(function (AdminCommissionPayout $payout): void {
            $payout->payout_code ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::max('id') ?? 0) + 1;

        return 'ACP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'gross_commission' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => PayoutStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdminCommissionPayoutItem::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PayoutStatus::Pending);
    }
}
