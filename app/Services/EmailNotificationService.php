<?php

namespace App\Services;

use App\Mail\AccountStatusMail;
use App\Mail\GenericNotificationMail;
use App\Mail\LoginOtpMail;
use App\Mail\PasswordChangedMail;
use App\Mail\WelcomeMail;
use App\Notifications\AccountNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\SecurityNotification;
use App\Notifications\TwoStepLoginCompletedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Centralized email notification service.
 *
 * Provides a reusable, consistent interface for sending email notifications
 * across all application modules. Handles queue dispatch, recipient validation,
 * and consistent logging. SMTP credentials are resolved from .env only.
 *
 * Usage:
 *   app(EmailNotificationService::class)->send(
 *       $user->email,
 *       'Welcome to our platform',
 *       'emails.auth.welcome',
 *       ['user' => $user]
 *   );
 *
 * Or use the convenience methods:
 *   app(EmailNotificationService::class)->welcome($user);
 *   app(EmailNotificationService::class)->passwordChanged($user);
 */
final class EmailNotificationService
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * Send an email notification to a recipient.
     *
     * @param  string  $recipientEmail
     * @param  string  $subject
     * @param  string  $view
     * @param  array  $data
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     * @throws \Exception
     */
    public function send(
        string $recipientEmail,
        string $subject,
        string $view,
        array $data = [],
        bool $queue = true
    ) {
        // Validate recipient email
        if (! $this->isValidEmail($recipientEmail)) {
            Log::warning('Email notification skipped - invalid recipient: '.$recipientEmail);

            return false;
        }

        // Check if email sending is enabled via settings
        $emailEnabled = $this->isEmailEnabled();

        if (! $emailEnabled) {
            Log::info('Email notification skipped - email disabled in settings');

            return false;
        }

        $data['recipientEmail'] = $recipientEmail;
        $data['appName'] = Branding::name();
        $data['supportEmail'] = config('mail.from.address');

        if ($queue) {
            return Queue::push(
                new class($subject, $view, $data) implements ShouldQueue {
                    use Dispatchable, InteractsWithQueue, SerializesModels;

                    public string $subject;
                    public string $view;
                    public array $data;

                    public function __construct(string $subject, string $view, array $data)
                    {
                        $this->subject = $subject;
                        $this->view = $view;
                        $this->data = $data;
                    }

                    /**
                     * Run the job.
                     *
                     * @return void
                     */
                    public function handle(): void
                    {
                        try {
                            Mail::send($this->view, $this->data, function ($message) {
                                $message->to($this->data['recipientEmail'])
                                    ->subject($this->subject)
                                    ->from(config('mail.from.address'), $this->data['appName']);
                            });

                            // Log successful delivery
                            Log::info('Email sent successfully to: '.$this->data['recipientEmail'],
                                ['subject' => $this->subject, 'view' => $this->view]);
                        } catch (\Throwable $e) {
                            // Log the failure but don't break the calling code
                            Log::error('Email sending failed to: '.$this->data['recipientEmail'],
                                ['error' => $e->getMessage(), 'subject' => $this->subject]);

                            // Optionally re-throw or handle retry logic
                            throw $e;
                        }
                    }
                }
            );
        }

        // Sync delivery (for testing or immediate send)
        try {
            Mail::send($view, $data, function ($message) use ($recipientEmail, $subject) {
                $message->to($recipientEmail)
                    ->subject($subject)
                    ->from(config('mail.from.address'), Branding::name());
            });

            Log::info('Email sent synchronously to: '.$recipientEmail,
                ['subject' => $subject, 'view' => $view]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Email sending failed synchronously: '.$recipientEmail,
                ['error' => $e->getMessage(), 'subject' => $subject]);

            throw $e;
        }
    }

    /**
     * Validate email recipient format.
     *
     * @param  string  $email
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if email notifications are enabled globally.
     *
     * @return bool
     */
    private function isEmailEnabled(): bool
    {
        // Check global setting if available
        try {
            $enabled = config('mail.from.address') && config('mail.from.address') !== 'hello@example.com';

            // You could also check a centralized setting here:
            // $enabled = app(SettingsService::class)->getBool('notifications.email_enabled', true);

            return $enabled;
        } catch (\Throwable) {
            return true; // Default to enabled if settings not available
        }
    }

    /**
     * Send welcome email to a new user.
     *
     * @param  \App\Models\User  $user
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function welcome(User $user, bool $queue = true)
    {
        return $this->send(
            $user->email,
            'Welcome to '.Branding::name(),
            'emails.auth.welcome',
            ['user' => $user],
            $queue
        );
    }

    /**
     * Send login OTP/alert email.
     *
     * @param  \App\Models\User  $user
     * @param  string  $deviceInfo
     * @param  bool  $wasNewDevice
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function loginOtp(User $user, string $deviceInfo = '', bool $wasNewDevice = false, bool $queue = true)
    {
        return $this->send(
            $user->email,
            $wasNewDevice ? 'New device login detected' : 'Login successful',
            'emails.auth.login-alert',
            [
                'user' => $user,
                'deviceInfo' => $deviceInfo,
                'wasNewDevice' => $wasNewDevice,
            ],
            $queue
        );
    }

    /**
     * Send password changed email.
     *
     * @param  \App\Models\User  $user
     * @param  string  $initiatedFrom
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function passwordChanged(User $user, string $initiatedFrom = 'account settings', bool $queue = true)
    {
        return $this->send(
            $user->email,
            'Password Changed - '.Branding::name(),
            'emails.auth.password-changed',
            [
                'user' => $user,
                'initiatedFrom' => $initiatedFrom,
            ],
            $queue
        );
    }

    /**
     * Send account status change email.
     *
     * @param  \App\Models\User  $user
     * @param  string  $oldStatus
     * @param  string  $newStatus
     * @param  string  $reason
     * @param  ?\App\Models\User  $changedBy
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function accountStatus(User $user, string $oldStatus, string $newStatus, string $reason = '', ?User $changedBy = null, bool $queue = true)
    {
        return $this->send(
            $user->email,
            'Account Status Updated - '.ucfirst($newStatus).' - '.Branding::name(),
            'emails.account.account-status',
            [
                'user' => $user,
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus,
                'reason' => $reason,
                'changedBy' => $changedBy,
            ],
            $queue
        );
    }

    /**
     * Send password reset email.
     *
     * @param  \App\Models\User  $user
     * @param  string  $type
     * @param  string  $tokenUrl
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function passwordReset(User $user, string $type, string $tokenUrl, bool $queue = true)
    {
        return $this->send(
            $user->email,
            'Password '.$type.' - '.Branding::name(),
            'emails.auth.password-reset',
            [
                'user' => $user,
                'type' => $type,
                'tokenUrl' => $tokenUrl,
            ],
            $queue
        );
    }

    /**
     * Send two-step login completion email.
     *
     * @param  \App\Models\User  $user
     * @param  string  $method
     * @param  string  $deviceInfo
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function twoStepLoginCompleted(User $user, string $method, string $deviceInfo = '', bool $queue = true)
    {
        return $this->send(
            $user->email,
            'Login Confirmation - '.Branding::name(),
            'emails.auth.two-step-completed',
            [
                'user' => $user,
                'method' => $method,
                'deviceInfo' => $deviceInfo,
            ],
            $queue
        );
    }

    /**
     * Send a generic business notification email.
     *
     * @param  string  $subject
     * @param  string  $view
     * @param  array  $data
     * @param  bool  $queue
     * @return \Illuminate\Queue\Job|bool
     */
    public function business(string $subject, string $view, array $data = [], bool $queue = true)
    {
        return $this->send(
            $data['recipientEmail'] ?? '',
            $subject,
            $view,
            array_merge($data, [
                'appName' => Branding::name(),
                'supportEmail' => config('mail.from.address'),
            ]),
            $queue
        );
    }

    /**
     * Check for duplicate notifications and prevent dispatch.
     * Uses a simple cache-based dedup mechanism.
     *
     * @param  string  $key
     * @param  int  $minutes
     * @return bool
     */
    public function isDuplicate(string $key, int $minutes = 60): bool
    {
        $cacheKey = 'email_notification_dup_'.$key;
        return cache()->has($cacheKey);
    }

    public function markAsDuplicate(string $key): void
    {
        $cacheKey = 'email_notification_dup_'.$key;
        cache()->put($cacheKey, true, 60);
    }
}