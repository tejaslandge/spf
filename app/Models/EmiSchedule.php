<?php

namespace App\Models;

use App\Enums\EmiStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'loan_id',
    'installment_no',
    'due_date',
    'principal_amount',
    'interest_amount',
    'total_amount',
    'paid_principal',
    'paid_interest',
    'paid_amount',
    'outstanding_principal',
    'status',
    'settlement_type',
    'paid_at',
])]
class EmiSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_principal' => 'decimal:2',
            'paid_interest' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_principal' => 'decimal:2',
            'status' => EmiStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmiPayment::class);
    }

    public function remainingAmount(): string
    {
        return bcsub((string) $this->total_amount, (string) $this->paid_amount, 2);
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isSettled() && $this->due_date->isPast();
    }

    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNotIn('status', [EmiStatus::Paid->value, EmiStatus::Waived->value]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unsettled()->whereDate('due_date', '<', today());
    }
}
