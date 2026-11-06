<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    /**
     * @param  array<int, array{code: string, debit?: string, credit?: string, narration?: string}>  $lines
     */
    public function post(
        CarbonImmutable|string $date,
        string $description,
        array $lines,
        ?Model $reference = null,
        ?int $userId = null,
    ): JournalEntry {
        $mapped = collect($lines)->map(function (array $line): array {
            $account = Account::query()->where('code', $line['code'])->firstOrFail();

            return [
                'account_id' => $account->id,
                'debit' => Money::round($line['debit'] ?? 0),
                'credit' => Money::round($line['credit'] ?? 0),
                'narration' => $line['narration'] ?? null,
            ];
        });

        $debits = $mapped->reduce(fn (string $carry, array $line) => Money::add($carry, $line['debit']), '0');
        $credits = $mapped->reduce(fn (string $carry, array $line) => Money::add($carry, $line['credit']), '0');

        if (Money::compare($debits, $credits) !== 0) {
            throw new InvalidArgumentException('Journal entry is not balanced.');
        }

        return DB::transaction(function () use ($date, $description, $mapped, $reference, $userId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_date' => $date,
                'description' => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => $userId ?? auth()->id(),
            ]);

            $entry->lines()->createMany($mapped->all());

            return $entry->load('lines.account');
        });
    }
}
