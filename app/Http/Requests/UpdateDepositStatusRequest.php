<?php

namespace App\Http\Requests;

use App\Enums\DepositStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepositStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false || $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DepositStatus::class)],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
