<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;

/**
 * Welcome email sent to new users upon registration.
 */
class WelcomeMail extends BaseMail
{
    public User $user;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject('Welcome to '.Branding::name());

        return $this->view('emails.auth.welcome')
            ->with([
                'user' => $this->user,
                'appName' => Branding::name(),
                'supportEmail' => config('mail.from.address'),
            ]);
    }
}