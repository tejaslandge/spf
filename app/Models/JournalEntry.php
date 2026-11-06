<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['entry_no', 'entry_date', 'description', 'reference_type', 'reference_id', 'created_by'])]
class JournalEntry extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::creating(function (JournalEntry $entry): void {
            $entry->entry_no ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::max('id') ?? 0) + 1;

        return 'JE-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBalanced(): bool
    {
        return bccomp((string) $this->lines()->sum('debit'), (string) $this->lines()->sum('credit'), 2) === 0;
    }
}
