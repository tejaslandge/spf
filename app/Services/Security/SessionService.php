<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\SessionDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SessionService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return Collection<int, SessionDevice>
     */
    public function activeFor(User $user): Collection
    {
        $currentId = request()->hasSession() ? request()->session()->getId() : null;

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): SessionDevice => new SessionDevice(
                id: (string) $session->id,
                ipAddress: $session->ip_address,
                userAgent: $session->user_agent,
                lastActivity: Carbon::createFromTimestamp((int) $session->last_activity),
                isCurrent: $currentId !== null && (string) $session->id === $currentId,
            ));
    }

    public function revoke(string $sessionId, User $user): bool
    {
        $currentId = request()->hasSession() ? request()->session()->getId() : null;

        if ($currentId !== null && $sessionId === $currentId) {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeOthers(User $user): int
    {
        $currentId = request()->hasSession() ? request()->session()->getId() : null;

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentId !== null, fn ($query) => $query->where('id', '!=', $currentId))
            ->delete();
    }

    public function revokeAll(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    public function enforceSingleSessionPolicy(User $user): void
    {
        if ($this->settings->get('security.concurrent_sessions', 'allow') === 'single') {
            $this->revokeOthers($user);
        }
    }

    public function timeoutMinutes(): int
    {
        $configured = $this->settings->getInt('security.session_timeout_minutes', 0);

        return $configured > 0 ? $configured : (int) config('session.lifetime', 120);
    }
}
