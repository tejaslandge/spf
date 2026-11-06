<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payout_code',
    'investor_id',
    'period_month',
    'batch_no',
    'gross_interest',
    'management_fee',
    'tds_amount',
    'net_amount',
    'status',
    'approved_at',
    'approved_by',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
    'paid_at',
    'processed_at',
    'processed_by',
    'payment_reference',
    'notes',
    'created_by',
])]
class InvestorPayout extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::creating(function (InvestorPayout $payout): void {
            $payout->payout_code ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::max('id') ?? 0) + 1;

        return 'PAY-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'gross_interest' => 'decimal:2',
            'management_fee' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'status' => PayoutStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvestorPayoutItem::class);
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
