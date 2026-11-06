<?php

namespace App\Enums;

/**
 * Admins only have two primary roles; designations express the working
 * function and are mapped to permissions solely through the explicit
 * templates below. A designation never grants access on its own — a Super
 * Admin must apply the template when assigning permissions.
 */
enum AdminDesignation: string
{
    case FinanceManager = 'finance_manager';
    case Accountant = 'accountant';
    case LoanManager = 'loan_manager';
    case OperationsManager = 'operations_manager';

    public function label(): string
    {
        return match ($this) {
            self::FinanceManager => 'Finance Manager',
            self::Accountant => 'Accountant',
            self::LoanManager => 'Loan Manager',
            self::OperationsManager => 'Operations Manager',
        };
    }

    /**
     * @return array<int, Permission>
     */
    public function permissionTemplate(): array
    {
        return match ($this) {
            self::FinanceManager => [
                Permission::DashboardView,
                Permission::DashboardFinancialSummary,
                Permission::DepositsView,
                Permission::DepositsVerify,
                Permission::DepositsChangeStatus,
                Permission::RepaymentsView,
                Permission::RepaymentsVerify,
                Permission::FinancialReports,
                Permission::FinancialDistribution,
                Permission::FinancialPayouts,
                Permission::FinancialAccounting,
                Permission::SettingsViewGeneral,
                Permission::SettingsViewLoan,
                Permission::SettingsViewInterest,
            ],
            self::Accountant => [
                Permission::DashboardView,
                Permission::DashboardFinancialSummary,
                Permission::BorrowersView,
                Permission::LoansView,
                Permission::DepositsView,
                Permission::RepaymentsView,
                Permission::FinancialReports,
                Permission::FinancialAccounting,
                Permission::FinancialPayouts,
            ],
            self::LoanManager => [
                Permission::DashboardView,
                Permission::BorrowersView,
                Permission::BorrowersCreate,
                Permission::BorrowersEdit,
                Permission::BorrowersVerifyDocuments,
                Permission::LoansView,
                Permission::LoansCreate,
                Permission::LoansEdit,
                Permission::LoansReview,
                Permission::LoansApprove,
                Permission::LoansReject,
                Permission::RepaymentsView,
                Permission::RepaymentsRecord,
                Permission::DepositsView,
            ],
            self::OperationsManager => [
                Permission::DashboardView,
                Permission::BorrowersView,
                Permission::InvestorsView,
                Permission::LoansView,
                Permission::DepositsView,
                Permission::DepositsAllocate,
                Permission::RepaymentsView,
                Permission::RepaymentsRecord,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $this->permissionTemplate());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $designation) => [$designation->value => $designation->label()])->all();
    }
}
