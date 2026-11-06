<?php

namespace App\Models;

use App\Enums\BorrowerStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'borrower_code',
    'full_name',
    'address',
    'mobile',
    'alternate_mobile',
    'emergency_contact_name',
    'emergency_contact_number',
    'bank_name',
    'bank_account_number',
    'bank_ifsc',
    'status',
    'notes',
    'created_by',
])]
class Borrower extends Model
{
    /** @use HasFactory<BorrowerFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Borrower $borrower): void {
            $borrower->borrower_code ??= static::nextCode();
        });
    }

    public static function nextCode(): string
    {
        $next = (static::withTrashed()->max('id') ?? 0) + 1;

        return 'BR-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BorrowerStatus::class,
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function kycDocuments(): MorphMany
    {
        return $this->morphMany(KycDocument::class, 'documentable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.strtolower($term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->whereRaw('LOWER(full_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(mobile) LIKE ?', [$like])
                ->orWhereRaw('LOWER(borrower_code) LIKE ?', [$like]);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BorrowerStatus::Active);
    }

    public function totalOutstanding(): string
    {
        return (string) $this->loans()->sum('outstanding_principal');
    }
}
