<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestbookMessageNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $siteName,
        public string $trigger,
        public int $displayNo,
        public string $name,
        public string $phone,
        public string $contentText,
        public string $status,
        public string $createdAt,
        public string $replyContent = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【'.$this->siteName.'】收到新的留言反馈',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guestbook-message-notification',
        );
    }
}
