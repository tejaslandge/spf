<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

/**
 * OTP login notification sent when a user completes two-step login.
 * Does NOT contain the OTP code for SMS-based second factor security.
 */
class LoginOtpMail extends Mailable
{
    public User $user;
    public string $deviceInfo;
    public bool $wasNewDevice;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $deviceInfo
     * @param  bool  $wasNewDevice
     * @return void
     */
    public function __construct(User $user, string $deviceInfo = '', bool $wasNewDevice = false)
    {
        $this->user = $user;
        $this->deviceInfo = $deviceInfo;
        $this->wasNewDevice = $wasNewDevice;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject($this->wasNewDevice
            ? 'New device login detected'
            : 'Login successful');

        return $this->view('emails.auth.login-alert')
            ->with([
                'user' => $this->user,
                'deviceInfo' => $this->deviceInfo,
                'wasNewDevice' => $this->wasNewDevice,
                'appName' => Branding::name(),
            ]);
    }
}