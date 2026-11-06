<?php

namespace App\Services\Settings;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user preferences (work preferences and notification choices).
 */
final class UserPreferenceService
{
    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'dashboard' => '',
        'date_filter' => 'today',
        'rows_per_page' => 12,
        'reporting_period' => 'current_month',
        'notifications.email_enabled' => true,
        'notifications.in_app_enabled' => true,
        'notifications.loan_updates' => true,
        'notifications.loan_approvals' => true,
        'notifications.document_updates' => true,
        'notifications.deposit_updates' => true,
        'notifications.payment_updates' => true,
        'notifications.repayment_updates' => true,
        'notifications.system_alerts' => true,
    ];

    /**
     * Notification categories that control delivery, mapped to the preference key.
     *
     * @var array<string, string>
     */
    public const NOTIFICATION_CATEGORIES = [
        'loan_updates' => 'notifications.loan_updates',
        'loan_approvals' => 'notifications.loan_approvals',
        'document_updates' => 'notifications.document_updates',
        'deposit_updates' => 'notifications.deposit_updates',
        'payment_updates' => 'notifications.payment_updates',
        'repayment_updates' => 'notifications.repayment_updates',
        'system_alerts' => 'notifications.system_alerts',
    ];

    public function get(User $user, string $key, mixed $default = null): mixed
    {
        $stored = $this->stored($user);

        if (! array_key_exists($key, $stored)) {
            return $default ?? self::DEFAULTS[$key] ?? null;
        }

        $decoded = json_decode((string) $stored[$key], true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $stored[$key];
    }

    public function getBool(User $user, string $key, bool $default = false): bool
    {
        return (bool) ($this->get($user, $key, $default) ?? $default);
    }

    public function getInt(User $user, string $key, int $default = 0): int
    {
        $value = $this->get($user, $key, $default);

        return $value === null ? $default : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(User $user): array
    {
        $values = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->get($user, $key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(User $user, array $values): void
    {
        foreach ($values as $key => $value) {
            UserPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'key' => $key],
                ['value' => json_encode($value)],
            );
        }

        Cache::forget($this->cacheKey($user));
    }

    public function allowsChannel(User $user, string $channel): bool
    {
        return match ($channel) {
            'mail' => $this->getBool($user, 'notifications.email_enabled', true),
            'database' => $this->getBool($user, 'notifications.in_app_enabled', true),
            default => true,
        };
    }

    public function allowsCategory(User $user, string $category): bool
    {
        $key = self::NOTIFICATION_CATEGORIES[$category] ?? null;

        return $key === null || $this->getBool($user, $key, true);
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(User $user): array
    {
        return Cache::rememberForever(
            $this->cacheKey($user),
            fn (): array => UserPreference::query()
                ->where('user_id', $user->id)
                ->pluck('value', 'key')
                ->all(),
        );
    }

    private function cacheKey(User $user): string
    {
        return 'user_preferences.'.$user->id;
    }
}
