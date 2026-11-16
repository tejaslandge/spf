<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Services\Auth\WhatsAppOtpResult;
use App\Services\Auth\WhatsAppOtpService;
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
        $config = require base_path('waapi.php');

        return view('pages::auth.login', [
            'defaultCountryCode' => (string) ($config['default_country_code'] ?? '91'),
        ]);
    }

    /**
     * Start a pending OTP challenge for the dynamically entered mobile number.
     */
    public function login(
        LoginRequest $request,
        WhatsAppOtpService $whatsapp,
        LoginOtpService $otp,
        LoginAttemptService $attempts,
    ): RedirectResponse {
        $waapi = $whatsapp->config();
        $countryCode = preg_replace('/\D+/', '', (string) ($request->input('country_code') ?: $waapi['default_country_code'])) ?: $waapi['default_country_code'];
        $input = trim((string) $request->input('mobile'));
        $phone = PhoneNumber::forWhatsAppApi(($countryCode !== '' ? $countryCode : '').$input, $waapi['default_country_code'])
            ?? PhoneNumber::forWhatsAppApi($input, $waapi['default_country_code']);
        $remember = $request->boolean('remember');

        if ($phone === null) {
            throw ValidationException::withMessages([
                'mobile' => __('Please enter a valid mobile number.'),
            ]);
        }

        $identifier = $phone;

        if ($attempts->isLocked($identifier, $request->ip())) {
            $lockedUntil = $attempts->lockedUntil($identifier, $request->ip());
            $minutes = $lockedUntil !== null
                ? max(1, (int) ceil(now()->diffInMinutes($lockedUntil, true)))
                : $attempts->lockoutMinutes();

            throw ValidationException::withMessages([
                'mobile' => __('Too many failed sign-in attempts. Your account is locked for :minutes more minute(s).', ['minutes' => $minutes]),
            ]);
        }

        $user = User::query()
            ->where('status', 'active')
            ->whereIn('mobile', PhoneNumber::equivalentValues($phone, $waapi['default_country_code']))
            ->first();

        if (! $user) {
            $attempts->recordFailure($identifier, $request->ip());

            throw ValidationException::withMessages([
                'mobile' => __('auth.failed'),
            ]);
        }

        $attempts->clear($identifier, $request->ip());

        $code = $otp->start($user, $remember, $phone);
        $otp->logCode($code);

        $result = $whatsapp->sendOtp($phone, $code);

        if (! $result->successful) {
            $otp->clear();

            if ($result->reason === WhatsAppOtpResult::UNAUTHORIZED) {
                throw ValidationException::withMessages([
                    'mobile' => $result->message ?: __('This number is not authorized to receive OTPs.'),
                ]);
            }

            $message = match ($result->reason) {
                WhatsAppOtpResult::NOT_CONFIGURED => __('Mobile verification is not configured. Please contact your administrator.'),
                default => $result->message ?: __('We could not send a verification code right now. Please try again in a moment.'),
            };

            if ($result->reason === WhatsAppOtpResult::NOT_CONFIGURED) {
                Log::warning('WhatsApp OTP API is not configured; the login OTP was not delivered.');
            }

            return redirect()->route('login')->withInput($request->only('country_code', 'mobile', 'remember'))->withErrors(['mobile' => $message]);
        }

        return redirect()->route('login.otp')->with('status', __('A verification code has been sent to your WhatsApp number.'));
    }
}
