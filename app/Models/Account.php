<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'is_system', 'is_active'])]
class Account extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function balance(): string
    {
        $debit = (string) $this->lines()->sum('debit');
        $credit = (string) $this->lines()->sum('credit');

        return $this->type->normalBalance() === 'debit'
            ? bcsub($debit, $credit, 2)
            : bcsub($credit, $debit, 2);
    }
}
