<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once after sign-up. Rendered in the user's language
 * (User::preferredLocale), Latvian by default.
 */
class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.welcome.subject'),
        );
    }

    public function content(): Content
    {
        $frontend = rtrim(config('app.frontend_url'), '/');

        return new Content(
            view: 'emails.welcome',
            text: 'emails.welcome-text',
            with: [
                'name' => $this->user->name,
                'appUrl' => $frontend.'/home',
                'siteUrl' => $frontend,
                'logoUrl' => $frontend.'/email/logo.png',
            ],
        );
    }
}
