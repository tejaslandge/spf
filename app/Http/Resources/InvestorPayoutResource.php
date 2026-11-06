<?php

namespace App\Http\Resources;

use App\Models\InvestorPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvestorPayout */
class InvestorPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_code' => $this->payout_code,
            'investor_id' => $this->investor_id,
            'investor_name' => $this->whenLoaded('investor', fn () => $this->investor?->name),
            'period_month' => $this->period_month?->format('Y-m'),
            'batch_no' => $this->batch_no,
            'gross_interest' => (string) $this->gross_interest,
            'management_fee' => (string) $this->management_fee,
            'tds_amount' => (string) $this->tds_amount,
            'net_amount' => (string) $this->net_amount,
            'status' => $this->status?->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'rejection_reason' => $this->rejection_reason,
            'items' => InvestorPayoutItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
