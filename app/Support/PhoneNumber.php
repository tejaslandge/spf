<?php

namespace App\Support;

/**
 * Normalise and mask mobile numbers used for OTP authentication.
 */
final class PhoneNumber
{
    /**
     * Convert a dynamic login input into international digits without spaces,
     * hyphens, or the + symbol (e.g. 91721820585) for the WhatsApp OTP API.
     */
    public static function forWhatsAppApi(?string $value, string $defaultCountryCode = '91'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = $defaultCountryCode.$digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Convert a stored or entered mobile number to an E.164-like + format for display/session.
     */
    public static function forApi(?string $value, string $defaultCountryCode = '91'): ?string
    {
        $digits = self::forWhatsAppApi($value, $defaultCountryCode);

        return $digits === null ? null : '+'.$digits;
    }

    /**
     * Return the 10-digit Indian national number when applicable.
     */
    public static function national(?string $value, string $defaultCountryCode = '91'): ?string
    {
        $digits = self::forWhatsAppApi($value, $defaultCountryCode);

        if ($digits === null) {
            return null;
        }

        if (str_starts_with($digits, $defaultCountryCode) && strlen($digits) === strlen($defaultCountryCode) + 10) {
            return substr($digits, -10);
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /**
     * Mask a mobile number for display.
     */
    public static function mask(?string $value, string $defaultCountryCode = '91'): string
    {
        $digits = self::forWhatsAppApi($value, $defaultCountryCode);

        if ($digits === null) {
            return '+'.$defaultCountryCode;
        }

        $countryCode = $defaultCountryCode;
        $national = $digits;

        if (str_starts_with($digits, $defaultCountryCode) && strlen($digits) > strlen($defaultCountryCode)) {
            $national = substr($digits, strlen($defaultCountryCode));
        } else {
            $countryCode = strlen($digits) > 10 ? substr($digits, 0, -10) : $defaultCountryCode;
            $national = strlen($digits) > 10 ? substr($digits, -10) : $digits;
        }

        $visible = max(2, min(4, strlen($national)));
        $masked = str_repeat('X', max(0, strlen($national) - $visible)).substr($national, -$visible);

        return '+'.$countryCode.' '.$masked;
    }

    public static function equivalentValues(?string $value, string $defaultCountryCode = '91'): array
    {
        $digits = self::forWhatsAppApi($value, $defaultCountryCode);

        if ($digits === null) {
            return [];
        }

        $values = [$digits, '+'.$digits];
        $national = self::national($digits, $defaultCountryCode);

        if ($national !== null) {
            $values[] = $national;
            $values[] = $defaultCountryCode.$national;
            $values[] = '+'.$defaultCountryCode.$national;
        }

        return array_values(array_unique($values));
    }
}
