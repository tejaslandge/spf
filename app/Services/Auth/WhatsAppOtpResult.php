<?php

namespace App\Services\Auth;

/**
 * Normalised outcome of a WhatsApp OTP API request.
 */
final readonly class WhatsAppOtpResult
{
    public const SENT = 'sent';

    public const NOT_CONFIGURED = 'not_configured';

    public const UNAUTHORIZED = 'unauthorized';

    public const UNAVAILABLE = 'unavailable';

    public const REJECTED = 'rejected';

    public function __construct(
        public bool $successful,
        public string $reason,
        public int $statusCode = 0,
        /** @var array<string, mixed>|null */
        public ?array $response = null,
        public ?string $message = null,
    ) {}

    public static function success(int $statusCode = 200, ?array $response = null, ?string $message = null): self
    {
        return new self(true, self::SENT, $statusCode, $response, $message);
    }

    public static function failure(string $reason, int $statusCode = 0, ?array $response = null, ?string $message = null): self
    {
        return new self(false, $reason, $statusCode, $response, $message);
    }
}
