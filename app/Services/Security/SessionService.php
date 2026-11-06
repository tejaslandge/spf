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

    /**
     * Revoke any session by id (Super Admin session management). The caller's
     * own session can never be revoked through this method.
     */
    public function revokeAnyExceptCurrent(string $sessionId): bool
    {
        $currentId = request()->hasSession() ? request()->session()->getId() : null;

        if ($currentId !== null && $sessionId === $currentId) {
            return false;
        }

        return DB::table('sessions')->where('id', $sessionId)->delete() > 0;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function allActive(int $limit = 25): Collection
    {
        return DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->select(['sessions.id', 'sessions.user_id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity', 'users.name as user_name', 'users.email as user_email'])
            ->orderByDesc('sessions.last_activity')
            ->limit($limit)
            ->get();
    }

    public function enforceSingleSessionPolicy(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

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
