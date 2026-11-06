<?php

namespace App\Http\Requests;

use App\Models\InterestConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInterestConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InterestConfiguration::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'effective_from' => ['required', 'date'],
            'borrower_monthly_rate' => ['required', 'numeric', 'gt:0', 'max:100'],
            'default_investor_monthly_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'admin_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'company_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_admin_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $total = bcadd(
                bcadd((string) $this->input('investor_allocation_percent'), (string) $this->input('admin_allocation_percent'), 4),
                (string) $this->input('company_allocation_percent'),
                4,
            );

            if (bccomp($total, '100', 4) !== 0) {
                $validator->errors()->add(
                    'company_allocation_percent',
                    'Investor, admin and company shares must total exactly 100% of collected interest. Current total: '.$total.'%.',
                );
            }

            if (bccomp((string) $this->input('default_investor_monthly_rate'), (string) $this->input('borrower_monthly_rate'), 6) > 0) {
                $validator->errors()->add(
                    'default_investor_monthly_rate',
                    'The default investor monthly rate cannot exceed the borrower monthly rate.',
                );
            }
        });
    }
}
