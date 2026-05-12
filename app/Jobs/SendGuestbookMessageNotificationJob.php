<?php

namespace App\Jobs;

use App\Mail\GuestbookMessageNotificationMail;
use App\Modules\Guestbook\Support\GuestbookSettings;
use App\Support\PlatformMailSettings;
use App\Support\Mail\PlatformMailException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendGuestbookMessageNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $siteId,
        public int $messageId,
        public string $trigger,
    ) {
    }

    public function handle(PlatformMailSettings $platformMailSettings, GuestbookSettings $guestbookSettings): void
    {
        if (! $platformMailSettings->enabled() || ! $platformMailSettings->configured()) {
            return;
        }

        $settings = $guestbookSettings->forSite($this->siteId);
        if (! ($settings['email_notify_enabled'] ?? false)) {
            return;
        }

        if (($settings['email_notify_on'] ?? 'submitted') !== $this->trigger) {
            return;
        }

        $site = DB::table('sites')->where('id', $this->siteId)->first();
        $message = DB::table('module_guestbook_messages')
            ->where('site_id', $this->siteId)
            ->where('id', $this->messageId)
            ->first();

        if (! $site || ! $message) {
            return;
        }

        $recipient = $this->resolveRecipient($settings, $site);
        if ($recipient === '') {
            return;
        }

        try {
            $platformMailSettings->send(
                $recipient,
                new GuestbookMessageNotificationMail(
                    siteName: (string) ($site->name ?? ''),
                    trigger: $this->trigger,
                    displayNo: (int) ($message->display_no ?? 0),
                    name: (string) ($message->name ?? ''),
                    phone: (string) ($message->phone ?? ''),
                    contentText: (string) ($message->content ?? ''),
                    status: (string) ($message->status ?? 'pending'),
                    createdAt: (string) ($message->created_at ?? ''),
                    replyContent: (string) ($message->reply_content ?? ''),
                ),
                [
                    'scene' => 'guestbook_notification',
                    'site_id' => $this->siteId,
                ],
            );
        } catch (PlatformMailException $exception) {
            Log::warning('guestbook_notification_send_failed', [
                'site_id' => $this->siteId,
                'message_id' => $this->messageId,
                'trigger' => $this->trigger,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function resolveRecipient(array $settings, object $site): string
    {
        $moduleRecipient = strtolower(trim((string) ($settings['email_notify_to'] ?? '')));
        if ($moduleRecipient !== '') {
            return $moduleRecipient;
        }

        return strtolower(trim((string) ($site->contact_email ?? '')));
    }
}
