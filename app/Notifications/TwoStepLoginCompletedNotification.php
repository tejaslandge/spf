<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Optional two-step login completion notification.
 * Sent after successful 2FA or OTP verification.
 */
class TwoStepLoginCompletedNotification extends Notification
{
    use Queueable;

    public User $user;
    public string $method; // 'otp', 'sms', 'passkey'
    public string $deviceInfo;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $method
     * @param  string  $deviceInfo
     * @return void
     */
    public function __construct(User $user, string $method, string $deviceInfo = '')
    {
        $this->user = $user;
        $this->method = $method;
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
        $methodDisplay = ucfirst($this->method);

        return (new MailMessage)
            ->subject('Login Confirmation - '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('Your login was completed successfully using '.$methodDisplay.' verification.')
            ->line('Device information: '.$this->deviceInfo ?: 'Your registered device.')
            ->line('If you did not initiate this login, please secure your account and contact support at '.config('mail.from.address').'.')
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
            'type' => 'two_step_login',
            'user_id' => $this->user->id,
            'method' => $this->method,
        ];
    }
}