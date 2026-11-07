<?php

namespace App\Models;

use App\Enums\CollateralVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id',
    'item',
    'description',
    'estimated_value',
    'quantity',
    'identification_details',
    'remarks',
    'verification_status',
    'verified_by',
    'verified_at',
])]
class Collateral extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'quantity' => 'decimal:2',
            'verification_status' => CollateralVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
