<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id',
    'investor_id',
    'investor_allocation_id',
    'principal_amount',
    'monthly_rate',
    'share_percent',
])]
class LoanInvestorDistribution extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'monthly_rate' => 'decimal:6',
            'share_percent' => 'decimal:6',
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

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InvestorAllocation::class, 'investor_allocation_id');
    }
}
