<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->two_factor_confirmed_at !== null || ! $this->isRequired($user->role)) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        return redirect()
            ->route('security.edit')
            ->with('status', 'two-factor-required');
    }

    private function isRequired(?UserRole $role): bool
    {
        $enabled = $this->settings->getBool('security.two_factor_enabled', true);

        if (! $enabled) {
            return false;
        }

        return match ($role) {
            UserRole::SuperAdmin => $this->settings->getBool('security.two_factor_required_super_admin', false),
            UserRole::Admin => $this->settings->getBool('security.two_factor_required_admin', false),
            default => false,
        };
    }

    private function isExempt(Request $request): bool
    {
        return $request->routeIs([
            'security.edit',
            'security.password.update',
            'password.confirm',
            'logout',
            'two-factor.*',
            'livewire.update',
            '.well-known.*',
        ]);
    }
}
