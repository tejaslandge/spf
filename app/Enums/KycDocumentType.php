<?php

namespace App\Enums;

enum KycDocumentType: string
{
    case Aadhaar = 'aadhaar';
    case Pan = 'pan';
    case ElectricityBill = 'electricity_bill';
    case IncomeProof = 'income_proof';
    case BankStatement = 'bank_statement';
    case EmploymentProof = 'employment_proof';
    case BankAccountProof = 'bank_account_proof';
    case CancelledCheque = 'cancelled_cheque';
    case AddressProof = 'address_proof';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Aadhaar => 'Aadhaar Card',
            self::Pan => 'PAN Card',
            self::ElectricityBill => 'Electricity Bill (Address Proof)',
            self::IncomeProof => 'Income Proof',
            self::BankStatement => 'Bank Statement',
            self::EmploymentProof => 'Employment / Business Proof',
            self::BankAccountProof => 'Bank Account Proof',
            self::CancelledCheque => 'Cancelled Cheque',
            self::AddressProof => 'Address Proof',
            self::Other => 'Other document',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
