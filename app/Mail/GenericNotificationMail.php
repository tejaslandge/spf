<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Generic notification mailable for business events.
 * Used when specific mailables don't cover the use case.
 */
class GenericNotificationMail extends Mailable
{
    public string $subject;
    public string $view;
    public array $data;

    /**
     * Create a new message instance.
     *
     * @param  string  $subject
     * @param  string  $view
     * @param  array  $data
     * @return void
     */
    public function __construct(string $subject, string $view, array $data = [])
    {
        $this->subject = $subject;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view($this->view)->with($this->data);
    }
}