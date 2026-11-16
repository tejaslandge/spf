<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'regex:/^\+?\d{1,4}$/'],
            'mobile' => ['required', 'string', 'regex:/^[0-9\s\-]{6,15}$/'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
