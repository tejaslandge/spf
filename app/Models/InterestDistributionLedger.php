<?php

namespace App\Models;

use App\Enums\DistributionBeneficiaryType;
use App\Enums\DistributionEntryType;
use App\Enums\DistributionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'entitlement_key',
    'emi_payment_id',
    'loan_id',
    'beneficiary_type',
    'entry_type',
    'investor_id',
    'admin_id',
    'investor_allocation_id',
    'loan_investor_distribution_id',
    'amount',
    'collected_interest',
    'applicable_rate',
    'share_percent',
    'period_month',
    'status',
    'reversal_of_id',
    'reference',
    'metadata',
    'created_by',
])]
class InterestDistributionLedger extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'beneficiary_type' => DistributionBeneficiaryType::class,
            'entry_type' => DistributionEntryType::class,
            'status' => DistributionStatus::class,
            'amount' => 'decimal:2',
            'collected_interest' => 'decimal:2',
            'applicable_rate' => 'decimal:6',
            'share_percent' => 'decimal:6',
            'period_month' => 'date',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EmiPayment::class, 'emi_payment_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InvestorAllocation::class, 'investor_allocation_id');
    }

    public function loanInvestorDistribution(): BelongsTo
    {
        return $this->belongsTo(LoanInvestorDistribution::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function investorPayoutItem(): HasOne
    {
        return $this->hasOne(InvestorPayoutItem::class, 'distribution_ledger_id');
    }

    public function adminCommissionPayoutItem(): HasOne
    {
        return $this->hasOne(AdminCommissionPayoutItem::class, 'distribution_ledger_id');
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('entry_type', DistributionEntryType::Earning->value);
    }

    public function scopeReversals(Builder $query): Builder
    {
        return $query->where('entry_type', DistributionEntryType::Reversal->value);
    }

    public function scopeForInvestors(Builder $query): Builder
    {
        return $query->where('beneficiary_type', DistributionBeneficiaryType::Investor->value);
    }

    public function scopeForAdmins(Builder $query): Builder
    {
        return $query->where('beneficiary_type', DistributionBeneficiaryType::Admin->value);
    }

    public function scopeForCompany(Builder $query): Builder
    {
        return $query->where('beneficiary_type', DistributionBeneficiaryType::Company->value);
    }

    public function signedAmount(): string
    {
        return $this->entry_type === DistributionEntryType::Reversal
            ? bcsub('0', (string) $this->amount, 2)
            : (string) $this->amount;
    }
}
