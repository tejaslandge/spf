<?php

namespace App\Models;

use App\Enums\CommitmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id',
    'investor_id',
    'amount',
    'status',
    'confirmed_at',
    'withdrawn_at',
    'transaction_reference',
    'confirmed_by',
    'notes',
])]
class LoanFundingCommitment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => CommitmentStatus::class,
            'confirmed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', CommitmentStatus::Confirmed);
    }
}
