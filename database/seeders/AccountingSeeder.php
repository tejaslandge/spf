<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash & Bank', 'type' => AccountType::Asset, 'is_system' => true],
            ['code' => '1100', 'name' => 'Loans Receivable', 'type' => AccountType::Asset, 'is_system' => true],
            ['code' => '2000', 'name' => 'Investor Capital', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2100', 'name' => 'TDS Payable', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '3000', 'name' => "Owner's Equity", 'type' => AccountType::Equity, 'is_system' => true],
            ['code' => '4000', 'name' => 'Interest Income', 'type' => AccountType::Income, 'is_system' => true],
            ['code' => '4100', 'name' => 'Penalty Income', 'type' => AccountType::Income, 'is_system' => true],
            ['code' => '4200', 'name' => 'Management Fee Income', 'type' => AccountType::Income, 'is_system' => true],
            ['code' => '4400', 'name' => 'Processing Fee Income', 'type' => AccountType::Income, 'is_system' => true],
            ['code' => '5000', 'name' => 'Interest Expense', 'type' => AccountType::Expense, 'is_system' => true],
            ['code' => '5100', 'name' => 'Operating Expenses', 'type' => AccountType::Expense, 'is_system' => true],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(['code' => $account['code']], $account);
        }
    }
}
