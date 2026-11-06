<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investor_id',
    'monthly_rate',
    'effective_from',
    'effective_to',
    'is_active',
    'notes',
    'created_by',
])]
class InvestorInterestRate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_rate' => 'decimal:6',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }

    public static function activeFor(Investor $investor, \DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()
            ->where('investor_id', $investor->getKey())
            ->active()
            ->effectiveOn($date ?? today())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
