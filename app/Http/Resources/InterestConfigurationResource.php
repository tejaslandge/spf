<?php

namespace App\Http\Resources;

use App\Models\InterestConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InterestConfiguration */
class InterestConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'effective_from' => $this->effective_from?->toDateString(),
            'borrower_monthly_rate' => (string) $this->borrower_monthly_rate,
            'default_investor_monthly_rate' => (string) $this->default_investor_monthly_rate,
            'investor_allocation_percent' => (string) $this->investor_allocation_percent,
            'admin_allocation_percent' => (string) $this->admin_allocation_percent,
            'company_allocation_percent' => (string) $this->company_allocation_percent,
            'default_admin_commission_rate' => $this->default_admin_commission_rate !== null ? (string) $this->default_admin_commission_rate : null,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
