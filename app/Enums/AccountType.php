<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function normalBalance(): string
    {
        return in_array($this, [self::Asset, self::Expense], true) ? 'debit' : 'credit';
    }
}
