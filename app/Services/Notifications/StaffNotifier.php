<?php

namespace App\Services\Notifications;

use App\Enums\Permission;
use App\Models\User;
use App\Notifications\StaffNotification;

/**
 * Sends staff notifications to every active user holding the relevant
 * permission (Super Admin always included). Each recipient's own notification
 * preferences decide whether database and mail delivery happen.
 */
final class StaffNotifier
{
    public function notifyPermission(
        Permission $permission,
        string $category,
        string $title,
        string $body,
        ?string $url = null,
        ?User $except = null,
    ): int {
        $recipients = User::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission($permission) && $user->getKey() !== $except?->getKey());

        foreach ($recipients as $recipient) {
            $recipient->notify(new StaffNotification($category, $title, $body, $url));
        }

        return $recipients->count();
    }
}
