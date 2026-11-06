<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Models\User;
use App\Services\Security\PasswordPolicyService;
use App\Services\Security\SessionService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordPolicyService $policy,
        private readonly SessionService $sessions,
    ) {}

    /**
     * Update the authenticated user's password and stamp the change time.
     *
     * Other sessions are invalidated by the AuthenticateSession middleware,
     * which re-checks the stored password hash on every request; the current
     * session is re-hashed at the end of this request and stays signed in.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->policy->record($user);

        $user->forceFill([
            'password' => $request->validated('password'),
            'password_changed_at' => now(),
        ])->save();

        if (config('session.driver') === 'database') {
            $this->sessions->enforceSingleSessionPolicy($user);
        }

        AuditLogger::log('password.changed', $user, [], ['role' => $user->role->value]);

        return redirect()
            ->route('security.edit')
            ->with('status', __('Password updated successfully. Other sessions have been signed out.'));
    }
}
