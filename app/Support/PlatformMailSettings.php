<?php

namespace App\Support;

use App\Support\Mail\PlatformMailRateLimitException;
use App\Support\Mail\PlatformMailUnavailableException;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class PlatformMailSettings
{
    public function __construct(
        protected SystemSettings $settings,
        protected MailFactory $mail,
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->mailEnabled();
    }

    public function driver(): string
    {
        return $this->settings->mailDriver();
    }

    public function configured(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->driver() === 'log') {
            return $this->fromAddress() !== '' && $this->fromName() !== '';
        }

        if ($this->host() === '' || $this->port() <= 0 || $this->fromAddress() === '' || $this->fromName() === '') {
            return false;
        }

        $username = $this->username();
        $password = $this->password();

        if ($username === '' && $password !== '') {
            return false;
        }

        if ($username !== '' && $password === '') {
            return false;
        }

        return true;
    }

    public function apply(): void
    {
        Config::set('mail.default', $this->driver());
        Config::set('mail.from.address', $this->fromAddress());
        Config::set('mail.from.name', $this->fromName());

        $mailer = [
            'transport' => $this->driver(),
            'from' => [
                'address' => $this->fromAddress(),
                'name' => $this->fromName(),
            ],
        ];

        if ($this->replyToAddress() !== '') {
            $mailer['reply_to'] = [
                'address' => $this->replyToAddress(),
                'name' => $this->fromName(),
            ];
        }

        if ($this->driver() === 'smtp') {
            $mailer = array_merge($mailer, [
                'host' => $this->host(),
                'port' => $this->port(),
                'username' => $this->username() !== '' ? $this->username() : null,
                'password' => $this->password() !== '' ? $this->password() : null,
                'timeout' => $this->timeoutSeconds(),
                'scheme' => $this->scheme(),
            ]);
        }

        Config::set("mail.mailers.{$this->driver()}", array_merge(
            (array) config("mail.mailers.{$this->driver()}", []),
            $mailer,
        ));

        if ($this->mail instanceof MailManager) {
            $this->mail->forgetMailers();
        }
    }

    public function sendTestMail(string $to): string
    {
        if (! $this->enabled()) {
            throw new PlatformMailUnavailableException('请先开启邮件服务。');
        }

        if (! $this->configured()) {
            throw new PlatformMailUnavailableException('邮件服务配置不完整，请先保存完整配置后再测试。');
        }

        $this->send(
            $to,
            new \App\Mail\PlatformTestMail(
                $this->fromName(),
                $this->driver(),
            ),
            [
                'scene' => 'platform_test',
                'site_id' => null,
            ],
        );

        return $this->driver();
    }

    public function send(string $to, Mailable $mailable, array $context = []): void
    {
        if (! $this->enabled()) {
            throw new PlatformMailUnavailableException('请先开启邮件服务。');
        }

        if (! $this->configured()) {
            throw new PlatformMailUnavailableException('邮件服务配置不完整，请先保存完整配置后再发送。');
        }

        $normalizedTo = $this->normalizeRecipient($to);

        if ($normalizedTo === '') {
            throw new RuntimeException('收件邮箱不能为空。');
        }

        $this->apply();
        $this->guardRateLimit($normalizedTo, $context);

        $this->mail->mailer($this->driver())->to($normalizedTo)->send($mailable);
    }

    public function host(): string
    {
        return $this->settings->mailHost();
    }

    public function port(): int
    {
        return $this->settings->mailPort();
    }

    public function username(): string
    {
        return $this->settings->mailUsername();
    }

    public function fromAddress(): string
    {
        return $this->settings->mailFromAddress();
    }

    public function fromName(): string
    {
        return $this->settings->mailFromName();
    }

    public function replyToAddress(): string
    {
        return $this->settings->mailReplyToAddress();
    }

    public function timeoutSeconds(): int
    {
        return $this->settings->mailTimeoutSeconds();
    }

    public function rateLimitEnabled(): bool
    {
        return $this->settings->mailRateLimitEnabled();
    }

    public function rateLimitWindowSeconds(): int
    {
        return $this->settings->mailRateLimitWindowSeconds();
    }

    public function rateLimitGlobalMax(): int
    {
        return $this->settings->mailRateLimitGlobalMax();
    }

    public function rateLimitSiteMax(): int
    {
        return $this->settings->mailRateLimitSiteMax();
    }

    public function rateLimitSceneMax(): int
    {
        return $this->settings->mailRateLimitSceneMax();
    }

    public function rateLimitRecipientWindowSeconds(): int
    {
        return $this->settings->mailRateLimitRecipientWindowSeconds();
    }

    public function rateLimitRecipientMax(): int
    {
        return $this->settings->mailRateLimitRecipientMax();
    }

    public function encryptPassword(string $value): string
    {
        return $value === '' ? '' : 'enc:'.Crypt::encryptString($value);
    }

    public function password(): string
    {
        $encrypted = $this->settings->mailPasswordEncrypted();

        if ($encrypted === '') {
            return '';
        }

        if (! str_starts_with($encrypted, 'enc:')) {
            return '';
        }

        return (string) Crypt::decryptString(substr($encrypted, 4));
    }

    protected function scheme(): ?string
    {
        return match ($this->settings->mailEncryption()) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };
    }

    protected function guardRateLimit(string $to, array $context = []): void
    {
        if (! $this->rateLimitEnabled()) {
            return;
        }

        $scene = $this->normalizeScene((string) ($context['scene'] ?? 'generic'));
        $siteId = $this->normalizeSiteId($context['site_id'] ?? null);
        $windowSeconds = $this->rateLimitWindowSeconds();
        $recipientWindowSeconds = $this->rateLimitRecipientWindowSeconds();

        $limits = [
            [
                'key' => 'platform-mail:global',
                'max' => $this->rateLimitGlobalMax(),
                'window' => $windowSeconds,
                'label' => '平台总发送',
            ],
            [
                'key' => 'platform-mail:scene:'.$scene,
                'max' => $this->rateLimitSceneMax(),
                'window' => $windowSeconds,
                'label' => '当前发送场景',
            ],
            [
                'key' => 'platform-mail:recipient:'.sha1($to),
                'max' => $this->rateLimitRecipientMax(),
                'window' => $recipientWindowSeconds,
                'label' => '当前收件人',
            ],
        ];

        if ($siteId !== null) {
            $limits[] = [
                'key' => 'platform-mail:site:'.$siteId,
                'max' => $this->rateLimitSiteMax(),
                'window' => $windowSeconds,
                'label' => '当前站点',
            ];
        }

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                $seconds = RateLimiter::availableIn($limit['key']);

                Log::warning('platform_mail_rate_limited', [
                    'key' => $limit['key'],
                    'label' => $limit['label'],
                    'scene' => $scene,
                    'site_id' => $siteId,
                    'to_sha1' => sha1($to),
                    'available_in' => $seconds,
                ]);

                throw new PlatformMailRateLimitException($limit['label'].'发送过于频繁，请在 '.$seconds.' 秒后重试。');
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $limit['window']);
        }
    }

    protected function normalizeRecipient(string $to): string
    {
        return strtolower(trim($to));
    }

    protected function normalizeScene(string $scene): string
    {
        $normalized = preg_replace('/[^a-z0-9:_-]+/i', '-', strtolower(trim($scene))) ?? '';

        return $normalized !== '' ? $normalized : 'generic';
    }

    protected function normalizeSiteId(mixed $siteId): ?int
    {
        if ($siteId === null || $siteId === '') {
            return null;
        }

        $normalized = (int) $siteId;

        return $normalized > 0 ? $normalized : null;
    }
}
