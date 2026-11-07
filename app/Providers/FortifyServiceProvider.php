<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower(trim((string) $request->input('mobile'))).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by('otp-verify|'.$request->session()->getId().'|'.$request->ip());
        });

        RateLimiter::for('otp-resend', function (Request $request) {
            return Limit::perMinute(3)->by('otp-resend|'.$request->session()->getId().'|'.$request->ip());
        });
    }
}
