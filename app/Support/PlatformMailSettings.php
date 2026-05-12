<?php

namespace App\Support;

use App\Support\Mail\PlatformMailRateLimitException;
use App\Support\Mail\PlatformMailUnavailableException;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PlatformMailSettings
{
    protected const QUEUE_WORKER_HEARTBEAT_CACHE_KEY = 'platform-mail:queue-worker:heartbeat';

    protected const QUEUE_WORKER_HEARTBEAT_TTL_SECONDS = 600;

    protected const LAST_FAILURE_CACHE_KEY = 'platform-mail:last-failure';

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

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));
        $requiresWorker = ! in_array($queueConnection, ['sync', 'null'], true);
        $jobsTableExists = Schema::hasTable('jobs');
        $failedJobsTableExists = Schema::hasTable('failed_jobs');
        $pendingJobs = $queueConnection === 'database' && $jobsTableExists
            ? (int) DB::table('jobs')->count()
            : null;
        $failedJobs = $failedJobsTableExists
            ? (int) DB::table('failed_jobs')->count()
            : null;

        $heartbeat = Cache::get(self::QUEUE_WORKER_HEARTBEAT_CACHE_KEY);
        $lastSeenTimestamp = is_array($heartbeat) ? (int) ($heartbeat['timestamp'] ?? 0) : 0;
        $lastSeenAt = $lastSeenTimestamp > 0 ? date('Y-m-d H:i:s', $lastSeenTimestamp) : '';
        $workerActive = $requiresWorker && $lastSeenTimestamp > 0 && (time() - $lastSeenTimestamp) <= self::QUEUE_WORKER_HEARTBEAT_TTL_SECONDS;

        [$status, $message, $suggestion] = match (true) {
            ! $requiresWorker => [
                'ok',
                '当前队列模式不依赖独立 worker，邮件任务会直接在请求内执行。',
                '',
            ],
            $queueConnection === 'database' && ! $jobsTableExists => [
                'error',
                '当前队列连接为 database，但未检测到 jobs 表，异步任务无法入队。',
                '请先执行数据库迁移，确保 jobs / failed_jobs 表存在。',
            ],
            $workerActive => [
                'ok',
                '已检测到活跃 queue worker，异步邮件任务会自动消费执行。',
                '',
            ],
            $pendingJobs !== null && $pendingJobs > 0 => [
                'error',
                '检测到待执行队列任务，但当前未检测到活跃 queue worker。',
                '请先启动 queue worker，否则邮件通知只会堆积在 jobs 表中。',
            ],
            default => [
                'warning',
                '当前未检测到最近可用的 queue worker 心跳。',
                '如果你刚启动了 worker，请先重启 worker 让新心跳逻辑生效，再刷新当前页面。',
            ],
        };

        return [
            'queue_connection' => $queueConnection,
            'requires_worker' => $requiresWorker,
            'worker_active' => $workerActive,
            'last_seen_at' => $lastSeenAt,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'status' => $status,
            'message' => $message,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastFailure(): ?array
    {
        $failure = Cache::get(self::LAST_FAILURE_CACHE_KEY);

        return is_array($failure) ? $failure : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function rememberFailure(string $type, string $message, array $context = []): void
    {
        Cache::forever(self::LAST_FAILURE_CACHE_KEY, [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'occurred_at' => now('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ]);
    }

    public static function recordQueueWorkerHeartbeat(): void
    {
        Cache::put(self::QUEUE_WORKER_HEARTBEAT_CACHE_KEY, [
            'timestamp' => time(),
            'pid' => getmypid(),
            'connection' => (string) config('queue.default', 'sync'),
        ], now()->addSeconds(self::QUEUE_WORKER_HEARTBEAT_TTL_SECONDS * 2));
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
