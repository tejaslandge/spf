<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kyc_document_id',
    'subject_type',
    'subject_id',
    'action',
    'old_status',
    'new_status',
    'rejection_reason',
    'acted_by',
    'notes',
])]
class KycVerificationHistory extends Model
{
    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
