<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function log(string $action, Model $model, array $oldValues = [], array $newValues = []): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        try {
            if (! app(SettingsService::class)->getBool('security.audit_logging_enabled', true)) {
                return;
            }
        } catch (Throwable) {
            // Fall through when the settings table is not available.
        }

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => Arr::only($oldValues, array_keys($newValues)) ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
