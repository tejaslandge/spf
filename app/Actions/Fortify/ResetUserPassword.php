<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Security\PasswordPolicyService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        app(PasswordPolicyService::class)->record($user);

        $user->forceFill([
            'password' => $input['password'],
            'password_changed_at' => now(),
        ])->save();

        AuditLogger::log('password.reset', $user, [], []);
    }
}
