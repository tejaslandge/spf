<?php

namespace App\Services;

use App\Enums\IdentityDocumentType;
use App\Enums\LoanStatus;
use App\Enums\RelationshipType;
use App\Models\Borrower;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class GuarantorService
{
    /**
     * @return array<string, mixed>
     */
    public static function blankRow(): array
    {
        return [
            'id' => null,
            'full_name' => '',
            'mobile_number' => '',
            'alternate_mobile' => '',
            'email' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'pincode' => '',
            'relationship' => '',
            'custom_relationship' => '',
            'occupation' => '',
            'identity_document_type' => '',
            'identity_document_number' => '',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = '', bool $includeRelationship = true): array
    {
        $path = $prefix === '' ? '' : rtrim($prefix, '.').'.';

        $rules = [
            $path.'full_name' => ['required', 'string', 'min:2', 'max:255'],
            $path.'mobile_number' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            $path.'alternate_mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/', 'different:'.$path.'mobile_number'],
            $path.'email' => ['nullable', 'email', 'max:255'],
            $path.'address' => ['required', 'string', 'min:5', 'max:1000'],
            $path.'city' => ['required', 'string', 'min:2', 'max:100'],
            $path.'state' => ['required', 'string', 'min:2', 'max:100'],
            $path.'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
            $path.'occupation' => ['nullable', 'string', 'max:100'],
            $path.'identity_document_type' => [
                'nullable',
                Rule::enum(IdentityDocumentType::class),
                'required_with:'.$path.'identity_document_number',
            ],
            $path.'identity_document_number' => [
                'nullable',
                'string',
                'max:50',
                'required_with:'.$path.'identity_document_type',
            ],
        ];

        if ($includeRelationship) {
            $rules[$path.'relationship'] = ['required', Rule::enum(RelationshipType::class)];
            $rules[$path.'custom_relationship'] = [
                'nullable',
                'string',
                'max:100',
                'required_if:'.$path.'relationship,'.RelationshipType::Other->value,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function relationshipRules(string $prefix = ''): array
    {
        $path = $prefix === '' ? '' : rtrim($prefix, '.').'.';

        return [
            $path.'relationship' => ['required', Rule::enum(RelationshipType::class)],
            $path.'custom_relationship' => [
                'nullable',
                'string',
                'max:100',
                'required_if:'.$path.'relationship,'.RelationshipType::Other->value,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{relationship: ?string, custom_relationship: ?string}
     */
    public static function pivotRelationship(array $row): array
    {
        $row = self::normalize($row);
        $relationship = $row['relationship'] ?? null;

        return [
            'relationship' => $relationship,
            'custom_relationship' => $relationship === RelationshipType::Other->value
                ? ($row['custom_relationship'] ?? null)
                : null,
        ];
    }

    public static function pivotRelationshipLabel(mixed $relationship, mixed $customRelationship = null): string
    {
        $value = $relationship instanceof RelationshipType ? $relationship->value : $relationship;

        if ($value === RelationshipType::Other->value) {
            return filled($customRelationship) ? (string) $customRelationship : RelationshipType::Other->label();
        }

        return RelationshipType::tryFrom((string) $value)?->label() ?? '—';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalize(array $row): array
    {
        $row['full_name'] = trim((string) ($row['full_name'] ?? ''));
        $row['mobile_number'] = preg_replace('/\D/', '', (string) ($row['mobile_number'] ?? '')) ?? '';
        $row['alternate_mobile'] = preg_replace('/\D/', '', (string) ($row['alternate_mobile'] ?? '')) ?? '';
        $row['email'] = strtolower(trim((string) ($row['email'] ?? '')));
        $row['address'] = trim((string) ($row['address'] ?? ''));
        $row['city'] = trim((string) ($row['city'] ?? ''));
        $row['state'] = trim((string) ($row['state'] ?? ''));
        $row['pincode'] = preg_replace('/\D/', '', (string) ($row['pincode'] ?? '')) ?? '';
        $row['occupation'] = trim((string) ($row['occupation'] ?? ''));
        $row['identity_document_number'] = trim((string) ($row['identity_document_number'] ?? ''));

        if (($row['relationship'] ?? null) === RelationshipType::Other->value) {
            $row['custom_relationship'] = trim((string) ($row['custom_relationship'] ?? ''));
        } elseif (array_key_exists('relationship', $row) && filled($row['relationship'])) {
            $row['custom_relationship'] = null;
        }

        foreach (['alternate_mobile', 'email', 'custom_relationship', 'occupation', 'identity_document_type', 'identity_document_number', 'relationship', 'city', 'state', 'pincode', 'borrower_id'] as $field) {
            if (($row[$field] ?? null) === '') {
                $row[$field] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function hasAnyValue(array $row): bool
    {
        foreach ($row as $field => $value) {
            if ($field !== 'id' && filled($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Guarantor>
     */
    public function forLoan(Loan $loan): Collection
    {
        return $loan->guarantors()->orderBy('guarantors.id')->get();
    }

    public function findByMobileNumber(string $mobileNumber): ?Guarantor
    {
        $mobileNumber = preg_replace('/\D/', '', $mobileNumber) ?? '';

        if ($mobileNumber === '') {
            return null;
        }

        return Guarantor::query()->where('mobile_number', $mobileNumber)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStandalone(array $data, ?User $actor = null): Guarantor
    {
        $data = self::normalize($data);
        $mobile = (string) ($data['mobile_number'] ?? '');
        $existing = $this->findByMobileNumber($mobile);
        $borrower = $mobile !== '' ? Borrower::query()->where('mobile', $mobile)->first() : null;

        if ($borrower !== null) {
            return $this->syncBorrowerGuarantor($borrower, true, $actor) ?? Guarantor::query()->where('borrower_id', $borrower->getKey())->firstOrFail();
        }

        if ($existing !== null) {
            if ($existing->borrower_id !== null) {
                return $existing;
            }

            return $this->update($existing, $data, $actor);
        }

        return Guarantor::query()->create([
            ...$this->attributes($data),
            'created_by' => $actor?->getKey() ?? auth()->id(),
            'updated_by' => $actor?->getKey() ?? auth()->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForLoan(Loan $loan, array $data, ?User $actor = null): Guarantor
    {
        return DB::transaction(function () use ($loan, $data, $actor): Guarantor {
            $data = self::normalize($data);
            $pivot = self::pivotRelationship($data);
            $mobile = (string) ($data['mobile_number'] ?? '');
            $borrower = $mobile !== '' ? Borrower::query()->where('mobile', $mobile)->first() : null;

            if ($borrower !== null) {
                $guarantor = $this->syncBorrowerGuarantor($borrower, true, $actor) ?? Guarantor::query()->where('borrower_id', $borrower->getKey())->firstOrFail();
            } else {
                $existing = $this->findByMobileNumber($mobile);

                if ($existing !== null && $existing->borrower_id !== null) {
                    $guarantor = $existing;
                } elseif ($existing !== null) {
                    $guarantor = $this->update($existing, $data, $actor);
                } else {
                    $guarantor = Guarantor::query()->create([
                        ...$this->attributes($data),
                        'created_by' => $actor?->getKey() ?? auth()->id(),
                        'updated_by' => $actor?->getKey() ?? auth()->id(),
                    ]);
                }
            }

            $loan->guarantors()->syncWithoutDetaching([
                $guarantor->getKey() => $pivot,
            ]);
            $loan->guarantors()->updateExistingPivot($guarantor->getKey(), $pivot);

            return $guarantor;
        });
    }

    /**
     * @param  array<int, array{guarantor_id:int, relationship?:?string, custom_relationship?:?string}>  $selections
     */
    public function attachSelectionsToLoan(Loan $loan, array $selections): void
    {
        $sync = [];

        foreach ($selections as $selection) {
            $guarantorId = (int) ($selection['guarantor_id'] ?? $selection['id'] ?? 0);
            if ($guarantorId < 1) {
                continue;
            }

            $sync[$guarantorId] = self::pivotRelationship($selection);
        }

        $loan->guarantors()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Guarantor $guarantor, array $data, ?User $actor = null): Guarantor
    {
        $guarantor->fill($this->attributes($data));
        $guarantor->updated_by = $actor?->getKey() ?? auth()->id();
        $guarantor->save();

        return $guarantor->refresh();
    }

    /**
     * Replace the loan's guarantors with the submitted rows. Existing rows are
     * updated in place, new rows are attached, and removed rows are detached.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, Guarantor>
     */
    public function syncForLoan(Loan $loan, array $rows, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($loan, $rows, $actor): Collection {
            $existing = $loan->guarantors()->get()->keyBy('id');
            $keptIds = [];
            $saved = new Collection;

            foreach ($rows as $row) {
                $row = self::normalize($row);

                if (! self::hasAnyValue($row)) {
                    continue;
                }

                $id = filled($row['id'] ?? null) ? (int) $row['id'] : null;

                if ($id !== null) {
                    /** @var Guarantor|null $guarantor */
                    $guarantor = $existing->get($id);

                    if ($guarantor === null) {
                        throw new InvalidArgumentException('The guarantor does not belong to this loan.');
                    }

                    $saved->push($this->update($guarantor, $row, $actor));
                } else {
                    $saved->push($this->createForLoan($loan, $row, $actor));
                }

                $keptIds[] = $saved->last()->getKey();
            }

            foreach ($existing as $guarantor) {
                if (! in_array($guarantor->getKey(), $keptIds, true)) {
                    $this->removeFromLoan($loan, $guarantor);
                }
            }

            return $saved;
        });
    }

    public function removeFromLoan(Loan $loan, Guarantor $guarantor): void
    {
        if ($loan->guarantors()->count() <= 1) {
            throw new InvalidArgumentException(__('At least one guarantor is required for every loan.'));
        }

        $loan->guarantors()->detach($guarantor->getKey());

        if ($guarantor->loans()->count() === 0 && $guarantor->borrower_id === null) {
            $guarantor->delete();
        }
    }

    public function syncBorrowerGuarantor(Borrower $borrower, bool $enabled, ?User $actor = null): ?Guarantor
    {
        $guarantor = Guarantor::withTrashed()
            ->where('borrower_id', $borrower->getKey())
            ->first();

        if ($guarantor === null) {
            $guarantor = Guarantor::withTrashed()
                ->where('mobile_number', $borrower->mobile)
                ->where(fn ($query) => $query->whereNull('borrower_id')->orWhere('borrower_id', $borrower->getKey()))
                ->first();
        }

        if (! $enabled) {
            if ($guarantor === null || (int) $guarantor->borrower_id !== (int) $borrower->getKey()) {
                return $guarantor?->trashed() ? null : $guarantor;
            }

            $hasActiveLoan = $guarantor->loans()
                ->where('loans.status', LoanStatus::Active->value)
                ->exists();

            if ($hasActiveLoan || $guarantor->loans()->exists()) {
                $guarantor->forceFill([
                    'borrower_id' => null,
                    'updated_by' => $actor?->getKey() ?? auth()->id(),
                ])->save();

                return $guarantor->refresh();
            }

            $guarantor->forceFill([
                'borrower_id' => null,
                'updated_by' => $actor?->getKey() ?? auth()->id(),
            ])->save();
            $guarantor->delete();

            return null;
        }

        $attributes = [
            'borrower_id' => $borrower->getKey(),
            'full_name' => $borrower->full_name,
            'mobile_number' => $borrower->mobile,
            'alternate_mobile' => filled($borrower->alternate_mobile) ? $borrower->alternate_mobile : null,
            'email' => $guarantor?->email,
            'address' => $borrower->address,
            'city' => $guarantor?->city ?: (filled($borrower->state) ? $borrower->state : 'Not specified'),
            'state' => filled($borrower->state) ? $borrower->state : $guarantor?->state,
            'pincode' => filled($borrower->pincode) ? preg_replace('/\D/', '', (string) $borrower->pincode) : $guarantor?->pincode,
            'occupation' => filled($borrower->occupation) ? $borrower->occupation : $guarantor?->occupation,
            'identity_document_type' => $guarantor?->identity_document_type?->value,
            'identity_document_number' => $guarantor?->identity_document_number,
            'updated_by' => $actor?->getKey() ?? auth()->id(),
        ];

        if ($guarantor !== null) {
            if ($guarantor->trashed()) {
                $guarantor->restore();
            }

            $guarantor->forceFill($attributes)->save();

            return $guarantor->refresh();
        }

        return Guarantor::query()->create([
            ...$attributes,
            'created_by' => $actor?->getKey() ?? auth()->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return array_filter([
            'borrower_id' => $data['borrower_id'] ?? null,
            'full_name' => $data['full_name'],
            'mobile_number' => $data['mobile_number'],
            'alternate_mobile' => $data['alternate_mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'identity_document_type' => $data['identity_document_type'] ?? null,
            'identity_document_number' => $data['identity_document_number'] ?? null,
        ], static fn ($value, string $key): bool => $key === 'borrower_id' ? true : $value !== null || in_array($key, ['alternate_mobile', 'email', 'occupation', 'identity_document_type', 'identity_document_number', 'city', 'state', 'pincode'], true), ARRAY_FILTER_USE_BOTH);
    }
}
