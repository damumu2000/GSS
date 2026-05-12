<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $fromName,
        public string $driver,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '系统邮件测试',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform-test',
        );
    }
}
