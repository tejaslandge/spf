<?php

namespace App\Services\Auth;

/**
 * The normalised outcome of a Renflair SMS request.
 */
final readonly class RenflairSmsResult
{
    public const SENT = 'sent';

    public const NOT_CONFIGURED = 'not_configured';

    public const UNAVAILABLE = 'unavailable';

    public const REJECTED = 'rejected';

    private function __construct(
        public bool $successful,
        public string $reason,
    ) {}

    public static function success(): self
    {
        return new self(true, self::SENT);
    }

    public static function failure(string $reason): self
    {
        return new self(false, $reason);
    }
}
