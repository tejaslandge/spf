<?php

namespace App\Services\Security;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Account lockout tracking on top of cache. Fortify's per-minute limiter stays
 * in place; this adds the configurable long-running lockout.
 */
final class LoginAttemptService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function maxAttempts(): int
    {
        return max(1, $this->settings->getInt('security.max_login_attempts', 5));
    }

    public function lockoutMinutes(): int
    {
        return max(1, $this->settings->getInt('security.login_lockout_minutes', 15));
    }

    public function isLocked(string $identifier, string $ip): bool
    {
        return Cache::has($this->lockKey($identifier, $ip));
    }

    public function lockedUntil(string $identifier, string $ip): ?Carbon
    {
        $value = Cache::get($this->lockKey($identifier, $ip));

        return $value === null ? null : Carbon::parse($value);
    }

    public function remainingAttempts(string $identifier, string $ip): int
    {
        $attempts = (int) Cache::get($this->attemptKey($identifier, $ip), 0);

        return max(0, $this->maxAttempts() - $attempts);
    }

    public function recordFailure(string $identifier, string $ip): void
    {
        $key = $this->attemptKey($identifier, $ip);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes($this->lockoutMinutes()));

        if ($attempts >= $this->maxAttempts()) {
            Cache::put($this->lockKey($identifier, $ip), now()->addMinutes($this->lockoutMinutes())->toIso8601String(), now()->addMinutes($this->lockoutMinutes()));
            Cache::forget($key);
        }
    }

    public function clear(string $identifier, string $ip): void
    {
        Cache::forget($this->attemptKey($identifier, $ip));
        Cache::forget($this->lockKey($identifier, $ip));
    }

    private function attemptKey(string $identifier, string $ip): string
    {
        return 'login_attempts.'.sha1(mb_strtolower($identifier).'|'.$ip);
    }

    private function lockKey(string $identifier, string $ip): string
    {
        return 'login_lock.'.sha1(mb_strtolower($identifier).'|'.$ip);
    }
}
