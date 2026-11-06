<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investor_payout_id',
    'distribution_ledger_id',
    'loan_id',
    'investor_id',
    'amount',
])]
class InvestorPayoutItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(InvestorPayout::class, 'investor_payout_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(InterestDistributionLedger::class, 'distribution_ledger_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
