<?php

namespace App\Services\Settings;

use App\Enums\KycDocumentType;
use App\Models\Borrower;
use App\Models\Investor;

/**
 * Reads the KYC & verification configuration. Upload and verification code
 * asks this class instead of hardcoding document requirements.
 */
final class KycSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, array{enabled: bool, mandatory: bool}>
     */
    public function documentsFor(string $subjectType): array
    {
        [$key, $defaults] = $this->keyAndDefaults($subjectType);

        if ($key === null) {
            return [];
        }

        $stored = $this->settings->getJson($key, $defaults);

        // Drop stale or unknown document types so removed options cannot linger.
        $stored = array_intersect_key($stored, $defaults);

        return array_replace_recursive($defaults, $stored);
    }

    /**
     * @return array<int, string>
     */
    public function enabledTypesFor(string $subjectType): array
    {
        return array_keys(array_filter(
            $this->documentsFor($subjectType),
            fn (array $config): bool => (bool) $config['enabled'],
        ));
    }

    /**
     * @return array<int, string>
     */
    public function mandatoryTypesFor(string $subjectType): array
    {
        return array_keys(array_filter(
            $this->documentsFor($subjectType),
            fn (array $config): bool => (bool) $config['enabled'] && (bool) $config['mandatory'],
        ));
    }

    /**
     * @return array<int, string>
     */
    public function allowedFormats(): array
    {
        $formats = array_values(array_intersect(
            $this->settings->getJson('kyc.allowed_formats', ['jpg', 'jpeg', 'png', 'pdf']),
            ['jpg', 'jpeg', 'png', 'pdf'],
        ));

        return $formats === [] ? ['jpg', 'jpeg', 'png', 'pdf'] : $formats;
    }

    public function maxFileSizeKb(): int
    {
        return max(100, $this->settings->getInt('kyc.max_file_size_kb', 5120));
    }

    public function maxUploadCount(): int
    {
        return max(1, $this->settings->getInt('kyc.max_upload_count', 10));
    }

    public function reuploadAllowed(): bool
    {
        return $this->settings->getBool('kyc.reupload_allowed', true);
    }

    public function reverificationRequired(): bool
    {
        return $this->settings->getBool('kyc.reverification_required', true);
    }

    public function manualVerification(): bool
    {
        return $this->settings->getBool('kyc.manual_verification', true);
    }

    public function rejectionReasonRequired(): bool
    {
        return $this->settings->getBool('kyc.rejection_reason_required', true);
    }

    public function mandatoryMustBeVerified(): bool
    {
        return $this->settings->getBool('kyc.mandatory_must_be_verified', true);
    }

    /**
     * @return array<int, string>
     */
    public function fileValidationRules(): array
    {
        return ['file', 'mimes:'.implode(',', $this->allowedFormats()), 'max:'.$this->maxFileSizeKb()];
    }

    /**
     * @return array{0: string|null, 1: array<string, array{enabled: bool, mandatory: bool}>}
     */
    private function keyAndDefaults(string $subjectType): array
    {
        return match ($subjectType) {
            Borrower::class => ['kyc.borrower_documents', SettingsRegistry::borrowerDocumentDefaults()],
            Investor::class => ['kyc.investor_documents', SettingsRegistry::investorDocumentDefaults()],
            default => [null, []],
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptionsFor(string $subjectType): array
    {
        return collect($this->enabledTypesFor($subjectType))
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => KycDocumentType::tryFrom($value)?->label() ?? $value,
            ])
            ->values()
            ->all();
    }
}
