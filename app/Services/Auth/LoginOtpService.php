<?php

namespace App\Services\Auth;

use App\Models\LoginOtp as LoginOtpModel;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DB-backed pending login challenge for dynamic WhatsApp OTP verification.
 *
 * The challenge stores only a secure OTP hash, is bound to the exact entered
 * phone number, expires after 5 minutes, and cannot be reused after success.
 */
final class LoginOtpService
{
    public const SESSION_KEY = 'auth.login_otp';

    public const EXPIRES_AFTER_SECONDS = 300;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Create a new challenge for a dynamic phone number and return the plaintext OTP.
     */
    public function start(?User $user, bool $remember, ?string $phone = null): string
    {
        $apiPhone = $this->normalizeApiPhone($phone ?? $user?->mobile);
        $code = $this->generateCode();
        $challengeId = (string) Str::uuid();

        $record = LoginOtpModel::query()->create([
            'challenge_id' => $challengeId,
            'phone' => $apiPhone,
            'user_id' => $user?->getKey(),
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(self::EXPIRES_AFTER_SECONDS),
            'last_sent_at' => now(),
            'resend_available_at' => now()->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            'attempts' => 0,
            'used_at' => null,
            'remember' => $remember,
        ]);

        $this->storeSession($record);

        return $code;
    }

    public function logCode(string $code): void
    {
        if (! (bool) (config('services.renflair.log_otp', false) || config('services.whatsapp.log_otp', false))) {
            return;
        }

        Log::info('Login OTP code generated.', [
            'phone' => PhoneNumber::mask($this->phone()),
            'otp' => $code,
        ]);
    }

    public function regenerate(): ?string
    {
        $record = $this->record();

        if ($record === null || $record->isUsed()) {
            $this->clear();

            return null;
        }

        $user = $record->user_id ? $record->user()->first() : null;

        return $this->start($user, $record->remember, $record->phone);
    }

    public function verify(string $code): bool
    {
        $record = $this->record();

        if ($record === null || $record->isUsed() || $record->isExpired()) {
            return false;
        }

        return Hash::check($code, $record->otp_hash);
    }

    public function markUsed(): void
    {
        $record = $this->record();

        if ($record === null || $record->isUsed()) {
            return;
        }

        $record->forceFill(['used_at' => now()])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        $session = session(self::SESSION_KEY);
        $record = $this->record();

        if (! is_array($session) || $record === null) {
            return null;
        }

        return [
            'session' => $session,
            'record' => $record->replicate()->getAttributes(),
            'record_id' => $record->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $challenge
     */
    public function restore(?array $challenge): void
    {
        if ($challenge === null) {
            $this->clear();

            return;
        }

        $recordId = $challenge['record_id'] ?? null;
        $attributes = $challenge['record'] ?? null;

        if ($recordId && is_array($attributes)) {
            $record = LoginOtpModel::query()->find($recordId);

            if ($record !== null) {
                $record->forceFill($attributes)->save();
                session([self::SESSION_KEY => $challenge['session'] ?? ['id' => $record->challenge_id, 'phone' => $record->phone]]);
                session(['auth.login_phone' => $record->phone]);

                return;
            }
        }

        $this->clear();
    }

    public function exists(): bool
    {
        return $this->record() !== null;
    }

    public function user(): ?User
    {
        $record = $this->record();

        if ($record === null) {
            return null;
        }

        if ($record->user_id) {
            return $record->user()->first();
        }

        return User::query()
            ->whereIn('mobile', PhoneNumber::equivalentValues($record->phone))
            ->first();
    }

    public function phone(): ?string
    {
        return $this->record()?->phone ?? session('auth.login_phone');
    }

    public function remember(): bool
    {
        return (bool) ($this->record()?->remember ?? false);
    }

    public function isExpired(): bool
    {
        $record = $this->record();

        return $record === null || $record->isExpired();
    }

    public function secondsUntilExpiry(): int
    {
        $record = $this->record();

        if ($record === null || $record->expires_at === null) {
            return 0;
        }

        return max(0, now()->diffInSeconds($record->expires_at, false));
    }

    public function secondsUntilResend(): int
    {
        $record = $this->record();

        if ($record === null || $record->resend_available_at === null) {
            return 0;
        }

        return max(0, now()->diffInSeconds($record->resend_available_at, false));
    }

    public function attempts(): int
    {
        return (int) ($this->record()?->attempts ?? 0);
    }

    public function remainingAttempts(): int
    {
        return max(0, self::MAX_ATTEMPTS - $this->attempts());
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->remainingAttempts() > 0;
    }

    public function recordFailedAttempt(): int
    {
        $record = $this->record();

        if ($record === null) {
            return 0;
        }

        $record->forceFill(['attempts' => $record->attempts + 1])->save();

        return (int) $record->attempts;
    }

    public function clear(): void
    {
        $record = $this->record();

        if ($record !== null && ! $record->isUsed()) {
            $record->forceFill(['used_at' => now()])->save();
        }

        session()->forget([self::SESSION_KEY, 'auth.login_phone']);
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function normalizeApiPhone(?string $value): string
    {
        $config = require base_path('waapi.php');
        $default = (string) ($config['default_country_code'] ?? '91');
        $phone = PhoneNumber::forWhatsAppApi($value, $default);

        if ($phone === null) {
            throw new \InvalidArgumentException('A valid phone number is required for OTP delivery.');
        }

        return $phone;
    }

    private function storeSession(LoginOtpModel $record): void
    {
        session([
            self::SESSION_KEY => [
                'id' => $record->challenge_id,
                'phone' => $record->phone,
                'user_id' => $record->user_id,
                'remember' => (bool) $record->remember,
            ],
            'auth.login_phone' => $record->phone,
        ]);
    }

    private function record(): ?LoginOtpModel
    {
        $session = session(self::SESSION_KEY);

        if (! is_array($session) || ! isset($session['id'])) {
            return null;
        }

        return LoginOtpModel::query()
            ->where('challenge_id', (string) $session['id'])
            ->first();
    }
}
