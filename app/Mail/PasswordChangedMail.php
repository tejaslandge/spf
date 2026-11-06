<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

/**
 * Email sent when a user changes their password.
 * Contains no password information for security.
 */
class PasswordChangedMail extends Mailable
{
    public User $user;
    public string $initiatedFrom;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $initiatedFrom
     * @return void
     */
    public function __construct(User $user, string $initiatedFrom = 'account settings')
    {
        $this->user = $user;
        $this->initiatedFrom = $initiatedFrom;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject('Password Changed - '.Branding::name());

        return $this->view('emails.auth.password-changed')
            ->with([
                'user' => $this->user,
                'initiatedFrom' => $this->initiatedFrom,
                'appName' => Branding::name(),
                'supportEmail' => config('mail.from.address'),
            ]);
    }
}