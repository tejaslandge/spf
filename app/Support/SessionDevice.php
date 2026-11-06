<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Read-only view of one database session row for the session management UI.
 */
final readonly class SessionDevice
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public ?string $userAgent,
        public Carbon $lastActivity,
        public bool $isCurrent = false,
    ) {}

    public function browser(): string
    {
        return match (true) {
            str_contains((string) $this->userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains((string) $this->userAgent, 'Chrome/') => 'Google Chrome',
            str_contains((string) $this->userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains((string) $this->userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    public function platform(): string
    {
        return match (true) {
            str_contains((string) $this->userAgent, 'Windows') => 'Windows',
            str_contains((string) $this->userAgent, 'Android') => 'Android',
            str_contains((string) $this->userAgent, 'iPhone'), str_contains((string) $this->userAgent, 'iPad') => 'iOS',
            str_contains((string) $this->userAgent, 'Macintosh') => 'macOS',
            str_contains((string) $this->userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };
    }
}
