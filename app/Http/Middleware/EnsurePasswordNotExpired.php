<?php

namespace App\Http\Middleware;

use App\Services\Security\PasswordPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    public function __construct(private readonly PasswordPolicyService $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->hasExpired($user) || $this->isExempt($request)) {
            return $next($request);
        }

        return redirect()
            ->route('security.edit')
            ->with('status', 'password-expired');
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
