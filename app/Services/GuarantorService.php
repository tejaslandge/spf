<?php

namespace App\Services;

use App\Enums\IdentityDocumentType;
use App\Enums\RelationshipType;
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
    public static function rules(string $prefix = ''): array
    {
        $path = $prefix === '' ? '' : rtrim($prefix, '.').'.';

        return [
            $path.'full_name' => ['required', 'string', 'min:2', 'max:255'],
            $path.'mobile_number' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            $path.'alternate_mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/', 'different:'.$path.'mobile_number'],
            $path.'email' => ['nullable', 'email', 'max:255'],
            $path.'address' => ['required', 'string', 'min:5', 'max:1000'],
            $path.'city' => ['required', 'string', 'min:2', 'max:100'],
            $path.'state' => ['required', 'string', 'min:2', 'max:100'],
            $path.'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
            $path.'relationship' => ['required', Rule::enum(RelationshipType::class)],
            $path.'custom_relationship' => [
                'nullable',
                'string',
                'max:100',
                'required_if:'.$path.'relationship,'.RelationshipType::Other->value,
            ],
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
        } else {
            $row['custom_relationship'] = null;
        }

        foreach (['alternate_mobile', 'email', 'custom_relationship', 'occupation', 'identity_document_type', 'identity_document_number'] as $field) {
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForLoan(Loan $loan, array $data, ?User $actor = null): Guarantor
    {
        return DB::transaction(function () use ($loan, $data, $actor): Guarantor {
            $guarantor = Guarantor::query()->create([
                ...$this->attributes($data),
                'created_by' => $actor?->getKey() ?? auth()->id(),
                'updated_by' => $actor?->getKey() ?? auth()->id(),
            ]);

            $loan->guarantors()->syncWithoutDetaching([$guarantor->getKey()]);

            return $guarantor;
        });
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
        $loan->guarantors()->detach($guarantor->getKey());

        if ($guarantor->loans()->count() === 0) {
            $guarantor->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $relationship = $data['relationship'] ?? null;
        $relationship = $relationship instanceof RelationshipType ? $relationship->value : $relationship;

        return [
            'full_name' => $data['full_name'],
            'mobile_number' => $data['mobile_number'],
            'alternate_mobile' => $data['alternate_mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'relationship' => $relationship,
            'custom_relationship' => $relationship === RelationshipType::Other->value
                ? ($data['custom_relationship'] ?? null)
                : null,
            'occupation' => $data['occupation'] ?? null,
            'identity_document_type' => $data['identity_document_type'] ?? null,
            'identity_document_number' => $data['identity_document_number'] ?? null,
        ];
    }
}
