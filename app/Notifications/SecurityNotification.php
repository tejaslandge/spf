<?php

namespace App\Notifications;

use App\Mail\LoginOtpMail;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Security-related notifications.
 * Sends email alerts for security events without exposing sensitive data.
 */
class SecurityNotification extends Notification
{
    use Queueable;

    public User $user;
    public string $event;
    public string $details;
    public ?string $ipAddress;
    public ?string $deviceInfo;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $event
     * @param  string  $details
     * @param  string|null  $ipAddress
     * @param  string|null  $deviceInfo
     * @return void
     */
    public function __construct(User $user, string $event, string $details, ?string $ipAddress = null, ?string $deviceInfo = null)
    {
        $this->user = $user;
        $this->event = $event;
        $this->details = $details;
        $this->ipAddress = $ipAddress;
        $this->deviceInfo = $deviceInfo;
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
        $eventDisplay = ucfirst(str_replace('_', ' ', $this->event));

        return (new MailMessage)
            ->subject('Security Alert - '.$eventDisplay.' - '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('A security event has been recorded on your account.')
            ->line('Event: '.$eventDisplay)
            ->line('Details: '.$this->details);

        // Add IP/device info only if available (not for end-user exposure in all cases)
        if (! empty($this->ipAddress)) {
            // This could be conditionally added based on sensitivity
        }

        ->line('If you did not initiate this activity, please contact support immediately at '.config('mail.from.address').'.')
            ->action('View Account', route('dashboard'))
            ->salutation('Security Team, '.config('app.name'));
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
            'type' => 'security',
            'user_id' => $this->user->id,
            'event' => $this->event,
            'details' => $this->details,
        ];
    }
}