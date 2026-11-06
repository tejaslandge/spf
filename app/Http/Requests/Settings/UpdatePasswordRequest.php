<?php

namespace App\Http\Requests\Settings;

use App\Services\Security\PasswordPolicyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $policy = app(PasswordPolicyService::class);

        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                ...$policy->rules(),
                'confirmed',
                'different:current_password',
                function (string $attribute, mixed $value, Closure $fail) use ($policy): void {
                    $user = $this->user();

                    if ($user !== null && $policy->isReused($user, (string) $value)) {
                        $fail(__('This password was used recently. Choose a password you have not used before.'));
                    }
                },
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('current password'),
            'password' => __('new password'),
            'password_confirmation' => __('password confirmation'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('The provided current password is incorrect.'),
            'password.different' => __('The new password must be different from your current password.'),
            'password.confirmed' => __('The new password confirmation does not match.'),
            'password_confirmation.required' => __('Please confirm the new password.'),
        ];
    }
}
