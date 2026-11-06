<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record for a settings change. Rows are only ever inserted.
 *
 * @property int $id
 * @property string $setting_key
 * @property string|null $old_value
 * @property string|null $new_value
 * @property bool $is_sensitive
 * @property int|null $changed_by
 * @property string|null $user_role
 * @property string|null $ip_address
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $created_at
 */
#[Fillable([
    'setting_key',
    'old_value',
    'new_value',
    'is_sensitive',
    'changed_by',
    'user_role',
    'ip_address',
    'reason',
])]
class SettingAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'settings_audit_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
