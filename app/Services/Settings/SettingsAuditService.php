<?php

namespace App\Services\Settings;

use App\Models\SettingAuditLog;
use App\Models\SettingVersion;
use App\Models\User;
use App\Support\AuditLogger;

/**
 * Writes the immutable settings audit trail and version snapshots.
 */
final class SettingsAuditService
{
    /**
     * @param  array<string, mixed>  $changes  key => new value
     * @param  array<string, mixed>  $before  key => previous value
     */
    public function record(array $changes, array $before, ?User $actor = null, ?string $reason = null): SettingVersion
    {
        $role = $actor?->role?->value;
        $ip = app()->runningInConsole() ? null : request()->ip();

        foreach ($changes as $key => $value) {
            $meta = SettingsRegistry::meta($key) ?? [];
            $sensitive = (bool) ($meta['sensitive'] ?? false);

            SettingAuditLog::query()->create([
                'setting_key' => $key,
                'old_value' => $this->stringify($before[$key] ?? null, $sensitive),
                'new_value' => $this->stringify($value, $sensitive),
                'is_sensitive' => $sensitive,
                'changed_by' => $actor?->id,
                'user_role' => $role,
                'ip_address' => $ip,
                'reason' => $reason,
            ]);
        }

        $version = $this->nextVersionNumber();

        $record = SettingVersion::query()->create([
            'version' => $version,
            'settings_snapshot' => $this->snapshot(),
            'created_by' => $actor?->id,
            'effective_at' => now(),
        ]);

        if ($actor !== null) {
            AuditLogger::log('settings.updated', $record, [], [
                'version' => $version,
                'keys' => array_keys($changes),
                'reason' => $reason,
                'role' => $role,
            ]);
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        return $settings->snapshot();
    }

    private function nextVersionNumber(): int
    {
        return ((int) SettingVersion::query()->max('version')) + 1;
    }

    private function stringify(mixed $value, bool $sensitive): ?string
    {
        if ($sensitive) {
            return $value === null ? null : '[redacted]';
        }

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value) ?: null;
        }

        return (string) $value;
    }
}
