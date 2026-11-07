<?php

namespace App\Services\Settings;

/**
 * Single source of truth for every centralised application setting.
 *
 * Each entry declares the storage group, value type, safe default and a
 * human label used by the settings screens and the audit trail.
 */
final class SettingsRegistry
{
    public const GROUP_COMPANY = 'company';

    public const GROUP_LOAN = 'loan';

    public const GROUP_INVESTOR = 'investor';

    public const GROUP_DEPOSIT = 'deposit';

    public const GROUP_ALLOCATION = 'allocation';

    public const GROUP_KYC = 'kyc';

    public const GROUP_SECURITY = 'security';

    public const GROUP_BACKUP = 'backup';

    /**
     * @return array<string, array{group: string, type: string, default: mixed, label: string, sensitive?: bool}>
     */
    public static function keys(): array
    {
        return [
            // Company / general settings.
            'company.name' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => config('app.name', 'Finance & Capital'), 'label' => 'Company name'],
            'company.legal_name' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Company legal name'],
            'company.registration_number' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Registration number'],
            'company.email' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Company email'],
            'company.phone' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Company phone number'],
            'company.support_email' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Support email'],
            'company.support_mobile' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Support mobile number'],
            'company.address' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Company address'],
            'company.city' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'City'],
            'company.state' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'State'],
            'company.pincode' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'PIN code'],
            'company.website' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '', 'label' => 'Website URL'],
            'company.logo' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => null, 'label' => 'Company logo', 'sensitive' => false],
            'company.currency_code' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => 'INR', 'label' => 'Currency'],
            'company.currency_symbol' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => '₹', 'label' => 'Currency symbol'],
            'company.timezone' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => 'Asia/Kolkata', 'label' => 'Timezone'],
            'company.date_format' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => 'd M Y', 'label' => 'Date format'],
            'company.time_format' => ['group' => self::GROUP_COMPANY, 'type' => 'string', 'default' => 'H:i', 'label' => 'Time format'],
            'company.financial_year_start_month' => ['group' => self::GROUP_COMPANY, 'type' => 'integer', 'default' => 4, 'label' => 'Financial year start month'],

            // Loan settings.
            'loan.min_amount' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '1000', 'label' => 'Minimum loan amount'],
            'loan.max_amount' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '100000000', 'label' => 'Maximum loan amount'],
            'loan.default_amount' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => null, 'label' => 'Default loan amount'],
            'loan.min_tenure_months' => ['group' => self::GROUP_LOAN, 'type' => 'integer', 'default' => 1, 'label' => 'Minimum tenure (months)'],
            'loan.max_tenure_months' => ['group' => self::GROUP_LOAN, 'type' => 'integer', 'default' => 360, 'label' => 'Maximum tenure (months)'],
            'loan.tenure_options' => ['group' => self::GROUP_LOAN, 'type' => 'json', 'default' => [6, 12, 18, 24, 36], 'label' => 'Available tenure options'],
            'loan.default_monthly_rate' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '4', 'label' => 'Default monthly interest rate'],
            'loan.min_monthly_rate' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Minimum monthly interest rate'],
            'loan.max_monthly_rate' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '5', 'label' => 'Maximum monthly interest rate'],
            'loan.allowed_interest_methods' => ['group' => self::GROUP_LOAN, 'type' => 'json', 'default' => ['flat', 'reducing'], 'label' => 'Allowed interest methods'],
            'loan.processing_fee_amount' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Loan processing fee'],
            'loan.processing_fee_type' => ['group' => self::GROUP_LOAN, 'type' => 'string', 'default' => 'fixed', 'label' => 'Processing fee type'],
            'loan.processing_fee_percent' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Processing fee percentage'],
            'loan.processing_fee_collection' => ['group' => self::GROUP_LOAN, 'type' => 'string', 'default' => 'record_only', 'label' => 'Processing fee collection rule'],
            'loan.require_super_admin_approval' => ['group' => self::GROUP_LOAN, 'type' => 'boolean', 'default' => false, 'label' => 'Final approval requires Super Admin'],
            'loan.penalty_enabled' => ['group' => self::GROUP_LOAN, 'type' => 'boolean', 'default' => false, 'label' => 'Late payment penalty'],
            'loan.penalty_method' => ['group' => self::GROUP_LOAN, 'type' => 'string', 'default' => 'per_day_percent', 'label' => 'Penalty calculation method'],
            'loan.penalty_rate' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Penalty rate'],
            'loan.penalty_grace_days' => ['group' => self::GROUP_LOAN, 'type' => 'integer', 'default' => 0, 'label' => 'Penalty grace period (days)'],
            'loan.penalty_max_amount' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => null, 'label' => 'Maximum penalty amount'],

            // Foreclosure settings.
            'loan.foreclosure_charges_enabled' => ['group' => self::GROUP_LOAN, 'type' => 'boolean', 'default' => false, 'label' => 'Enable foreclosure charges'],
            'loan.foreclosure_charge_type' => ['group' => self::GROUP_LOAN, 'type' => 'string', 'default' => 'fixed', 'label' => 'Foreclosure charge type'],
            'loan.foreclosure_charge_value' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Foreclosure charge value'],
            'loan.foreclosure_charge_minimum' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => '0', 'label' => 'Minimum foreclosure charge'],
            'loan.foreclosure_charge_maximum' => ['group' => self::GROUP_LOAN, 'type' => 'decimal', 'default' => null, 'label' => 'Maximum foreclosure charge'],
            'loan.foreclosure_effective_from' => ['group' => self::GROUP_LOAN, 'type' => 'date', 'default' => null, 'label' => 'Foreclosure charges effective from'],

            // Investor settings.
            'investor.min_investment' => ['group' => self::GROUP_INVESTOR, 'type' => 'decimal', 'default' => '0', 'label' => 'Minimum investment amount'],
            'investor.max_investment' => ['group' => self::GROUP_INVESTOR, 'type' => 'decimal', 'default' => null, 'label' => 'Maximum investment amount'],
            'investor.require_kyc_verified' => ['group' => self::GROUP_INVESTOR, 'type' => 'boolean', 'default' => false, 'label' => 'Require verified KYC before funding'],
            'investor.activation_requires_kyc' => ['group' => self::GROUP_INVESTOR, 'type' => 'boolean', 'default' => false, 'label' => 'Require verified KYC before activation'],
            'investor.management_fee_percent' => ['group' => self::GROUP_INVESTOR, 'type' => 'decimal', 'default' => '10', 'label' => 'Investor management fee (%)'],
            'investor.tds_percent' => ['group' => self::GROUP_INVESTOR, 'type' => 'decimal', 'default' => '10', 'label' => 'Investor TDS (%)'],

            // Deposit settings.
            'deposit.min_amount' => ['group' => self::GROUP_DEPOSIT, 'type' => 'decimal', 'default' => '1', 'label' => 'Minimum deposit amount'],
            'deposit.max_amount' => ['group' => self::GROUP_DEPOSIT, 'type' => 'decimal', 'default' => '100000000', 'label' => 'Maximum deposit amount'],
            'deposit.verification_required' => ['group' => self::GROUP_DEPOSIT, 'type' => 'boolean', 'default' => true, 'label' => 'Deposit verification required'],
            'deposit.allow_reclear_rejected' => ['group' => self::GROUP_DEPOSIT, 'type' => 'boolean', 'default' => false, 'label' => 'Allow rejected deposits to be re-cleared'],
            'deposit.min_allocation_amount' => ['group' => self::GROUP_DEPOSIT, 'type' => 'decimal', 'default' => '0', 'label' => 'Minimum allocation amount'],

            // Allocation settings.
            'allocation.allow_manual' => ['group' => self::GROUP_ALLOCATION, 'type' => 'boolean', 'default' => true, 'label' => 'Allow manual loan allocation'],
            'allocation.allow_partial' => ['group' => self::GROUP_ALLOCATION, 'type' => 'boolean', 'default' => true, 'label' => 'Allow partial allocation'],
            'allocation.allow_reallocation' => ['group' => self::GROUP_ALLOCATION, 'type' => 'boolean', 'default' => true, 'label' => 'Allow reallocation'],
            'allocation.prevent_duplicate' => ['group' => self::GROUP_ALLOCATION, 'type' => 'boolean', 'default' => true, 'label' => 'Prevent duplicate allocation'],

            // KYC settings.
            'kyc.borrower_documents' => ['group' => self::GROUP_KYC, 'type' => 'json', 'default' => self::borrowerDocumentDefaults(), 'label' => 'Borrower document requirements'],
            'kyc.investor_documents' => ['group' => self::GROUP_KYC, 'type' => 'json', 'default' => self::investorDocumentDefaults(), 'label' => 'Investor document requirements'],
            'kyc.allowed_formats' => ['group' => self::GROUP_KYC, 'type' => 'json', 'default' => ['jpg', 'jpeg', 'png', 'pdf'], 'label' => 'Allowed file formats'],
            'kyc.max_file_size_kb' => ['group' => self::GROUP_KYC, 'type' => 'integer', 'default' => 5120, 'label' => 'Maximum file size (KB)'],
            'kyc.max_upload_count' => ['group' => self::GROUP_KYC, 'type' => 'integer', 'default' => 10, 'label' => 'Maximum uploads per document type'],
            'kyc.reupload_allowed' => ['group' => self::GROUP_KYC, 'type' => 'boolean', 'default' => true, 'label' => 'Allow re-upload'],
            'kyc.reverification_required' => ['group' => self::GROUP_KYC, 'type' => 'boolean', 'default' => true, 'label' => 'Re-verification required after re-upload'],
            'kyc.manual_verification' => ['group' => self::GROUP_KYC, 'type' => 'boolean', 'default' => true, 'label' => 'Manual document verification'],
            'kyc.rejection_reason_required' => ['group' => self::GROUP_KYC, 'type' => 'boolean', 'default' => true, 'label' => 'Rejection reason required'],
            'kyc.mandatory_must_be_verified' => ['group' => self::GROUP_KYC, 'type' => 'boolean', 'default' => true, 'label' => 'Mandatory documents must be verified'],

            // Security settings.
            'security.password_min_length' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 12, 'label' => 'Minimum password length'],
            'security.password_require_mixed_case' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => true, 'label' => 'Require upper and lower case'],
            'security.password_require_numbers' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => true, 'label' => 'Require numbers'],
            'security.password_require_symbols' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => true, 'label' => 'Require special characters'],
            'security.password_require_uncompromised' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => false, 'label' => 'Reject compromised passwords'],
            'security.password_expiry_days' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 0, 'label' => 'Password expiry (days)'],
            'security.password_history_count' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 0, 'label' => 'Password reuse restriction (history count)'],
            'security.two_factor_enabled' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => true, 'label' => 'Two-factor authentication available'],
            'security.two_factor_required_super_admin' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => false, 'label' => 'Mandatory 2FA for Super Admin'],
            'security.two_factor_required_admin' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => false, 'label' => 'Mandatory 2FA for Admin'],
            'security.max_login_attempts' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 5, 'label' => 'Maximum login attempts'],
            'security.login_lockout_minutes' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 15, 'label' => 'Login lockout duration (minutes)'],
            'security.session_timeout_minutes' => ['group' => self::GROUP_SECURITY, 'type' => 'integer', 'default' => 0, 'label' => 'Session timeout (minutes)'],
            'security.concurrent_sessions' => ['group' => self::GROUP_SECURITY, 'type' => 'string', 'default' => 'allow', 'label' => 'Concurrent session policy'],
            'security.audit_logging_enabled' => ['group' => self::GROUP_SECURITY, 'type' => 'boolean', 'default' => true, 'label' => 'Audit logging'],

            // Backup settings.
            'backup.enabled' => ['group' => self::GROUP_BACKUP, 'type' => 'boolean', 'default' => false, 'label' => 'Scheduled backups'],
            'backup.frequency' => ['group' => self::GROUP_BACKUP, 'type' => 'string', 'default' => 'daily', 'label' => 'Backup frequency'],
            'backup.retention_days' => ['group' => self::GROUP_BACKUP, 'type' => 'integer', 'default' => 30, 'label' => 'Backup retention (days)'],
            'backup.last_run_at' => ['group' => self::GROUP_BACKUP, 'type' => 'string', 'default' => null, 'label' => 'Last backup timestamp'],
            'backup.last_status' => ['group' => self::GROUP_BACKUP, 'type' => 'string', 'default' => 'never', 'label' => 'Last backup status'],
            'backup.last_file' => ['group' => self::GROUP_BACKUP, 'type' => 'string', 'default' => null, 'label' => 'Last backup file'],
            'backup.last_error' => ['group' => self::GROUP_BACKUP, 'type' => 'string', 'default' => null, 'label' => 'Last backup error'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function meta(string $key): ?array
    {
        return self::keys()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keysForGroup(string $group): array
    {
        return array_keys(array_filter(
            self::keys(),
            fn (array $meta): bool => $meta['group'] === $group,
        ));
    }

    /**
     * @return array<string, array{enabled: bool, mandatory: bool}>
     */
    public static function borrowerDocumentDefaults(): array
    {
        return [
            'aadhaar' => ['enabled' => true, 'mandatory' => true],
            'pan' => ['enabled' => true, 'mandatory' => true],
            'electricity_bill' => ['enabled' => true, 'mandatory' => true],
            'income_proof' => ['enabled' => false, 'mandatory' => false],
            'bank_statement' => ['enabled' => false, 'mandatory' => false],
            'employment_proof' => ['enabled' => false, 'mandatory' => false],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, mandatory: bool}>
     */
    public static function investorDocumentDefaults(): array
    {
        return [
            'aadhaar' => ['enabled' => true, 'mandatory' => true],
            'pan' => ['enabled' => true, 'mandatory' => true],
            'bank_account_proof' => ['enabled' => false, 'mandatory' => false],
            'cancelled_cheque' => ['enabled' => false, 'mandatory' => false],
            'address_proof' => ['enabled' => false, 'mandatory' => false],
        ];
    }
}
