<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investor_deposit_id',
    'old_status',
    'new_status',
    'rejection_reason',
    'notes',
    'changed_by',
])]
class DepositStatusHistory extends Model
{
    public const UPDATED_AT = null;

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(InvestorDeposit::class, 'investor_deposit_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'old_status' => DepositStatus::class,
            'new_status' => DepositStatus::class,
        ];
    }
}
