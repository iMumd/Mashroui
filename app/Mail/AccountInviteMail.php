<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** رسالة دعوة لتفعيل الحساب — رابط تعيين كلمة المرور لأول مرة */
class AccountInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'دعوة لتفعيل حسابك - مسار');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invite',
            text: 'emails.text.invite',
        );
    }
}
