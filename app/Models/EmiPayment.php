<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'receipt_no',
    'loan_id',
    'emi_schedule_id',
    'amount',
    'principal_component',
    'interest_component',
    'penalty_amount',
    'is_reversed',
    'reversed_at',
    'reversed_by',
    'reversal_reason',
    'reversal_of_payment_id',
    'payment_date',
    'payment_method',
    'reference',
    'notes',
    'collected_by',
])]
class EmiPayment extends Model
{
    use Auditable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (EmiPayment $payment): void {
            $payment->receipt_no ??= static::nextReceiptNo();
        });
    }

    public static function nextReceiptNo(): string
    {
        $next = (static::withTrashed()->max('id') ?? 0) + 1;

        return 'RCPT-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_component' => 'decimal:2',
            'interest_component' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
            'payment_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EmiPaymentAllocation::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(InterestDistributionLedger::class, 'emi_payment_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class, 'emi_schedule_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
