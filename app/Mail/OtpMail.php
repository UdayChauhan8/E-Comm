<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your OTP Code - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }
}