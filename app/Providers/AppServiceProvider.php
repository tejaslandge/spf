<?php

namespace App\Providers;

use App\Models\Loan;
use App\Policies\LoanDistributionPolicy;
use App\Services\Security\SessionService;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->applyStoredSettings();

        Event::listen(function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            app(SessionService::class)->enforceSingleSessionPolicy($event->user);
        });

        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);
        Gate::policy(Loan::class, LoanDistributionPolicy::class);
        Gate::policy(ForeclosureRequest::class, ForeclosurePolicy::class);
    }

    /**
     * Apply database-driven branding, timezone, currency and session policy
     * before the request lifecycle begins. Guarded so fresh installs and
     * migrations (where the settings table may not exist yet) keep working.
     */
    protected function applyStoredSettings(): void
    {
        try {
            $settings = app(SettingsService::class);

            $name = $settings->get('company.name');

            if (is_string($name) && $name !== '') {
                config(['app.name' => $name]);
            }

            $timezone = $settings->get('company.timezone');

            if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }

            config([
                'finance.currency.code' => $settings->get('company.currency_code') ?: config('finance.currency.code'),
                'finance.currency.symbol' => $settings->get('company.currency_symbol') ?: config('finance.currency.symbol'),
            ]);

            $timeout = $settings->getInt('security.session_timeout_minutes', 0);

            if ($timeout > 0) {
                config(['session.lifetime' => $timeout]);
            }
        } catch (Throwable) {
            // The settings table is not available yet (fresh install, migrate).
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
