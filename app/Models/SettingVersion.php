<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A point-in-time snapshot of every application setting.
 *
 * @property int $id
 * @property int $version
 * @property array<string, mixed> $settings_snapshot
 * @property int|null $created_by
 * @property Carbon|null $effective_at
 * @property Carbon|null $created_at
 */
#[Fillable(['version', 'settings_snapshot', 'created_by', 'effective_at'])]
class SettingVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'settings_versions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_snapshot' => 'array',
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changesAgainst(?SettingVersion $previous): array
    {
        if ($previous === null) {
            return [];
        }

        $changes = [];

        foreach ($this->settings_snapshot as $key => $value) {
            $old = $previous->settings_snapshot[$key] ?? null;

            if ($old !== $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        return $changes;
    }
}
