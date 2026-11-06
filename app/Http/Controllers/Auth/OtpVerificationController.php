<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Auth\LoginOtpService;
use App\Services\Auth\RenflairSmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    public function show(LoginOtpService $otp): View|RedirectResponse
    {
        if (! $otp->exists()) {
            return $this->restart(__('Please sign in to request a verification code.'));
        }

        if ($otp->isExpired()) {
            $otp->clear();

            return $this->restart(__('Your verification session expired. Please sign in again.'));
        }

        return view('pages::auth.otp-verify', [
            'maskedPhone' => PhoneNumber::mask($otp->phone()),
            'resendIn' => $otp->secondsUntilResend(),
            'expiresIn' => $otp->secondsUntilExpiry(),
            'attemptsLeft' => $otp->remainingAttempts(),
        ]);
    }

    public function verify(
        VerifyOtpRequest $request,
        LoginOtpService $otp,
    ): RedirectResponse {
        if (! $otp->exists()) {
            return $this->restart(__('Please sign in to request a verification code.'));
        }

        if ($otp->isExpired()) {
            $otp->clear();

            return $this->restart(__('Your verification session expired. Please sign in again.'));
        }

        if (! $otp->hasAttemptsLeft()) {
            $otp->clear();

            return $this->restart(__('Too many incorrect codes. Please sign in again.'));
        }

        $user = $otp->user();
        $phone = $otp->phone();

        if ($user === null || $user->status !== 'active' || $phone === null) {
            $otp->clear();

            return $this->restart(__('This account can no longer complete sign-in. Please sign in again.'));
        }

        if (! $otp->verify((string) $request->validated('code'))) {
            $otp->recordFailedAttempt();

            if (! $otp->hasAttemptsLeft()) {
                $otp->clear();

                return $this->restart(__('Too many incorrect codes. Please sign in again.'));
            }

            throw ValidationException::withMessages([
                'code' => __('The verification code is incorrect. Please try again.'),
            ]);
        }

        $remember = $otp->remember();

        $otp->clear();
        $request->session()->forget(['login.id', 'login.remember']);

        // Preserve the existing Fortify two-factor challenge as an additional
        // layer for accounts that have confirmed 2FA enabled.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $remember,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route($user->dashboardRoute());
    }

    public function resend(
        LoginOtpService $otp,
        RenflairSmsService $sms,
    ): RedirectResponse {
        if (! $otp->exists()) {
            return $this->restart(__('Please sign in to request a verification code.'));
        }

        if ($otp->isExpired()) {
            $otp->clear();

            return $this->restart(__('Your verification session expired. Please sign in again.'));
        }

        $wait = $otp->secondsUntilResend();

        if ($wait > 0) {
            throw ValidationException::withMessages([
                'resend' => __('Please wait :seconds seconds before requesting another code.', ['seconds' => $wait]),
            ]);
        }

        $user = $otp->user();
        $phone = $otp->phone();

        if ($user === null || $user->status !== 'active' || $phone === null) {
            $otp->clear();

            return $this->restart(__('This account can no longer complete sign-in. Please sign in again.'));
        }

        // Keep the previous challenge so a gateway failure does not invalidate
        // a code the user may already have received.
        $previous = $otp->snapshot();
        $code = $otp->regenerate();
        $phone = $otp->phone();

        if ($code === null || $phone === null) {
            $otp->restore($previous);

            return $this->restart(__('Please sign in to request a verification code.'));
        }

        $result = $sms->sendOtp($phone, $code);

        if (! $result->successful) {
            $otp->restore($previous);

            throw ValidationException::withMessages([
                'resend' => __('We could not resend a verification code right now. Please try again in a moment.'),
            ]);
        }

        $otp->logCode($code);

        return back()->with('status', __('A new verification code has been sent to your registered mobile number.'));
    }

    public function cancel(LoginOtpService $otp): RedirectResponse
    {
        $otp->clear();

        return redirect()
            ->route('login')
            ->with('status', __('Sign-in cancelled. Enter your credentials to try again.'));
    }

    private function restart(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', $message);
    }
}
