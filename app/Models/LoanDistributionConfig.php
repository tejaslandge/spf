<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'loan_id',
    'interest_configuration_id',
    'borrower_monthly_rate',
    'default_investor_monthly_rate',
    'investor_allocation_percent',
    'admin_allocation_percent',
    'company_allocation_percent',
    'rules_snapshot',
    'locked_at',
    'created_by',
])]
class LoanDistributionConfig extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrower_monthly_rate' => 'decimal:6',
            'default_investor_monthly_rate' => 'decimal:6',
            'investor_allocation_percent' => 'decimal:4',
            'admin_allocation_percent' => 'decimal:4',
            'company_allocation_percent' => 'decimal:4',
            'rules_snapshot' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(InterestConfiguration::class, 'interest_configuration_id');
    }

    public function investorDistributionRows(): HasMany
    {
        return $this->hasMany(LoanInvestorDistribution::class, 'loan_id', 'loan_id');
    }

    public function adminAssignments(): HasMany
    {
        return $this->hasMany(LoanAdminAssignment::class, 'loan_id', 'loan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
