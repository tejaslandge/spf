<?php

namespace App\Http\Requests;

use App\Models\InterestConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvestorInterestRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'investor_id' => ['required', 'exists:investors,id'],
            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $borrowerRate = (float) InterestConfiguration::activeFor($this->input('effective_from'))?->borrower_monthly_rate;

            if ($borrowerRate > 0 && (float) $this->input('monthly_rate') > $borrowerRate) {
                $validator->errors()->add('monthly_rate', 'An investor monthly rate cannot exceed the borrower monthly rate of '.$borrowerRate.'%.');
            }
        });
    }
}
