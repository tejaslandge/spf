<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\UserPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $category,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        $preferences = app(UserPreferenceService::class);

        if (! $preferences->allowsCategory($notifiable, $this->category)) {
            return [];
        }

        $channels = [];

        if ($preferences->allowsChannel($notifiable, 'database')) {
            $channels[] = 'database';
        }

        if ($preferences->allowsChannel($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if ($this->url !== null) {
            $mail->action(__('Open in :app', ['app' => config('app.name')]), url($this->url));
        }

        return $mail;
    }
}
