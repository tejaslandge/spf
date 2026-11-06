<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Concerns\Auditable;
use App\Models\ForeclosureRequest;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'loan_code',
    'borrower_id',
    'principal_amount',
    'annual_interest_rate',
    'interest_method',
    'tenure_months',
    'emi_frequency',
    'disbursement_date',
    'first_emi_date',
    'emi_amount',
    'total_interest',
    'total_payable',
    'outstanding_principal',
    'outstanding_interest',
    'foreclosure_charge',
    'processing_fee',
    'other_charges',
    'status',
    'application_status',
    'purpose',
    'monthly_income',
    'employment_details',
    'funding_status',
    'funding_locked_at',
    'capital_verified_at',
    'capital_verified_by',
    'final_approved_at',
    'final_approved_by',
    'disbursement_reference',
    'approved_by',
    'created_by',
    'closed_at',
    'notes',
])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Loan $loan): void {
            $loan->loan_code ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::withTrashed()->max('id') ?? 0) + 1;

        return 'LN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4',
            'emi_amount' => 'decimal:2',
            'total_interest' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'outstanding_principal' => 'decimal:2',
            'outstanding_interest' => 'decimal:2',
            'foreclosure_charge' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'monthly_income' => 'decimal:2',
            'interest_method' => InterestMethod::class,
            'status' => LoanStatus::class,
            'application_status' => ApplicationStatus::class,
            'funding_status' => LoanFundingStatus::class,
            'disbursement_date' => 'date',
            'first_emi_date' => 'date',
            'funding_locked_at' => 'datetime',
            'capital_verified_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmiSchedule::class)->orderBy('installment_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EmiPayment::class);
    }

    public function guarantors(): BelongsToMany
    {
        return $this->belongsToMany(Guarantor::class, 'loan_guarantors')
            ->withTimestamps()
            ->orderBy('guarantors.id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvestorAllocation::class);
    }

    public function distributionConfig(): HasOne
    {
        return $this->hasOne(LoanDistributionConfig::class);
    }

    public function investorDistributions(): HasMany
    {
        return $this->hasMany(LoanInvestorDistribution::class);
    }

    public function adminAssignments(): HasMany
    {
        return $this->hasMany(LoanAdminAssignment::class);
    }

    public function foreclosureRequests(): HasMany
    {
        return $this->hasMany(ForeclosureRequest::class)->latest('foreclosure_request_date');
    }

    public function latestForeclosureRequest(): ?ForeclosureRequest
    {
        return $this->foreclosureRequests()->first();
    }

    public function distributionEntries(): HasMany
    {
        return $this->hasMany(InterestDistributionLedger::class);
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(LoanFundingCommitment::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LoanOffer::class)->orderByDesc('offered_at');
    }

    public function capitalVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capital_verified_by');
    }

    public function finalApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    public function latestOffer(): ?LoanOffer
    {
        return $this->offers()->first();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.strtolower($term).'%';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(loan_code) LIKE ?', [$like])
            ->orWhereHas('borrower', fn (Builder $query) => $query
                ->whereRaw('LOWER(full_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(mobile) LIKE ?', [$like])));
    }

    public function paidAmount(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    public function isFullyPaid(): bool
    {
        return ! $this->schedules()->whereNotIn('status', ['paid', 'waived'])->exists();
    }

    public function guarantorsEditable(): bool
    {
        return $this->status === LoanStatus::Pending
            && $this->disbursement_date === null
            && $this->final_approved_at === null;
    }

    public function completionPercentage(): float
    {
        $total = $this->schedules()->count();

        if ($total === 0) {
            return 0;
        }

        return round(($this->schedules()->whereIn('status', ['paid', 'waived'])->count() / $total) * 100, 1);
    }
}
