<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    /**
     * Only users granted the modify-settings permission may update
     * administrative general settings. Super Admin always passes.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::SettingsModify) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'company_legal_name' => ['nullable', 'string', 'max:160'],
            'company_registration_number' => ['nullable', 'string', 'max:60'],
            'company_email' => ['nullable', 'email', 'max:160'],
            'company_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'company_support_email' => ['nullable', 'email', 'max:160'],
            'company_support_mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_city' => ['nullable', 'string', 'max:80'],
            'company_state' => ['nullable', 'string', 'max:80'],
            'company_pincode' => ['nullable', 'regex:/^[0-9]{4,10}$/'],
            'company_website' => ['nullable', 'url', 'max:160'],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'company_currency_code' => ['nullable', 'string', 'size:3', 'alpha:ascii'],
            'company_currency_symbol' => ['nullable', 'string', 'max:5'],
            'company_timezone' => ['nullable', 'timezone'],
            'company_date_format' => ['nullable', 'in:d M Y,d/m/Y,m/d/Y,Y-m-d'],
            'company_time_format' => ['nullable', 'in:H:i,h:i A'],
            'company_financial_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'reason' => ['nullable', 'string', 'max:500'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('company name'),
            'company_legal_name' => __('company legal name'),
            'company_registration_number' => __('registration number'),
            'company_email' => __('company email'),
            'company_phone' => __('company phone'),
            'company_support_email' => __('support email'),
            'company_support_mobile' => __('support mobile number'),
            'company_address' => __('company address'),
            'company_city' => __('city'),
            'company_state' => __('state'),
            'company_pincode' => __('PIN code'),
            'company_website' => __('website URL'),
            'company_logo' => __('company logo'),
            'company_currency_code' => __('currency'),
            'company_currency_symbol' => __('currency symbol'),
            'company_timezone' => __('timezone'),
            'company_date_format' => __('date format'),
            'company_time_format' => __('time format'),
            'company_financial_year_start_month' => __('financial year start month'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_phone.regex' => __('The company phone may only contain digits, spaces and the characters + - ( ).'),
            'company_support_mobile.regex' => __('The support mobile number may only contain digits, spaces and the characters + - ( ).'),
            'company_pincode.regex' => __('The PIN code must contain 4 to 10 digits.'),
            'company_currency_code.size' => __('Use the three-letter currency code, for example INR.'),
        ];
    }
}
