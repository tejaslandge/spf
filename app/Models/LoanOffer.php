<?php

namespace App\Models;

use App\Enums\InterestMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id',
    'principal_amount',
    'annual_interest_rate',
    'interest_method',
    'tenure_months',
    'emi_amount',
    'total_interest',
    'total_repayment',
    'processing_fee',
    'other_charges',
    'comparison',
    'offered_at',
    'accepted_at',
    'accepted_by',
    'created_by',
    'notes',
])]
class LoanOffer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4',
            'interest_method' => InterestMethod::class,
            'emi_amount' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'total_repayment' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'comparison' => 'array',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
