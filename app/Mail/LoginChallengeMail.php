<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginChallengeMail extends Mailable
{
    public function __construct(
        public readonly string $magicUrl,
        public readonly string $otp,
        public readonly CarbonInterface $expiresAt,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your MC Kit sign-in link and code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.login-challenge',
        );
    }
}
