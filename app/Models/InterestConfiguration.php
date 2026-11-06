<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'effective_from',
    'borrower_monthly_rate',
    'default_investor_monthly_rate',
    'investor_allocation_percent',
    'admin_allocation_percent',
    'company_allocation_percent',
    'default_admin_commission_rate',
    'is_active',
    'notes',
    'created_by',
])]
class InterestConfiguration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'borrower_monthly_rate' => 'decimal:6',
            'default_investor_monthly_rate' => 'decimal:6',
            'investor_allocation_percent' => 'decimal:4',
            'admin_allocation_percent' => 'decimal:4',
            'company_allocation_percent' => 'decimal:4',
            'default_admin_commission_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LoanDistributionConfig::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date);
    }

    public static function activeFor(\DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()
            ->active()
            ->effectiveOn($date ?? today())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function totalAllocationPercent(): string
    {
        return bcadd(
            bcadd((string) $this->investor_allocation_percent, (string) $this->admin_allocation_percent, 4),
            (string) $this->company_allocation_percent,
            4,
        );
    }
}
