<?php

namespace App\Models;

use App\Enums\InvestorStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\InvestorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'investor_code',
    'name',
    'address',
    'mobile',
    'email',
    'pan_number',
    'aadhaar_number',
    'bank_name',
    'bank_account_number',
    'bank_ifsc',
    'status',
    'notes',
    'created_by',
])]
class Investor extends Model
{
    /** @use HasFactory<InvestorFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Investor $investor): void {
            $investor->investor_code ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::withTrashed()->max('id') ?? 0) + 1;

        return 'INV-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvestorStatus::class,
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvestorAllocation::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(InvestorPayout::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(InvestorDeposit::class)->orderByDesc('deposited_at');
    }

    public function interestRates(): HasMany
    {
        return $this->hasMany(InvestorInterestRate::class)->orderByDesc('effective_from');
    }

    public function distributionEntries(): HasMany
    {
        return $this->hasMany(InterestDistributionLedger::class, 'investor_id');
    }

    public function kycDocuments(): MorphMany
    {
        return $this->morphMany(KycDocument::class, 'documentable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.strtolower($term).'%';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(mobile) LIKE ?', [$like])
            ->orWhereRaw('LOWER(investor_code) LIKE ?', [$like]));
    }

    public function activeCapital(): string
    {
        return (string) $this->allocations()->where('status', 'active')->sum('principal_amount');
    }
}
