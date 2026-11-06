<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            AuditLogger::log('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            AuditLogger::log('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model): void {
            AuditLogger::log('deleted', $model, $model->getAttributes(), []);
        });
    }
}
