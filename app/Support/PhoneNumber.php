<?php

namespace App\Support;

/**
 * Normalise and mask Indian mobile numbers used for OTP authentication.
 */
final class PhoneNumber
{
    /**
     * Convert a stored mobile number to the E.164 format used for display.
     */
    public static function forApi(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }

        if (strlen($digits) === 10) {
            return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? '+91'.$digits : null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $national = substr($digits, 2);

            return preg_match('/^[6-9]\d{9}$/', $national) === 1 ? '+'.$digits : null;
        }

        return '+'.$digits;
    }

    /**
     * Return the 10-digit Indian national number expected by Renflair.
     */
    public static function national(?string $value): ?string
    {
        $normalised = self::forApi($value);

        if ($normalised === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $normalised) ?? '';

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /**
     * Mask a mobile number for display, for example: +91 XXXXXXX210.
     */
    public static function mask(?string $value): string
    {
        $normalised = self::forApi($value);
        $digits = preg_replace('/\D+/', '', $normalised ?? '') ?? '';

        $national = strlen($digits) > 10 ? substr($digits, -10) : $digits;

        if ($national === '') {
            return '+91';
        }

        $lastThree = substr($national, -3);
        $masked = str_repeat('X', max(0, strlen($national) - 3)).$lastThree;

        return '+91 '.$masked;
    }
}
