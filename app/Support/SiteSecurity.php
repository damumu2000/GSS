<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SiteSecurity
{
    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    public function protectionEnabled(): bool
    {
        return $this->systemSettings->siteProtectionEnabled();
    }

    public function resolveSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            try {
                $site = Cache::remember('site-security:site-by-host:'.hash('sha256', $host), now('Asia/Shanghai')->addMinute(), function () use ($host): ?object {
                    return $this->siteByHost($host);
                });
            } catch (\Throwable $exception) {
                Log::warning('Site security host cache failed.', [
                    'host' => $host,
                    'message' => $exception->getMessage(),
                ]);

                $site = $this->siteByHost($host);
            }

            if ($site) {
                return $site;
            }
        }

        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $siteKey = trim((string) $request->query('site', ''));

            if ($siteKey !== '') {
                $site = DB::table('sites')
                    ->where('site_key', $siteKey)
                    ->where('status', 1)
                    ->first();

                if ($site) {
                    return $site;
                }
            }

            return DB::table('sites')
                ->where('status', 1)
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    public function inspect(Request $request, object $site): ?array
    {
        if (! $this->protectionEnabled()) {
            return null;
        }

        $siteId = (int) $site->id;

        if ($rule = $this->matchProbeBlock($request, $siteId)) {
            return $rule;
        }

        if (! $this->isMediaRequest($request) && ($rule = $this->matchBadPath($request))) {
            return $this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule;
        }

        if ($rule = $this->matchPathTraversal($request)) {
            return $this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule;
        }

        if ($this->isMediaRequest($request)) {
            return $this->matchRateLimit($request, $siteId);
        }

        if ($rule = $this->matchBadUpload($request)) {
            return $this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule;
        }

        if ($rule = $this->matchSqlInjection($request)) {
            return $this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule;
        }

        if ($rule = $this->matchXss($request)) {
            return $this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule;
        }

        if ($rule = $this->matchRateLimit($request, $siteId)) {
            return $rule;
        }

        return null;
    }

    public function recordBlocked(object $site, array $rule, Request $request): void
    {
        $siteId = (int) $site->id;
        $now = now('Asia/Shanghai');
        $date = $now->toDateString();
        $column = $this->statsColumn((string) $rule['code']);

        DB::table('site_security_daily_stats')->updateOrInsert(
            ['site_id' => $siteId, 'stat_date' => $date],
            [
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->where('stat_date', $date)
            ->update([
                'blocked_total' => DB::raw('blocked_total + 1'),
                $column => DB::raw($column.' + 1'),
                'updated_at' => $now,
            ]);

        DB::table('site_security_events')->insert([
            'site_id' => $siteId,
            'rule_code' => (string) $rule['code'],
            'rule_name' => (string) $rule['name'],
            'request_path' => $this->normalizedRequestPath($request),
            'request_method' => strtoupper((string) $request->method()),
            'client_ip' => $request->ip() ?: null,
            'region_name' => $request->ip() ? $this->resolveAttackRegionLabel((string) $request->ip()) : null,
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'created_at' => $now,
        ]);

        $this->schedulePruning($siteId);
    }

    public function dashboardSummary(int $siteId): array
    {
        $today = now('Asia/Shanghai')->toDateString();
        $todayBlocked = (int) DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->where('stat_date', $today)
            ->value('blocked_total');

        $totalBlocked = (int) DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->sum('blocked_total');

        return [
            'enabled' => $this->protectionEnabled(),
            'today_blocked' => $todayBlocked,
            'total_blocked' => $totalBlocked,
        ];
    }

    public function sitePagePayload(int $siteId): array
    {
        $today = now('Asia/Shanghai')->startOfDay();
        $sevenDaysAgo = $today->copy()->subDays(6);

        $rows = DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->whereBetween('stat_date', [$sevenDaysAgo->toDateString(), $today->toDateString()])
            ->orderBy('stat_date')
            ->get();

        $byDate = $rows->keyBy(fn (object $row): string => (string) $row->stat_date);
        $trend = collect(range(6, 0))
            ->map(function (int $offset) use ($today, $byDate): array {
                $date = $today->copy()->subDays($offset);
                $row = $byDate->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('m-d'),
                    'value' => (int) ($row->blocked_total ?? 0),
                ];
            })
            ->values();

        $peak = (int) $trend->max('value');
        $todayTotal = (int) ($byDate->get($today->toDateString())->blocked_total ?? 0);
        $total = (int) DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->sum('blocked_total');
        $sevenDayTotal = (int) $rows->sum('blocked_total');

        $typeTotals = DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->whereBetween('stat_date', [$sevenDaysAgo->toDateString(), $today->toDateString()])
            ->selectRaw('SUM(blocked_bad_path) as bad_path')
            ->selectRaw('SUM(blocked_sql_injection) as sql_injection')
            ->selectRaw('SUM(blocked_xss) as xss')
            ->selectRaw('SUM(blocked_path_traversal) as path_traversal')
            ->selectRaw('SUM(blocked_bad_upload) as bad_upload')
            ->selectRaw('SUM(blocked_rate_limit) as rate_limit')
            ->selectRaw('SUM(blocked_probe_abuse) as probe_abuse')
            ->first();

        $types = collect([
            ['code' => 'bad_path', 'label' => '恶意扫描', 'value' => (int) ($typeTotals->bad_path ?? 0)],
            ['code' => 'sql_injection', 'label' => 'SQL 注入', 'value' => (int) ($typeTotals->sql_injection ?? 0)],
            ['code' => 'xss', 'label' => 'XSS 攻击', 'value' => (int) ($typeTotals->xss ?? 0)],
            ['code' => 'path_traversal', 'label' => '路径穿越', 'value' => (int) ($typeTotals->path_traversal ?? 0)],
            ['code' => 'bad_upload', 'label' => '可疑上传', 'value' => (int) ($typeTotals->bad_upload ?? 0)],
            ['code' => 'rate_limit', 'label' => '频繁刷新', 'value' => (int) ($typeTotals->rate_limit ?? 0)],
            ['code' => 'probe_abuse', 'label' => '扫描试探超限', 'value' => (int) ($typeTotals->probe_abuse ?? 0)],
        ])->filter(fn (array $item): bool => $item['value'] > 0)
            ->map(fn (array $item) => [
                ...$item,
                'ratio' => $sevenDayTotal > 0 ? (int) round(($item['value'] / $sevenDayTotal) * 100) : 0,
                'note' => $this->typeDistributionNote((string) $item['code']),
            ])
            ->values()
            ->all();

        $highRiskRuleCodes = ['sql_injection', 'xss', 'path_traversal', 'bad_upload', 'probe_abuse'];

        $events = collect()
            ->concat(
                DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            )
            ->concat(
                DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->whereIn('rule_code', $highRiskRuleCodes)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            )
            ->concat(
                DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->where('rule_code', 'probe_abuse')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            )
            ->unique(fn (object $event): int => (int) $event->id)
            ->sortByDesc(fn (object $event): int => (int) $event->id)
            ->values()
            ->map(function (object $event): array {
                $profile = $this->eventProfile((string) $event->rule_code, (string) $event->rule_name);

                return [
                    'rule_name' => (string) $event->rule_name,
                    'rule_code' => (string) $event->rule_code,
                    'request_path' => (string) $event->request_path,
                    'request_method' => strtoupper((string) $event->request_method),
                    'client_ip' => (string) ($event->client_ip ?? '--'),
                    'created_at_label' => $event->created_at ? date('m-d H:i', strtotime((string) $event->created_at)) : '--',
                    'category_label' => $profile['category_label'],
                    'action_label' => $profile['action_label'],
                    'risk_label' => $profile['risk_label'],
                ];
            })
            ->all();

        $regionRows = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->whereNotNull('client_ip')
            ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString())
            ->orderByDesc('id')
            ->get(['client_ip', 'region_name']);

        $regionCounts = [];
        foreach ($regionRows as $row) {
            $label = trim((string) ($row->region_name ?? '')) ?: $this->resolveAttackRegionLabel((string) ($row->client_ip ?? ''));
            $regionCounts[$label] = ($regionCounts[$label] ?? 0) + 1;
        }

        arsort($regionCounts);
        $regionTotal = array_sum($regionCounts);
        $regions = collect($regionCounts)
            ->map(fn (int $value, string $label) => [
                'label' => $label,
                'value' => $value,
                'ratio' => $regionTotal > 0 ? (int) round(($value / $regionTotal) * 100) : 0,
            ])
            ->take(5)
            ->values()
            ->all();

        return [
            'enabled' => $this->protectionEnabled(),
            'today_blocked' => $todayTotal,
            'total_blocked' => $total,
            'status_label' => $this->protectionEnabled() ? '运行中' : '未启用',
            'status_tone' => $this->protectionEnabled() ? 'running' : 'disabled',
            'peak_blocked' => $peak,
            'trend' => $trend->all(),
            'types' => $types,
            'events' => $events,
            'regions' => $regions,
        ];
    }

    protected function matchBadPath(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockBadPathEnabled()) {
            return null;
        }

        $path = mb_strtolower($this->normalizedRequestPath($request));
        $exactOrChildPrefixes = [
            '/wp-admin',
            '/wp-login',
            '/vendor/phpunit',
            '/phpmyadmin',
            '/pma',
            '/adminer',
            '/actuator',
            '/swagger-ui',
            '/v2/api-docs',
            '/v3/api-docs',
            '/druid',
            '/jenkins',
            '/boaform',
            '/hnap1',
            '/manager/html',
            '/manager/status',
            '/server-status',
            '/thinkphp',
            '/runtime/log',
            '/storage/logs',
        ];
        $suffixMatches = [
            '/.env',
            '/.git/config',
            '/.svn/entries',
            '/phpinfo.php',
            '/config/database.php',
        ];

        foreach ($exactOrChildPrefixes as $prefix) {
            if ($this->pathMatchesPrefix($path, $prefix)) {
                return ['code' => 'bad_path', 'name' => '恶意扫描路径'];
            }
        }

        foreach ($suffixMatches as $suffix) {
            if ($path === $suffix || str_ends_with($path, $suffix)) {
                return ['code' => 'bad_path', 'name' => '恶意扫描路径'];
            }
        }

        return null;
    }

    protected function matchSqlInjection(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockSqlInjectionEnabled()) {
            return null;
        }

        $payload = $this->requestFingerprintText($request);
        $patterns = [
            '/\bunion\s+select\b/i',
            '/\bsleep\s*\(/i',
            '/\bbenchmark\s*\(/i',
            '/\bor\s+1\s*=\s*1\b/i',
            '/\band\s+1\s*=\s*1\b/i',
            '/\binformation_schema\b/i',
            '/\bupdatexml\s*\(/i',
            '/\bextractvalue\s*\(/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                return ['code' => 'sql_injection', 'name' => 'SQL 注入拦截'];
            }
        }

        return null;
    }

    protected function matchXss(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockXssEnabled()) {
            return null;
        }

        $payload = $this->requestFingerprintText($request);
        $patterns = [
            '/<script\b/i',
            '/javascript\s*:/i',
            '/on(?:error|load)\s*=/i',
            '/<img\b[^>]*(onerror\s*=|src\s*=\s*[\'"]?\s*javascript:)/i',
            '/<svg\b[^>]*(onload\s*=|onerror\s*=)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                return ['code' => 'xss', 'name' => 'XSS 攻击拦截'];
            }
        }

        return null;
    }

    protected function matchPathTraversal(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockPathTraversalEnabled()) {
            return null;
        }

        $payload = mb_strtolower(rawurldecode($request->getRequestUri() ?: ''));
        $needles = ['../', '..\\', '%2e%2e%2f', '%2e%2e\\'];

        foreach ($needles as $needle) {
            if (str_contains($payload, $needle)) {
                return ['code' => 'path_traversal', 'name' => '路径穿越拦截'];
            }
        }

        return null;
    }

    protected function matchBadUpload(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockBadUploadEnabled()) {
            return null;
        }

        foreach ($request->allFiles() as $file) {
            foreach ($this->flattenFiles($file) as $uploadedFile) {
                $originalName = mb_strtolower((string) ($uploadedFile?->getClientOriginalName() ?: ''));
                $extension = mb_strtolower((string) ($uploadedFile?->getClientOriginalExtension() ?: ''));
                $serverMime = mb_strtolower((string) ($uploadedFile?->getMimeType() ?: ''));
                $clientMime = mb_strtolower((string) ($uploadedFile?->getClientMimeType() ?: ''));

                if (in_array($extension, ['php', 'phtml', 'phar', 'jsp', 'asp', 'aspx'], true)) {
                    return ['code' => 'bad_upload', 'name' => '可疑上传拦截'];
                }

                if ($originalName !== '' && preg_match('/\.(php[0-9]*|phtml|phar|jsp|asp|aspx)(?:\.[a-z0-9_-]+)+$/i', $originalName) === 1) {
                    return ['code' => 'bad_upload', 'name' => '可疑上传拦截'];
                }

                foreach ([$serverMime, $clientMime] as $mime) {
                    if (in_array($mime, [
                        'application/x-httpd-php',
                        'application/x-httpd-php-source',
                        'application/x-php',
                        'application/php',
                        'text/php',
                        'text/x-php',
                        'application/x-jsp',
                        'text/x-jsp',
                        'application/x-asp',
                        'text/x-asp',
                    ], true)) {
                        return ['code' => 'bad_upload', 'name' => '可疑上传拦截'];
                    }
                }
            }
        }

        return null;
    }

    protected function matchRateLimit(Request $request, int $siteId): ?array
    {
        if (! $this->systemSettings->securityRateLimitEnabled()) {
            return null;
        }

        try {
            $window = $this->systemSettings->securityRateLimitWindowSeconds();
            $maxAttempts = $this->isSensitiveRequest($request)
                ? $this->systemSettings->securityRateLimitSensitiveMaxRequests()
                : $this->systemSettings->securityRateLimitMaxRequests();
            $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();
            $blockKey = $this->rateLimitBlockKey($siteId, $request);

            if ($blockSeconds > 0 && RateLimiter::tooManyAttempts($blockKey, 1)) {
                return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
            }

            if ($this->isFrontendPageRequest($request)) {
                $siteWideKey = 'site-security-rate:'.$siteId.':site:'.sha1($request->ip() ?: 'guest');

                if (RateLimiter::tooManyAttempts($siteWideKey, $maxAttempts)) {
                    if ($blockSeconds > 0) {
                        RateLimiter::hit($blockKey, $blockSeconds);
                    }

                    return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
                }

                RateLimiter::hit($siteWideKey, $window);
            }

            $key = 'site-security-rate:'.$siteId.':'.sha1(
                ($request->ip() ?: 'guest').'|'.mb_strtolower($this->normalizedRequestPath($request))
            );

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                if ($blockSeconds > 0) {
                    RateLimiter::hit($blockKey, $blockSeconds);
                }

                return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
            }

            RateLimiter::hit($key, $window);
        } catch (\Throwable $exception) {
            Log::warning('Site security rate limit cache failed.', [
                'site_id' => $siteId,
                'path' => $request->path(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return null;
    }

    protected function matchProbeBlock(Request $request, int $siteId): ?array
    {
        if (! $this->systemSettings->securityScanProbeEnabled()) {
            return null;
        }

        $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();

        if ($blockSeconds <= 0) {
            return null;
        }

        $blockKey = $this->probeBlockKey($siteId, $request);

        if (RateLimiter::tooManyAttempts($blockKey, 1)) {
            return ['code' => 'probe_abuse', 'name' => '扫描试探超限'];
        }

        return null;
    }

    protected function escalateProbeIfNeeded(Request $request, int $siteId, array $rule): ?array
    {
        if (! $this->systemSettings->securityScanProbeEnabled()) {
            return null;
        }

        if (! $this->isProbeCandidateRule((string) ($rule['code'] ?? ''))) {
            return null;
        }

        $window = $this->systemSettings->securityScanProbeWindowSeconds();
        $threshold = $this->systemSettings->securityScanProbeThreshold();
        $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();
        $totalKey = $this->probeTotalKey($siteId, $request);
        $ruleKey = $this->probeRuleKey($siteId, $request, (string) $rule['code']);

        RateLimiter::hit($totalKey, $window);
        RateLimiter::hit($ruleKey, $window);

        if (! RateLimiter::tooManyAttempts($totalKey, $threshold) && ! RateLimiter::tooManyAttempts($ruleKey, $threshold)) {
            return null;
        }

        if ($blockSeconds > 0) {
            RateLimiter::hit($this->probeBlockKey($siteId, $request), $blockSeconds);
        }

        return ['code' => 'probe_abuse', 'name' => '扫描试探超限'];
    }

    protected function siteByHost(string $host): ?object
    {
        return DB::table('site_domains')
            ->join('sites', 'sites.id', '=', 'site_domains.site_id')
            ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
            ->where('site_domains.status', 1)
            ->where('sites.status', 1)
            ->first(['sites.*']);
    }

    protected function isFrontendPageRequest(Request $request): bool
    {
        if (! in_array(strtoupper((string) $request->method()), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = trim((string) $request->path(), '/');

        return $path === ''
            || str_starts_with($path, 'cat/')
            || str_starts_with($path, 'article/')
            || str_starts_with($path, 'page/');
    }

    protected function isSensitiveRequest(Request $request): bool
    {
        $path = '/'.trim($request->path(), '/');

        if (in_array(strtoupper((string) $request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        foreach (['/guestbook', '/payroll', '/login'] as $needle) {
            if (str_starts_with($path, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function isMediaRequest(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        return $path === 'site-media'
            || str_starts_with($path, 'site-media/')
            || $path === 'atts'
            || str_starts_with($path, 'atts/');
    }

    protected function requestFingerprintText(Request $request): string
    {
        $parts = [$request->path()];

        foreach ([$request->query(), $request->request->all()] as $input) {
            array_walk_recursive($input, function ($value, $key) use (&$parts): void {
                if (is_scalar($value) || $value === null) {
                    $parts[] = $key.'='.(string) $value;
                }
            });
        }

        return mb_substr(implode(' ', array_filter($parts, fn ($value): bool => (string) $value !== '')), 0, 2048);
    }

    protected function normalizedRequestPath(Request $request): string
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        return $path === '//' ? '/' : mb_substr($path, 0, 255);
    }

    protected function statsColumn(string $code): string
    {
        return match ($code) {
            'bad_path' => 'blocked_bad_path',
            'sql_injection' => 'blocked_sql_injection',
            'xss' => 'blocked_xss',
            'path_traversal' => 'blocked_path_traversal',
            'bad_upload' => 'blocked_bad_upload',
            'rate_limit' => 'blocked_rate_limit',
            'probe_abuse' => 'blocked_probe_abuse',
            default => 'blocked_total',
        };
    }

    protected function eventProfile(string $ruleCode, string $ruleName): array
    {
        return match ($ruleCode) {
            'sql_injection' => [
                'category_label' => 'SQL 注入攻击',
                'action_label' => 'WAF 应用层规则已阻断',
                'risk_label' => '高危',
            ],
            'xss' => [
                'category_label' => 'XSS 脚本攻击',
                'action_label' => 'WAF 应用层规则已阻断',
                'risk_label' => '高危',
            ],
            'path_traversal' => [
                'category_label' => '路径穿越探测',
                'action_label' => '目录越界访问已阻断',
                'risk_label' => '高危',
            ],
            'bad_upload' => [
                'category_label' => '可疑上传尝试',
                'action_label' => '危险文件上传已阻断',
                'risk_label' => '高危',
            ],
            'probe_abuse' => [
                'category_label' => '扫描试探超限',
                'action_label' => '多次命中扫描规则后已临时限制访问',
                'risk_label' => '高危',
            ],
            'rate_limit' => [
                'category_label' => '异常高频访问',
                'action_label' => '频率阈值命中已拦截',
                'risk_label' => '中危',
            ],
            'bad_path' => [
                'category_label' => '恶意扫描路径',
                'action_label' => '敏感路径探测已阻断',
                'risk_label' => '中危',
            ],
            default => [
                'category_label' => $ruleName !== '' ? $ruleName : '异常请求',
                'action_label' => '安全规则已拦截',
                'risk_label' => '中危',
            ],
        };
    }

    protected function typeDistributionNote(string $ruleCode): string
    {
        return match ($ruleCode) {
            'bad_path' => '常见目录探测、组件探测和规范性扫描目标。',
            'sql_injection' => '针对查询参数和表单字段的注入试探。',
            'xss' => '脚本注入、事件注入和可执行前端载荷尝试。',
            'path_traversal' => '尝试通过 ../ 等方式越级读取目录或文件。',
            'bad_upload' => '危险脚本或可执行文件上传尝试。',
            'rate_limit' => '短时间内高频访问触发的临时拦截。',
            'probe_abuse' => '同一来源多次命中扫描规则后升级限制访问。',
            default => '安护盾记录到的异常访问类型。',
        };
    }

    protected function resolveAttackRegionLabel(string $ip): string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return '未知来源';
        }

        if ($ip === '127.0.0.1' || $ip === '::1') {
            return '本地测试环境';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return '内网来源';
        }

        return '公网来源';
    }

    protected function pruneEvents(int $siteId): void
    {
        $limit = $this->systemSettings->securityEventRetentionLimit();

        $ids = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->skip($limit)
            ->limit(500)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('site_security_events')->whereIn('id', $ids->all())->delete();
        }
    }

    protected function pruneStats(int $siteId): void
    {
        $cutoff = now('Asia/Shanghai')
            ->subDays($this->systemSettings->securityStatsRetentionDays())
            ->toDateString();

        DB::table('site_security_daily_stats')
            ->where('site_id', $siteId)
            ->where('stat_date', '<', $cutoff)
            ->delete();
    }

    protected function schedulePruning(int $siteId): void
    {
        if (Cache::add('site-security:prune-events:'.$siteId, 1, now('Asia/Shanghai')->addMinutes(10))) {
            $this->pruneEvents($siteId);
        }

        if (Cache::add('site-security:prune-stats:'.$siteId, 1, now('Asia/Shanghai')->addHours(12))) {
            $this->pruneStats($siteId);
        }
    }

    protected function isProbeCandidateRule(string $code): bool
    {
        return in_array($code, ['bad_path', 'sql_injection', 'xss', 'path_traversal', 'bad_upload'], true);
    }

    protected function pathMatchesPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }

    protected function probeTotalKey(int $siteId, Request $request): string
    {
        return 'site-security-probe:'.$siteId.':'.sha1($request->ip() ?: 'guest');
    }

    protected function probeRuleKey(int $siteId, Request $request, string $ruleCode): string
    {
        return 'site-security-probe:'.$siteId.':'.$ruleCode.':'.sha1($request->ip() ?: 'guest');
    }

    protected function probeBlockKey(int $siteId, Request $request): string
    {
        return 'site-security-probe-block:'.$siteId.':'.sha1($request->ip() ?: 'guest');
    }

    protected function rateLimitBlockKey(int $siteId, Request $request): string
    {
        return 'site-security-rate-block:'.$siteId.':'.sha1($request->ip() ?: 'guest');
    }

    /**
     * @param  mixed  $files
     * @return Collection<int, \Illuminate\Http\UploadedFile>
     */
    protected function flattenFiles(mixed $files): Collection
    {
        if (is_array($files)) {
            return collect($files)->flatMap(fn ($item) => $this->flattenFiles($item));
        }

        if ($files instanceof \Illuminate\Http\UploadedFile) {
            return collect([$files]);
        }

        return collect();
    }
}
