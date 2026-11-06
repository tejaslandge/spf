<?php

namespace App\Notifications;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a new user registers.
 * Triggers a professional welcome email through the centralized system.
 */
class WelcomeNotification extends Notification
{
    use Queueable;

    public User $user;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
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
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name'))
            ->greeting('Hello '.$this->user->name.',')
            ->line('Welcome to '.config('app.name').'! We\'re excited to have you on board.')
            ->line('Your account has been successfully created. You can now log in using your credentials.')
            ->action('Log In', route('login'))
            ->line('If you have any questions, please contact our support team at '.config('mail.from.address').'.')
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
            'type' => 'welcome',
            'user_id' => $this->user->id,
        ];
    }
}