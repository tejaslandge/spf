<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Services\Security\LoginAttemptService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = (string) $request->input(Fortify::username());
            $attempts = app(LoginAttemptService::class);

            if ($attempts->isLocked($login, $request->ip())) {
                $lockedUntil = $attempts->lockedUntil($login, $request->ip());
                $minutes = $lockedUntil !== null ? max(1, (int) ceil(now()->diffInMinutes($lockedUntil, true))) : $attempts->lockoutMinutes();

                throw ValidationException::withMessages([
                    Fortify::username() => __('Too many failed sign-in attempts. Your account is locked for :minutes more minute(s).', ['minutes' => $minutes]),
                ]);
            }

            $user = User::query()
                ->where('email', $login)
                ->orWhere('mobile', $login)
                ->first();

            if (! $user || $user->status !== 'active' || ! Hash::check((string) $request->input('password'), $user->password)) {
                $attempts->recordFailure($login, $request->ip());

                return null;
            }

            $attempts->clear($login, $request->ip());

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('password-update', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(5)->by('password-update|'.$key);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by('otp-verify|'.$request->session()->getId().'|'.$request->ip());
        });

        RateLimiter::for('otp-resend', function (Request $request) {
            return Limit::perMinute(3)->by('otp-resend|'.$request->session()->getId().'|'.$request->ip());
        });
    }
}
