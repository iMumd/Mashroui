<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** رسالة إعادة تعيين كلمة المرور — رابط صالح لمدة ساعتين */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'إعادة تعيين كلمة المرور - مسار');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            text: 'emails.text.password-reset',
        );
    }
}
