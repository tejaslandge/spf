<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Services\Auth\RenflairSmsResult;
use App\Services\Auth\RenflairSmsService;
use App\Services\Security\LoginAttemptService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLogin(): View
    {
        return view('pages::auth.login');
    }

    /**
     * Start a pending OTP challenge for the registered mobile number.
     *
     * The user is intentionally not authenticated here. Authentication only
     * happens after the registered mobile number has been verified.
     */
    public function login(
        LoginRequest $request,
        RenflairSmsService $sms,
        LoginOtpService $otp,
        LoginAttemptService $attempts,
    ): RedirectResponse {
        $login = (string) $request->input('mobile');
        $remember = $request->boolean('remember');

        if ($attempts->isLocked($login, $request->ip())) {
            $lockedUntil = $attempts->lockedUntil($login, $request->ip());
            $minutes = $lockedUntil !== null
                ? max(1, (int) ceil(now()->diffInMinutes($lockedUntil, true)))
                : $attempts->lockoutMinutes();

            throw ValidationException::withMessages([
                'mobile' => __('Too many failed sign-in attempts. Your account is locked for :minutes more minute(s).', ['minutes' => $minutes]),
            ]);
        }

        $user = User::query()
            ->whereIn('mobile', array_unique([$login, '91'.$login, '+91'.$login]))
            ->first();

        if (! $user || $user->status !== 'active') {
            $attempts->recordFailure($login, $request->ip());

            throw ValidationException::withMessages([
                'mobile' => __('auth.failed'),
            ]);
        }

        $phone = PhoneNumber::national($user->mobile);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'mobile' => __('This account is not set up for mobile verification. Please contact your administrator.'),
            ]);
        }

        $attempts->clear($login, $request->ip());

        $code = $otp->start($user, $remember);
        $otp->logCode($code);

        $result = $sms->sendOtp($phone, $code);

        if (! $result->successful) {
            // Keep the challenge alive so the code from the log can still be
            // used when the gateway is unavailable or not configured.
            $canReadLog = (bool) config('services.renflair.log_otp', false);

            $message = match (true) {
                $result->reason === RenflairSmsResult::NOT_CONFIGURED && $canReadLog => __('SMS delivery is not configured. Check the application log for your verification code.'),
                $result->reason === RenflairSmsResult::NOT_CONFIGURED => __('Mobile verification is not configured. Please contact your administrator.'),
                $canReadLog => __('We could not send the SMS right now. Check the application log for your verification code.'),
                default => __('We could not send a verification code right now. Please try again in a moment.'),
            };

            if ($result->reason === RenflairSmsResult::NOT_CONFIGURED) {
                Log::warning('Renflair SMS is not configured; the login OTP was not delivered.');
            }

            return redirect()->route('login.otp')->withErrors(['sms' => $message]);
        }

        return redirect()->route('login.otp');
    }
}
