<?php

namespace App\Enums;

enum PermissionGroup: string
{
    case Dashboard = 'dashboard';
    case BorrowerManagement = 'borrowers';
    case InvestorManagement = 'investors';
    case LoanManagement = 'loans';
    case DepositManagement = 'deposits';
    case RepaymentManagement = 'repayments';
    case FinancialManagement = 'financial';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::BorrowerManagement => 'Borrower Management',
            self::InvestorManagement => 'Investor Management',
            self::LoanManagement => 'Loan Management',
            self::DepositManagement => 'Deposit Management',
            self::RepaymentManagement => 'Repayment Management',
            self::FinancialManagement => 'Financial Management',
            self::Settings => 'Settings',
        };
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => $permission->group() === $this,
        ));
    }
}
