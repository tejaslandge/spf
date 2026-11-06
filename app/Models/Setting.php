<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

#[Fillable(['key', 'value', 'type', 'group', 'description', 'is_sensitive', 'updated_by'])]
class Setting extends Model
{
    private const MISSING = ['__missing' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'setting.'.$key;

        // Cache plain attributes only; serializing Eloquent models breaks
        // across PHP/OPCache changes and leaks stale objects.
        try {
            $attributes = Cache::get($cacheKey);
        } catch (Throwable) {
            // A corrupt or stale cache entry must never break setting reads.
            Cache::forget($cacheKey);
            $attributes = null;
        }

        if ($attributes === null) {
            $setting = static::query()->where('key', $key)->first();
            $attributes = $setting?->getAttributes() ?? self::MISSING;

            Cache::forever($cacheKey, $attributes);
        }

        if (! is_array($attributes) || ($attributes['__missing'] ?? false) === true) {
            return $default;
        }

        $value = $attributes['value'] ?? null;

        if (($attributes['is_sensitive'] ?? false) && $value !== null) {
            try {
                $value = Crypt::decryptString((string) $value);
            } catch (Throwable) {
                // Legacy plaintext values remain readable until rewritten.
            }
        }

        return match ($attributes['type'] ?? 'string') {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    public static function put(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        ?string $description = null,
        bool $isSensitive = false,
        ?int $updatedBy = null,
    ): void {
        $stored = is_array($value) ? json_encode($value) : ($value === null ? null : (string) $value);

        if ($isSensitive && $stored !== null) {
            $stored = Crypt::encryptString((string) $stored);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_sensitive' => $isSensitive,
                'updated_by' => $updatedBy,
            ],
        );

        Cache::forget('setting.'.$key);
    }

    public static function forget(string $key): void
    {
        Cache::forget('setting.'.$key);
    }

    public static function forgetAll(): void
    {
        foreach (static::query()->pluck('key') as $key) {
            Cache::forget('setting.'.$key);
        }
    }
}
