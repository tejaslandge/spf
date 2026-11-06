<?php

namespace App\Http\Resources;

use App\Models\AdminCommissionPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdminCommissionPayout */
class AdminCommissionPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_code' => $this->payout_code,
            'user_id' => $this->user_id,
            'admin_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'period_month' => $this->period_month?->format('Y-m'),
            'batch_no' => $this->batch_no,
            'gross_commission' => (string) $this->gross_commission,
            'net_amount' => (string) $this->net_amount,
            'status' => $this->status?->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
