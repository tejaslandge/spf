<?php

namespace App\Models;

use App\Enums\DailyTaskPriority;
use App\Enums\DailyTaskStatus;
use App\Enums\DailyTaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'task_date',
    'type',
    'title',
    'description',
    'status',
    'priority',
    'related_type',
    'related_id',
    'assigned_to',
    'created_by',
    'completed_by',
    'completed_at',
    'auto_generated',
    'dedupe_key',
])]
class DailyTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'type' => DailyTaskType::class,
            'status' => DailyTaskStatus::class,
            'priority' => DailyTaskPriority::class,
            'auto_generated' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDone(): bool
    {
        return $this->status === DailyTaskStatus::Done;
    }

    public function scopeForDate(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('task_date', $date);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DailyTaskStatus::Pending);
    }

    public function scopeOfType(Builder $query, DailyTaskType|string $type): Builder
    {
        return $query->where('type', $type instanceof DailyTaskType ? $type->value : $type);
    }
}
