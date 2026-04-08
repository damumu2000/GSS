<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->first(['sites.*']);

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

        if ($rule = $this->matchBadPath($request)) {
            return $rule;
        }

        if ($rule = $this->matchPathTraversal($request)) {
            return $rule;
        }

        if ($rule = $this->matchBadUpload($request)) {
            return $rule;
        }

        if ($rule = $this->matchSqlInjection($request)) {
            return $rule;
        }

        if ($rule = $this->matchXss($request)) {
            return $rule;
        }

        if ($rule = $this->matchRateLimit($request, (int) $site->id)) {
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
            'region_name' => $request->ip() ? $this->resolveAttackRegionLabelCached((string) $request->ip()) : null,
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
            ->first();

        $types = collect([
            ['label' => '恶意扫描', 'value' => (int) ($typeTotals->bad_path ?? 0)],
            ['label' => 'SQL 注入', 'value' => (int) ($typeTotals->sql_injection ?? 0)],
            ['label' => 'XSS 攻击', 'value' => (int) ($typeTotals->xss ?? 0)],
            ['label' => '路径穿越', 'value' => (int) ($typeTotals->path_traversal ?? 0)],
            ['label' => '可疑上传', 'value' => (int) ($typeTotals->bad_upload ?? 0)],
            ['label' => '频繁刷新', 'value' => (int) ($typeTotals->rate_limit ?? 0)],
        ])->filter(fn (array $item): bool => $item['value'] > 0)
            ->map(fn (array $item) => [
                ...$item,
                'ratio' => $sevenDayTotal > 0 ? (int) round(($item['value'] / $sevenDayTotal) * 100) : 0,
            ])
            ->values()
            ->all();

        $events = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
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
            $label = trim((string) ($row->region_name ?? '')) ?: $this->resolveAttackRegionLabel((string) $row->client_ip);
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
        $needles = [
            '.env',
            'phpinfo.php',
            'wp-admin',
            'wp-login',
            'vendor/phpunit',
            'boaform',
            'hnap1',
            'manager/html',
        ];

        foreach ($needles as $needle) {
            if (str_contains($path, $needle)) {
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

        $payload = mb_strtolower($this->requestFingerprintText($request));
        $needles = ['union select', 'sleep(', 'benchmark(', 'or 1=1', 'and 1=1', 'information_schema', 'updatexml(', 'extractvalue('];

        foreach ($needles as $needle) {
            if (str_contains($payload, $needle)) {
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

        $payload = mb_strtolower($this->requestFingerprintText($request));
        $needles = ['<script', 'javascript:', 'onerror=', 'onload=', 'alert(', '<img', '<svg'];

        foreach ($needles as $needle) {
            if (str_contains($payload, $needle)) {
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
                $extension = mb_strtolower((string) ($uploadedFile?->getClientOriginalExtension() ?: ''));

                if (in_array($extension, ['php', 'phtml', 'phar', 'jsp', 'asp', 'aspx'], true)) {
                    return ['code' => 'bad_upload', 'name' => '可疑上传拦截'];
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

        $window = $this->systemSettings->securityRateLimitWindowSeconds();
        $maxAttempts = $this->isSensitiveRequest($request)
            ? $this->systemSettings->securityRateLimitSensitiveMaxRequests()
            : $this->systemSettings->securityRateLimitMaxRequests();

        $key = 'site-security-rate:'.$siteId.':'.sha1(
            ($request->ip() ?: 'guest').'|'.mb_strtolower($this->normalizedRequestPath($request))
        );

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
        }

        RateLimiter::hit($key, $window);

        return null;
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

        if (function_exists('geoip_record_by_name')) {
            try {
                $record = @geoip_record_by_name($ip);
                if (is_array($record)) {
                    $region = trim((string) ($record['region'] ?? ''));
                    $city = trim((string) ($record['city'] ?? ''));
                    $country = trim((string) ($record['country_name'] ?? ''));
                    $label = $region !== '' ? $region : ($city !== '' ? $city : $country);
                    if ($label !== '') {
                        return $label;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return '公网来源';
    }

    protected function resolveAttackRegionLabelCached(string $ip): string
    {
        $ip = trim($ip);

        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return $this->resolveAttackRegionLabel($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $this->resolveAttackRegionLabel($ip);
        }

        $cacheKey = 'site-security:geoip-region:'.hash('sha256', $ip);

        return Cache::remember($cacheKey, now('Asia/Shanghai')->addDays(7), function () use ($ip): string {
            return $this->resolveAttackRegionLabel($ip);
        });
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
