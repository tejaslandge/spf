<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Server-side pending login challenge stored in the session.
 *
 * The user is deliberately not authenticated until the mobile OTP has been
 * verified. The challenge holds the pending user id, the registered phone
 * number, a hash of the generated code and the attempt/expiry counters. No
 * phone number is ever read from the OTP form and the plaintext code only
 * lives in memory long enough to be handed to the SMS gateway.
 */
final class LoginOtpService
{
    public const SESSION_KEY = 'auth.login_otp';

    public const EXPIRES_AFTER_SECONDS = 300;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Create a new challenge and return the plaintext code for delivery.
     */
    public function start(User $user, bool $remember): string
    {
        $phone = PhoneNumber::national($user->mobile);
        $code = $this->generateCode();

        session([self::SESSION_KEY => [
            'id' => Str::random(40),
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'remember' => $remember,
            'otp_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->addSeconds(self::EXPIRES_AFTER_SECONDS)->getTimestamp(),
        ]]);

        return $code;
    }

    /**
     * Replace the current code with a fresh one, invalidating the previous code.
     *
     * Returns the plaintext code to deliver, or null when the challenge is gone.
     */
    public function regenerate(): ?string
    {
        $user = $this->user();

        if ($user === null) {
            $this->clear();

            return null;
        }

        return $this->start($user, $this->remember());
    }

    /**
     * Compare a submitted code against the hashed code in the challenge.
     */
    public function verify(string $code): bool
    {
        $hash = $this->get()['otp_hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            return false;
        }

        return Hash::check($code, $hash);
    }

    /**
     * Capture the raw challenge so a failed resend can restore it.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        $challenge = session(self::SESSION_KEY);

        return is_array($challenge) ? $challenge : null;
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

        session([self::SESSION_KEY => $challenge]);
    }

    /**
     * @return array{id: string, user_id: int|string, phone: ?string, remember: bool, otp_hash: string, attempts: int, sent_at: int, expires_at: int}|null
     */
    public function get(): ?array
    {
        $challenge = session(self::SESSION_KEY);

        if (! is_array($challenge)
            || ! isset($challenge['user_id'], $challenge['expires_at'], $challenge['id'], $challenge['otp_hash'])) {
            return null;
        }

        return [
            'id' => (string) $challenge['id'],
            'user_id' => $challenge['user_id'],
            'phone' => isset($challenge['phone']) && is_string($challenge['phone']) ? $challenge['phone'] : null,
            'remember' => (bool) ($challenge['remember'] ?? false),
            'otp_hash' => (string) $challenge['otp_hash'],
            'attempts' => (int) ($challenge['attempts'] ?? 0),
            'sent_at' => (int) ($challenge['sent_at'] ?? 0),
            'expires_at' => (int) $challenge['expires_at'],
        ];
    }

    public function exists(): bool
    {
        return $this->get() !== null;
    }

    public function user(): ?User
    {
        $challenge = $this->get();

        if ($challenge === null) {
            return null;
        }

        return User::query()->find($challenge['user_id']);
    }

    public function phone(): ?string
    {
        return $this->get()['phone'] ?? null;
    }

    public function remember(): bool
    {
        return (bool) ($this->get()['remember'] ?? false);
    }

    public function isExpired(): bool
    {
        $challenge = $this->get();

        return $challenge === null || $challenge['expires_at'] <= now()->getTimestamp();
    }

    public function secondsUntilExpiry(): int
    {
        $challenge = $this->get();

        if ($challenge === null) {
            return 0;
        }

        return max(0, $challenge['expires_at'] - now()->getTimestamp());
    }

    public function secondsUntilResend(): int
    {
        $challenge = $this->get();

        if ($challenge === null) {
            return 0;
        }

        return max(0, ($challenge['sent_at'] + self::RESEND_COOLDOWN_SECONDS) - now()->getTimestamp());
    }

    public function attempts(): int
    {
        return (int) ($this->get()['attempts'] ?? 0);
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
        $challenge = $this->get();

        if ($challenge === null) {
            return 0;
        }

        $challenge['attempts']++;

        session([self::SESSION_KEY => $challenge]);

        return $challenge['attempts'];
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
