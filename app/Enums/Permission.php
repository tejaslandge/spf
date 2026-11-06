<?php

namespace App\Enums;

enum Permission: string
{
    // Dashboard
    case DashboardView = 'dashboard.view';
    case DashboardFinancialSummary = 'dashboard.financial_summary';

    // Borrower management
    case BorrowersView = 'borrowers.view';
    case BorrowersCreate = 'borrowers.create';
    case BorrowersEdit = 'borrowers.edit';
    case BorrowersVerifyDocuments = 'borrowers.verify_documents';

    // Investor management
    case InvestorsView = 'investors.view';
    case InvestorsCreate = 'investors.create';
    case InvestorsEdit = 'investors.edit';
    case InvestorsVerifyDocuments = 'investors.verify_documents';

    // Loan management
    case LoansView = 'loans.view';
    case LoansCreate = 'loans.create';
    case LoansEdit = 'loans.edit';
    case LoansReview = 'loans.review';
    case LoansApprove = 'loans.approve';
    case LoansReject = 'loans.reject';

    // Deposit management
    case DepositsView = 'deposits.view';
    case DepositsVerify = 'deposits.verify';
    case DepositsChangeStatus = 'deposits.change_status';
    case DepositsAllocate = 'deposits.allocate';

    // Repayment management
    case RepaymentsView = 'repayments.view';
    case RepaymentsRecord = 'repayments.record';
    case RepaymentsVerify = 'repayments.verify';

    // Financial management
    case FinancialReports = 'financial.reports';
    case FinancialDistribution = 'financial.distribution';
    case FinancialPayouts = 'financial.payouts';
    case FinancialAccounting = 'financial.accounting';

    // Settings
    case SettingsViewGeneral = 'settings.view_general';
    case SettingsViewLoan = 'settings.view_loan';
    case SettingsViewInterest = 'settings.view_interest';
    case SettingsModify = 'settings.modify';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'View Dashboard',
            self::DashboardFinancialSummary => 'View Financial Summary',
            self::BorrowersView => 'View Borrowers',
            self::BorrowersCreate => 'Create Borrowers',
            self::BorrowersEdit => 'Edit Borrowers',
            self::BorrowersVerifyDocuments => 'Verify Borrower Documents',
            self::InvestorsView => 'View Investors',
            self::InvestorsCreate => 'Create Investors',
            self::InvestorsEdit => 'Edit Investors',
            self::InvestorsVerifyDocuments => 'Verify Investor Documents',
            self::LoansView => 'View Loans',
            self::LoansCreate => 'Create Loans',
            self::LoansEdit => 'Edit Loans',
            self::LoansReview => 'Review Loans',
            self::LoansApprove => 'Approve Loans',
            self::LoansReject => 'Reject Loans',
            self::DepositsView => 'View Deposits',
            self::DepositsVerify => 'Verify Deposits',
            self::DepositsChangeStatus => 'Change Deposit Status',
            self::DepositsAllocate => 'Allocate Deposits',
            self::RepaymentsView => 'View Repayments',
            self::RepaymentsRecord => 'Record Collections',
            self::RepaymentsVerify => 'Verify Collections',
            self::FinancialReports => 'View Financial Reports',
            self::FinancialDistribution => 'View Interest Distribution',
            self::FinancialPayouts => 'View Investor Payouts',
            self::FinancialAccounting => 'Access Accounting Records',
            self::SettingsViewGeneral => 'View General Settings',
            self::SettingsViewLoan => 'View Loan Settings',
            self::SettingsViewInterest => 'View Interest Settings',
            self::SettingsModify => 'Modify Authorized Settings',
        };
    }

    public function group(): PermissionGroup
    {
        return match ($this) {
            self::DashboardView, self::DashboardFinancialSummary => PermissionGroup::Dashboard,
            self::BorrowersView, self::BorrowersCreate, self::BorrowersEdit, self::BorrowersVerifyDocuments => PermissionGroup::BorrowerManagement,
            self::InvestorsView, self::InvestorsCreate, self::InvestorsEdit, self::InvestorsVerifyDocuments => PermissionGroup::InvestorManagement,
            self::LoansView, self::LoansCreate, self::LoansEdit, self::LoansReview, self::LoansApprove, self::LoansReject => PermissionGroup::LoanManagement,
            self::DepositsView, self::DepositsVerify, self::DepositsChangeStatus, self::DepositsAllocate => PermissionGroup::DepositManagement,
            self::RepaymentsView, self::RepaymentsRecord, self::RepaymentsVerify => PermissionGroup::RepaymentManagement,
            self::FinancialReports, self::FinancialDistribution, self::FinancialPayouts, self::FinancialAccounting => PermissionGroup::FinancialManagement,
            self::SettingsViewGeneral, self::SettingsViewLoan, self::SettingsViewInterest, self::SettingsModify => PermissionGroup::Settings,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * @return array<string, array{label: string, permissions: array<int, array{value: string, label: string}>}>
     */
    public static function groupedOptions(): array
    {
        $groups = [];

        foreach (PermissionGroup::cases() as $group) {
            $groups[$group->value] = [
                'label' => $group->label(),
                'permissions' => array_map(
                    fn (Permission $permission): array => [
                        'value' => $permission->value,
                        'label' => $permission->label(),
                    ],
                    $group->permissions(),
                ),
            ];
        }

        return $groups;
    }
}
