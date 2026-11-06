<?php

namespace App\Http\Resources;

use App\Models\InvestorPayoutItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvestorPayoutItem */
class InvestorPayoutItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distribution_ledger_id' => $this->distribution_ledger_id,
            'loan_id' => $this->loan_id,
            'loan_code' => $this->whenLoaded('loan', fn () => $this->loan?->loan_code),
            'amount' => (string) $this->amount,
        ];
    }
}
