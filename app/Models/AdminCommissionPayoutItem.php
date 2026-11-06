<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admin_commission_payout_id',
    'distribution_ledger_id',
    'loan_id',
    'user_id',
    'amount',
])]
class AdminCommissionPayoutItem extends Model
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
        return $this->belongsTo(AdminCommissionPayout::class, 'admin_commission_payout_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(InterestDistributionLedger::class, 'distribution_ledger_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
