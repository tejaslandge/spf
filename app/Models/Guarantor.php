<?php

namespace App\Models;

use App\Enums\IdentityDocumentType;
use App\Enums\RelationshipType;
use App\Models\Concerns\Auditable;
use Database\Factories\GuarantorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'full_name',
    'address',
    'mobile_number',
    'alternate_mobile',
    'email',
    'city',
    'state',
    'pincode',
    'relationship',
    'custom_relationship',
    'occupation',
    'identity_document_type',
    'identity_document_number',
    'notes',
    'created_by',
    'updated_by',
])]
#[Hidden(['identity_document_number'])]
class Guarantor extends Model
{
    /** @use HasFactory<GuarantorFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => RelationshipType::class,
            'identity_document_type' => IdentityDocumentType::class,
            'identity_document_number' => 'encrypted',
        ];
    }

    public function loans(): BelongsToMany
    {
        return $this->belongsToMany(Loan::class, 'loan_guarantors')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.strtolower($term).'%';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(full_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(mobile_number) LIKE ?', [$like]));
    }

    public function relationshipLabel(): string
    {
        if ($this->relationship === RelationshipType::Other) {
            return $this->custom_relationship ?: RelationshipType::Other->label();
        }

        return $this->relationship?->label() ?? '—';
    }

    public function maskedIdentityDocumentNumber(): ?string
    {
        $number = trim((string) $this->identity_document_number);

        if ($number === '') {
            return null;
        }

        $characters = preg_replace('/[^A-Za-z0-9]/', '', $number) ?? '';
        $visible = substr($characters, -4);

        return $visible === '' ? '••••' : '•••• '.$visible;
    }
}
