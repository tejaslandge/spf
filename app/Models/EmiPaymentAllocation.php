<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'emi_payment_id',
    'emi_schedule_id',
    'amount',
    'principal_amount',
    'interest_amount',
])]
class EmiPaymentAllocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EmiPayment::class, 'emi_payment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmiSchedule::class, 'emi_schedule_id');
    }
}
