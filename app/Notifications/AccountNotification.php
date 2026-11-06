<?php

namespace App\Notifications;

use App\Mail\AccountStatusMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Account management notifications.
 * Sent when user account status changes (blocked, activated, deactivated, etc.).
 */
class AccountNotification extends Notification
{
    use Queueable;

    public User $user;
    public string $oldStatus;
    public string $newStatus;
    public string $reason;
    public ?User $changedBy;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $oldStatus
     * @param  string  $newStatus
     * @param  string  $reason
     * @param  ?\App\Models\User  $changedBy
     * @return void
     */
    public function __construct(User $user, string $oldStatus, string $newStatus, string $reason = '', ?User $changedBy = null)
    {
        $this->user = $user;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
        $this->changedBy = $changedBy;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail message.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $newStatusDisplay = ucfirst(str_replace('_', ' ', $this->newStatus));

        $reasonDisplay = $this->reason
            ? 'Reason: '.$this->reason.'<br/>'
            : '';

        return (new MailMessage)
            ->subject('Account Status Update - '.$newStatusDisplay.' - '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('Your account status has been updated.')
            ->line('New status: '.$newStatusDisplay)
            ->line($reasonDisplay)
            ->line('If you believe this change was made in error, please contact support at '.config('mail.from.address').'.')
            ->action('View Account', route('dashboard'))
            ->salutation('Best regards,', config('app.name').' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'account',
            'user_id' => $this->user->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
        ];
    }
}