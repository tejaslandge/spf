<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Base mailable class providing common email layout and branding.
 *
 * All application mailables should extend this class to ensure
 * consistent branding, responsive layout, and plain-text fallback.
 */
abstract class BaseMail extends Mailable
{
    use Queueable;
    use ShouldQueue;
    use SerializesModels;

    /**
     * The subject line for the email.
     *
     * @var string
     */
    public $subject;

    /**
     * Build the mail message.
     *
     * @return $this
     */
    abstract public function build();

    /**
     * Get the plain-text version of the email.
     *
     * @return string
     */
    public function generatePlainText($view, $data)
    {
        $viewContent = $this->view()->renderContents($view, $data);

        // Simple strip HTML tags for plain text fallback
        $text = strip_tags($viewContent);
        // Better: convert HTML to text preserving meaningful content
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim(preg_replace('/<br\s*\\/?>/', "\n", $text));
        $text = trim(preg_replace('/<[^>]*>/', '', $text));

        return $text;
    }

    /**
     * Get the HTML email header with branding.
     *
     * @return string
     */
    public function htmlHeader($view, $data = [])
    {
        $logoUrl = Branding::logoUrl();
        $companyName = Branding::name();

        $logoHtml = $logoUrl
            ? '<div style="text-align: center; margin: 20px 0;">'.
                '<img src="'.e($logoUrl).'" alt="'.e($companyName).'" style="max-width: 200px; height: auto;">'.
                '</div>'
            : '<div style="text-align: center; margin: 20px 0; font-size: 0;">&nbsp;</div>';

        $companyNameHtml = '<h1 style="color: #1a365d; font-size: 24px; font-weight: bold; margin: 0; text-align: center; '
            .'letter-spacing: 1px;">'.e($companyName).'</h1>';

        return $logoHtml.$companyNameHtml;
    }
}