<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'loan_id',
    'borrower_id',
    'outstanding_principal',
    'accrued_interest',
    'other_outstanding_dues',
    'foreclosure_charges',
    'total_settlement',
    'eligible_adjustments',
    'waived_interest',
    'foreclosure_request_date',
    'effective_foreclosure_date',
    'approval_status',
    'payment_status',
    'payment_date',
    'actual_closure_date',
    'rejection_reason',
    'approved_by',
])]
class ForeclosureRequest extends Model
{
    use Auditable, SoftDeletes;

    protected $casts = [
        'outstanding_principal' => 'decimal:2',
        'accrued_interest' => 'decimal:2',
        'other_outstanding_dues' => 'decimal:2',
        'foreclosure_charges' => 'decimal:2',
        'total_settlement' => 'decimal:2',
        'eligible_adjustments' => 'decimal:2',
        'waived_interest' => 'decimal:2',
        'foreclosure_request_date' => 'date',
        'effective_foreclosure_date' => 'date',
        'payment_date' => 'date',
        'actual_closure_date' => 'date',
        'approval_status' => 'string',
        'payment_status' => 'string',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('approval_status', 'rejected');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }
}
