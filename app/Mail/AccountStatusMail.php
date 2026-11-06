<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

/**
 * Email sent when account status changes (blocked, activated, deactivated).
 */
class AccountStatusMail extends Mailable
{
    public User $user;
    public string $oldStatus;
    public string $newStatus;
    public string $reason;
    public ?User $changedBy;

    /**
     * Create a new message instance.
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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $statusDisplay = ucfirst(str_replace('_', ' ', $newStatus));

        $this->subject('Account Status Updated - '.Branding::name());

        return $this->view('emails.account.account-status')
            ->with([
                'user' => $this->user,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'statusDisplay' => $statusDisplay,
                'reason' => $this->reason,
                'changedBy' => $this->changedBy?->name ?? 'Administrator',
                'appName' => Branding::name(),
                'supportEmail' => config('mail.from.address'),
            ]);
    }
}