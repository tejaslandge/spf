<?php

namespace App\Enums;

enum DailyTaskType: string
{
    case EmiReminder = 'emi_reminder';
    case OverdueFollowUp = 'overdue_followup';
    case KycReview = 'kyc_review';
    case PayoutDue = 'payout_due';
    case LoanApproval = 'loan_approval';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::EmiReminder => 'EMI reminder',
            self::OverdueFollowUp => 'Overdue follow-up',
            self::KycReview => 'KYC review',
            self::PayoutDue => 'Investor payout',
            self::LoanApproval => 'Loan approval',
            self::Manual => 'General',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EmiReminder => 'blue',
            self::OverdueFollowUp => 'rose',
            self::KycReview => 'amber',
            self::PayoutDue => 'purple',
            self::LoanApproval => 'cyan',
            self::Manual => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::EmiReminder => 'bell-alert',
            self::OverdueFollowUp => 'exclamation-triangle',
            self::KycReview => 'identification',
            self::PayoutDue => 'banknotes',
            self::LoanApproval => 'clipboard-document-check',
            self::Manual => 'pencil-square',
        };
    }

    public function isMessageable(): bool
    {
        return in_array($this, [self::EmiReminder, self::OverdueFollowUp], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
