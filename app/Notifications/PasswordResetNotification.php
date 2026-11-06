<?php

namespace App\Notifications;

use App\Mail\GenericNotificationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset related notifications.
 */
class PasswordResetNotification extends Notification
{
    use Queueable;

    public User $user;
    public string $type; // 'requested' or 'completed'
    public string $tokenUrl;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $type
     * @param  string  $tokenUrl
     * @return void
     */
    public function __construct(User $user, string $type, string $tokenUrl)
    {
        $this->user = $user;
        $this->type = $type;
        $this->tokenUrl = $tokenUrl;
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
        $typeDisplay = ucfirst($this->type);

        return (new MailMessage)
            ->subject('Password '.$typeDisplay.' - '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('A password '.$this->type.' has been processed for your account.');

        if ($this->type === 'requested') {
            return (new MailMessage)
                ->subject('Password Reset Requested - '.config('app.name'))
                ->greeting('Hello '.$this->user->name.',')
                ->line('You recently requested a password reset.')
                ->line('Click the button below to set a new password:')
                ->action('Reset Password', $this->tokenUrl)
                ->line('This link will expire in 1 hour for security reasons.')
                ->line('If you did not request this, you can ignore this email or contact support at '.config('mail.from.address').'.');
        }

        if ($this->type === 'completed') {
            return (new MailMessage)
                ->subject('Password Reset Completed - '.config('app.name'))
                ->greeting('Hello '.$this->user->name.',')
                ->line('Your password has been successfully reset.')
                ->line('You can now log in with your new password.')
                ->action('Log In', route('login'))
                ->line('If you did not initiate this change, please secure your account immediately and contact support at '.config('mail.from.address').'.');
        }

        return (new MailMessage)
            ->subject('Password '.ucfirst($this->type).' - '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('A password event has been processed.');
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
            'type' => 'password_reset',
            'user_id' => $this->user->id,
            'reset_type' => $this->type,
        ];
    }
}