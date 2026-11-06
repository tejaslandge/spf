<?php

namespace App\Http\Resources;

use App\Models\InterestDistributionLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InterestDistributionLedger */
class InterestDistributionLedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'emi_payment_id' => $this->emi_payment_id,
            'receipt_no' => $this->whenLoaded('payment', fn () => $this->payment?->receipt_no),
            'loan_id' => $this->loan_id,
            'loan_code' => $this->whenLoaded('loan', fn () => $this->loan?->loan_code),
            'beneficiary_type' => $this->beneficiary_type?->value,
            'entry_type' => $this->entry_type?->value,
            'investor_id' => $this->investor_id,
            'investor_name' => $this->whenLoaded('investor', fn () => $this->investor?->name),
            'admin_id' => $this->admin_id,
            'admin_name' => $this->whenLoaded('admin', fn () => $this->admin?->name),
            'amount' => (string) $this->amount,
            'signed_amount' => $this->signedAmount(),
            'collected_interest' => (string) $this->collected_interest,
            'applicable_rate' => $this->applicable_rate !== null ? (string) $this->applicable_rate : null,
            'share_percent' => $this->share_percent !== null ? (string) $this->share_percent : null,
            'period_month' => $this->period_month?->format('Y-m'),
            'status' => $this->status?->value,
            'reversal_of_id' => $this->reversal_of_id,
            'reference' => $this->reference,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
