<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\SettingVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SettingsService
{
    private const UNCONFIGURED = '__unconfigured__';

    public function __construct(private readonly SettingsAuditService $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $meta = SettingsRegistry::meta($key);

        return Setting::get($key, $default ?? $meta['default'] ?? null);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) ($this->get($key, $default) ?? $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return $value === null ? $default : (int) $value;
    }

    /**
     * Return a decimal as a numeric string so financial code never sees a float.
     */
    public function getDecimal(string $key, string $default = '0'): string
    {
        $value = $this->get($key, $default);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $values = [];

        foreach (SettingsRegistry::keysForGroup($group) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * Whether an explicit value has ever been saved for the key.
     */
    public function isConfigured(string $key): bool
    {
        return Setting::get($key, self::UNCONFIGURED) !== self::UNCONFIGURED;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach (array_keys(SettingsRegistry::keys()) as $key) {
            $meta = SettingsRegistry::meta($key);
            $value = $this->get($key);

            $snapshot[$key] = ($meta['sensitive'] ?? false) ? '[redacted]' : $value;
        }

        return $snapshot;
    }

    public function put(string $key, mixed $value, ?User $actor = null, ?string $reason = null): ?SettingVersion
    {
        return $this->putMany([$key => $value], $actor, $reason);
    }

    /**
     * Persist a batch of settings atomically, recording per-key audit rows and
     * a version snapshot. Returns null when nothing actually changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, ?User $actor = null, ?string $reason = null): ?SettingVersion
    {
        return DB::transaction(function () use ($values, $actor, $reason): ?SettingVersion {
            $before = [];
            $changed = [];

            foreach ($values as $key => $value) {
                $current = $this->get($key);

                $before[$key] = $current;

                if ($this->normalize($current) !== $this->normalize($value)) {
                    $changed[$key] = $value;
                }
            }

            if ($changed === []) {
                return null;
            }

            foreach ($changed as $key => $value) {
                $meta = SettingsRegistry::meta($key) ?? ['type' => 'string', 'group' => 'general', 'label' => null, 'sensitive' => false];

                Setting::put(
                    $key,
                    $value,
                    $meta['type'],
                    $meta['group'],
                    $meta['label'] ?? null,
                    $meta['sensitive'] ?? false,
                    $actor?->id,
                );
            }

            return $this->audit->record($changed, $before, $actor, $reason);
        });
    }

    public function versionCount(): int
    {
        return SettingVersion::query()->count();
    }

    public function latestVersion(): ?SettingVersion
    {
        return SettingVersion::query()->orderByDesc('version')->first();
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return (string) $value;
    }
}
