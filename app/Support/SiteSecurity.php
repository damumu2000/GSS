<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SiteSecurity
{
    protected const HIGH_RISK_RULE_CODES = ['sql_injection', 'xss', 'path_traversal', 'bad_upload', 'probe_abuse', 'ip_blocklist', 'bad_client', 'bad_payload'];

    protected const MALICIOUS_AUTO_BLOCK_RULE_CODES = ['bad_path', 'sql_injection', 'xss', 'path_traversal', 'bad_upload', 'probe_abuse', 'bad_client', 'bad_method', 'bad_payload'];

    protected const EVENT_SAMPLE_WINDOW_SECONDS = 600;

    protected const EVENT_PRUNE_BATCH_SIZE = 1000;

    /**
     * @var array<string, bool>
     */
    protected static array $columnExistsCache = [];

    protected static ?bool $ipReputationTableReady = null;

    /**
     * @var array<string, int>
     */
    protected static array $recordFailureLogBuckets = [];

    public function __construct(
        protected SystemSettings $systemSettings,
        protected ?IpRegionResolver $ipRegionResolver = null,
    ) {
        $this->ipRegionResolver ??= class_exists(IpRegionResolver::class)
            ? app(IpRegionResolver::class)
            : new IpRegionResolver;
    }

    public function protectionEnabled(): bool
    {
        return $this->systemSettings->siteProtectionEnabled();
    }

    public function isGlobalAllowlisted(string $ip): bool
    {
        return $this->ipMatchesList($ip, $this->systemSettings->securityIpAllowlist());
    }

    public function isGlobalBlocklisted(string $ip): bool
    {
        return $this->ipMatchesList($ip, $this->systemSettings->securityIpBlocklist());
    }

    /**
     * @return array<int, string>
     */
    public function siteIpPolicyActions(): array
    {
        return ['allow', 'block', 'remove_allow', 'remove_block', 'release_block'];
    }

    public function siteIpPolicyAuditAction(string $action): string
    {
        return match ($action) {
            'allow' => 'security_allow_ip',
            'block' => 'security_block_ip',
            'remove_allow' => 'security_remove_allow_ip',
            'release_block' => 'security_release_ip_block',
            default => 'security_remove_block_ip',
        };
    }

    public function siteIpPolicyStatusMessage(string $action): string
    {
        return match ($action) {
            'allow' => '已加入站点 IP 白名单。',
            'block' => '已加入站点 IP 黑名单。',
            'remove_allow' => '已移出站点 IP 白名单。',
            'release_block' => '已解除临时封禁。',
            default => '已移出站点 IP 黑名单。',
        };
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
                if ($this->shouldLogRuntimeFailure(0, 'host-cache')) {
                    Log::warning('Site security host cache failed.', [
                        'host' => $host,
                        'message' => $exception->getMessage(),
                    ]);
                }

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

            $routeSiteKey = trim((string) $request->route('siteKey', ''));

            if ($routeSiteKey !== '') {
                $site = DB::table('sites')
                    ->where('site_key', $routeSiteKey)
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
        $mode = $this->siteSecurityMode($siteId);

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->systemSettings->securityIpAllowlist())) {
            return null;
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->systemSettings->securityIpBlocklist())) {
            return $this->applySiteMode([
                'code' => 'ip_blocklist',
                'name' => '黑名单 IP 拦截',
                'risk_level' => 'critical',
                'action' => 'temporary_block',
            ], $mode);
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->siteIpAllowlist($siteId))) {
            return null;
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->siteIpBlocklist($siteId))) {
            return $this->applySiteMode([
                'code' => 'ip_blocklist',
                'name' => '站点黑名单 IP 拦截',
                'risk_level' => 'critical',
                'action' => 'temporary_block',
            ], $mode);
        }

        if ($this->pathMatchesAllowlist($request, $siteId)) {
            return null;
        }

        if ($rule = $this->exceptedRule($this->matchRuntimeIpBlock($request, $siteId), $siteId)) {
            return $this->applySiteMode($rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchProbeBlock($request, $siteId), $siteId)) {
            return $this->applySiteMode($rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchIpReputationBlock($request, $siteId), $siteId)) {
            return $this->applySiteMode($rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchBadMethod($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchBadClient($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchBadPayload($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if (! $this->isMediaRequest($request) && ($rule = $this->exceptedRule($this->matchBadPath($request), $siteId))) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchPathTraversal($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($this->isMediaRequest($request)) {
            return $this->applySiteMode($this->exceptedRule($this->matchRateLimit($request, $siteId), $siteId), $mode);
        }

        if ($rule = $this->exceptedRule($this->matchBadUpload($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchSqlInjection($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchXss($request), $siteId)) {
            return $this->applySiteMode($this->escalateProbeIfNeeded($request, $siteId, $rule) ?? $rule, $mode);
        }

        if ($rule = $this->exceptedRule($this->matchRateLimit($request, $siteId), $siteId)) {
            return $this->applySiteMode($rule, $mode);
        }

        return null;
    }

    public function recordBlocked(object $site, array $rule, Request $request): void
    {
        if (($rule['_skip_record'] ?? false) === true) {
            return;
        }

        $siteId = (int) $site->id;

        try {
            $now = now('Asia/Shanghai');
            $date = $now->toDateString();
            $rule = $this->enrichRule($rule);
            $column = $this->statsColumn((string) $rule['code']);
            $fingerprint = $this->eventFingerprint($siteId, $rule, $request);

            DB::table('site_security_daily_stats')->insertOrIgnore([
                'site_id' => $siteId,
                'stat_date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $updates = [
                'blocked_total' => DB::raw('blocked_total + 1'),
                'updated_at' => $now,
            ];

            if ($column !== 'blocked_total' && $this->tableHasColumn('site_security_daily_stats', $column)) {
                $updates[$column] = DB::raw($column.' + 1');
            }

            DB::table('site_security_daily_stats')
                ->where('site_id', $siteId)
                ->where('stat_date', $date)
                ->update($updates);

            $event = [
                'site_id' => $siteId,
                'rule_code' => (string) $rule['code'],
                'rule_name' => (string) $rule['name'],
                'request_path' => $this->normalizedRequestPath($request),
                'request_method' => strtoupper((string) $request->method()),
                'client_ip' => $request->ip() ?: null,
                'region_name' => null,
                'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
                'created_at' => $now,
            ];

            if ($this->shouldSampleSecurityEvent($siteId, $fingerprint)) {
                foreach ([
                    'risk_level' => (string) $rule['risk_level'],
                    'action' => (string) $rule['action'],
                    'user_agent' => $this->trimHeader($request->userAgent()),
                    'referer' => $this->trimHeader($request->headers->get('referer')),
                    'request_query' => $this->requestQuerySample($request),
                    'fingerprint' => $fingerprint,
                ] as $column => $value) {
                    if ($this->tableHasColumn('site_security_events', $column)) {
                        $event[$column] = $value;
                    }
                }

                DB::table('site_security_events')->insert($event);
            }

            if ($this->ipReputationTableReady()) {
                $this->updateIpReputation($siteId, $rule, $request, $now);
            }
        } catch (\Throwable $exception) {
            if ($this->shouldLogRecordFailure($siteId)) {
                Log::warning('Site security record failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }

            return;
        }
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

    public function pruneSecurityStorage(?int $siteId = null): void
    {
        $query = DB::table('sites')->where('status', 1)->orderBy('id');

        if ($siteId !== null) {
            $query->where('id', $siteId);
        }

        foreach ($query->pluck('id') as $id) {
            $this->pruneEvents((int) $id);
            $this->pruneStats((int) $id);
        }
    }

    public function platformOverviewPayload(): array
    {
        $today = now('Asia/Shanghai')->startOfDay();
        $sevenDaysAgo = $today->copy()->subDays(6);
        $now = now('Asia/Shanghai');

        $siteRows = DB::table('sites')
            ->select('id', 'name', 'site_key')
            ->orderBy('id')
            ->get();

        $siteIds = $siteRows->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $statTypeColumns = [
            'bad_path' => 'blocked_bad_path',
            'sql_injection' => 'blocked_sql_injection',
            'xss' => 'blocked_xss',
            'path_traversal' => 'blocked_path_traversal',
            'bad_upload' => 'blocked_bad_upload',
            'rate_limit' => 'blocked_rate_limit',
            'probe_abuse' => 'blocked_probe_abuse',
            'ip_blocklist' => 'blocked_ip_blocklist',
            'bad_client' => 'blocked_bad_client',
            'bad_method' => 'blocked_bad_method',
            'bad_payload' => 'blocked_bad_payload',
        ];
        $statSelectColumns = ['site_id', 'stat_date', 'blocked_total'];
        foreach ($statTypeColumns as $column) {
            if ($this->tableHasColumn('site_security_daily_stats', $column)) {
                $statSelectColumns[] = $column;
            }
        }

        $statsRows = DB::table('site_security_daily_stats')
            ->whereIn('site_id', $siteIds)
            ->whereBetween('stat_date', [$sevenDaysAgo->toDateString(), $today->toDateString()])
            ->get($statSelectColumns);

        $suspiciousRows = $this->ipReputationTableReady()
            ? DB::table('site_security_ip_reputations')
                ->whereIn('site_id', $siteIds)
                ->where('last_seen_at', '>=', $sevenDaysAgo->toDateTimeString())
                ->get(['site_id', 'status', 'blocked_until'])
            : collect();

        $recentHighRiskEvents = DB::table('site_security_events')
            ->join('sites', 'sites.id', '=', 'site_security_events.site_id')
            ->whereIn('site_security_events.site_id', $siteIds)
            ->where('site_security_events.created_at', '>=', $sevenDaysAgo->toDateTimeString())
            ->whereIn('site_security_events.risk_level', ['high', 'critical'])
            ->orderByDesc('site_security_events.created_at')
            ->limit(10)
            ->get([
                'site_security_events.site_id',
                'sites.name as site_name',
                'site_security_events.rule_code',
                'site_security_events.rule_name',
                'site_security_events.client_ip',
                'site_security_events.region_name',
                'site_security_events.request_path',
                'site_security_events.created_at',
            ])
            ->map(function (object $row): array {
                $profile = $this->eventProfile((string) ($row->rule_code ?? ''), (string) ($row->rule_name ?? ''));

                return [
                    'site_id' => (int) ($row->site_id ?? 0),
                    'site_name' => (string) ($row->site_name ?? ''),
                    'rule_label' => (string) ($profile['category_label'] ?? '异常请求'),
                    'client_ip' => (string) ($row->client_ip ?? '--'),
                    'region_name' => trim((string) ($row->region_name ?? '')) ?: $this->resolveAttackRegionLabel((string) ($row->client_ip ?? '')),
                    'request_path' => (string) ($row->request_path ?? '/'),
                    'created_at_label' => $row->created_at ? date('m-d H:i', strtotime((string) $row->created_at)) : '--',
                ];
            })
            ->all();

        $highRiskTotal = 0;
        $typeTotalsBySite = [];
        foreach ($statsRows as $row) {
            $rowSiteId = (int) ($row->site_id ?? 0);

            foreach ($statTypeColumns as $ruleCode => $column) {
                $value = (int) ($row->{$column} ?? 0);
                $typeTotalsBySite[$rowSiteId][$ruleCode] = ($typeTotalsBySite[$rowSiteId][$ruleCode] ?? 0) + $value;

                if (in_array($this->defaultRiskLevel($ruleCode), ['high', 'critical'], true)) {
                    $highRiskTotal += $value;
                }
            }
        }

        $modeRows = DB::table('site_settings')
            ->whereIn('site_id', $siteIds)
            ->where('setting_key', 'security.mode')
            ->pluck('setting_value', 'site_id');

        $siteStats = [];
        foreach ($siteRows as $site) {
            $siteId = (int) $site->id;
            $rows = $statsRows->where('site_id', $siteId);
            $siteSuspicious = $suspiciousRows->where('site_id', $siteId);
            $siteTypeTotals = $typeTotalsBySite[$siteId] ?? [];
            arsort($siteTypeTotals);
            $topRuleCode = (string) array_key_first(array_filter($siteTypeTotals, fn (int $total): bool => $total > 0));
            $topRuleProfile = $this->eventProfile($topRuleCode, '');

            $siteStats[] = [
                'site_id' => $siteId,
                'site_name' => (string) $site->name,
                'site_key' => (string) $site->site_key,
                'today_blocked' => (int) $rows->where('stat_date', $today->toDateString())->sum('blocked_total'),
                'seven_day_blocked' => (int) $rows->sum('blocked_total'),
                'suspicious_ip_count' => (int) $siteSuspicious->count(),
                'blocked_ip_count' => (int) $siteSuspicious->filter(function (object $row) use ($now): bool {
                    if ((string) ($row->status ?? '') !== 'blocked') {
                        return false;
                    }

                    if ($row->blocked_until === null) {
                        return true;
                    }

                    return strtotime((string) $row->blocked_until) > $now->getTimestamp();
                })->count(),
                'top_rule_label' => $topRuleCode !== '' ? (string) ($topRuleProfile['category_label'] ?? '异常请求') : '暂无',
                'security_mode_label' => $this->siteSecurityModeLabel((string) ($modeRows[$siteId] ?? 'standard')),
            ];
        }

        usort($siteStats, function (array $left, array $right): int {
            return [
                (int) ($right['seven_day_blocked'] ?? 0),
                (int) ($right['suspicious_ip_count'] ?? 0),
                (int) ($right['today_blocked'] ?? 0),
                (string) ($left['site_name'] ?? ''),
            ] <=> [
                (int) ($left['seven_day_blocked'] ?? 0),
                (int) ($left['suspicious_ip_count'] ?? 0),
                (int) ($left['today_blocked'] ?? 0),
                (string) ($right['site_name'] ?? ''),
            ];
        });

        return [
            'today_blocked' => (int) $statsRows->where('stat_date', $today->toDateString())->sum('blocked_total'),
            'seven_day_blocked' => (int) $statsRows->sum('blocked_total'),
            'seven_day_high_risk' => $highRiskTotal,
            'active_blocked_ips' => (int) collect($siteStats)->sum('blocked_ip_count'),
            'active_sites' => (int) collect($siteStats)->filter(fn (array $item): bool => ((int) $item['seven_day_blocked']) > 0 || ((int) $item['suspicious_ip_count']) > 0)->count(),
            'site_rows' => array_values(array_filter($siteStats, fn (array $item): bool => ((int) $item['seven_day_blocked']) > 0 || ((int) $item['suspicious_ip_count']) > 0)),
            'recent_high_risk_events' => $recentHighRiskEvents,
        ];
    }

    public function inspectBadMethod(Request $request): ?array
    {
        if (! $this->protectionEnabled()) {
            return null;
        }

        $site = $this->resolveSite($request);

        if (! $site) {
            return $this->matchBadMethod($request);
        }

        $siteId = (int) $site->id;
        $mode = $this->siteSecurityMode($siteId);

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->systemSettings->securityIpAllowlist())) {
            return null;
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->systemSettings->securityIpBlocklist())) {
            return $this->applySiteMode([
                'code' => 'ip_blocklist',
                'name' => '黑名单 IP 拦截',
                'risk_level' => 'critical',
                'action' => 'temporary_block',
            ], $mode);
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->siteIpAllowlist($siteId))) {
            return null;
        }

        if ($this->ipMatchesList((string) ($request->ip() ?: ''), $this->siteIpBlocklist($siteId))) {
            return $this->applySiteMode([
                'code' => 'ip_blocklist',
                'name' => '站点黑名单 IP 拦截',
                'risk_level' => 'critical',
                'action' => 'temporary_block',
            ], $mode);
        }

        if ($this->pathMatchesAllowlist($request, $siteId)) {
            return null;
        }

        if ($rule = $this->exceptedRule($this->matchRuntimeIpBlock($request, $siteId), $siteId)) {
            return $this->applySiteMode($rule, $mode);
        }

        return $this->applySiteMode($this->exceptedRule($this->matchBadMethod($request), $siteId), $mode);
    }

    public function shouldBlock(array $rule): bool
    {
        return (string) ($rule['action'] ?? $this->defaultAction((string) ($rule['code'] ?? ''))) !== 'record';
    }

    public function applySiteIpPolicy(int $siteId, string $ip, string $action, int $updatedBy): void
    {
        $ip = trim($ip);

        if ($ip === '127.0.0.1' || $ip === '::1') {
            throw ValidationException::withMessages([
                'client_ip' => '本地测试 IP 不支持加入站点黑白名单。',
            ]);
        }

        if ($this->isGlobalAllowlisted($ip) || $this->isGlobalBlocklisted($ip)) {
            throw ValidationException::withMessages([
                'client_ip' => '该 IP 当前受平台全局黑白名单控制，请先在平台安全设置中调整。',
            ]);
        }

        $policy = $this->sitePolicy($siteId);
        $allowlist = $policy['ip_allowlist'];
        $blocklist = $policy['ip_blocklist'];

        if ($action === 'allow') {
            $allowlist[] = $ip;
            $blocklist = array_values(array_filter($blocklist, fn (string $item): bool => $item !== $ip));
            $this->releaseTemporaryBlock($siteId, $ip);
        } elseif ($action === 'block') {
            $blocklist[] = $ip;
            $allowlist = array_values(array_filter($allowlist, fn (string $item): bool => $item !== $ip));
            $this->syncIpReputationPolicyStatus($siteId, $ip, 'block');
        } elseif ($action === 'remove_allow') {
            $allowlist = array_values(array_filter($allowlist, fn (string $item): bool => $item !== $ip));
            $this->syncIpReputationPolicyStatus($siteId, $ip, 'remove_allow');
        } elseif ($action === 'remove_block') {
            $blocklist = array_values(array_filter($blocklist, fn (string $item): bool => $item !== $ip));
            $this->releaseTemporaryBlock($siteId, $ip);
        } else {
            $this->releaseTemporaryBlock($siteId, $ip);
        }

        $this->storeSiteIpSettingList($siteId, 'security.ip_allowlist', $allowlist, $updatedBy);
        $this->storeSiteIpSettingList($siteId, 'security.ip_blocklist', $blocklist, $updatedBy);
        Cache::forget('site-security:site-policy:'.$siteId);
    }

    public function clearRuntimeBlocksForIp(int $siteId, string $ip, ?string $lastRequestPath = null, ?string $lastRuleCode = null): void
    {
        $ipHash = sha1($ip);

        RateLimiter::clear('site-security-rate-block:'.$siteId.':'.$ipHash);
        RateLimiter::clear('site-security-probe-block:'.$siteId.':'.$ipHash);
        RateLimiter::clear('site-security-reputation-block:'.$siteId.':'.$ipHash);
        RateLimiter::clear('site-security-malicious-block:'.$siteId.':'.$ipHash);
        RateLimiter::clear('site-security-rate:'.$siteId.':site:'.$ipHash);
        RateLimiter::clear('site-security-rate:'.$siteId.':form:'.$ipHash);
        RateLimiter::clear('site-security-rate:'.$siteId.':media:'.$ipHash);
        RateLimiter::clear('site-security-probe:'.$siteId.':'.$ipHash);

        if ($lastRequestPath !== null && $lastRequestPath !== '') {
            RateLimiter::clear('site-security-rate:'.$siteId.':'.sha1($ip.'|'.mb_strtolower($lastRequestPath)));
        }

        if ($lastRuleCode !== null && $lastRuleCode !== '') {
            RateLimiter::clear('site-security-probe:'.$siteId.':'.$lastRuleCode.':'.$ipHash);
        }

        Cache::forget($this->pathScanKey($siteId, $ip));
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function storeSiteIpSettingList(int $siteId, string $key, array $items, int $updatedBy): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $siteId, 'setting_key' => $key],
            [
                'setting_value' => collect($items)
                    ->map(fn ($item): string => trim((string) $item))
                    ->filter(fn (string $item): bool => $item !== '')
                    ->unique()
                    ->values()
                    ->implode("\n"),
                'autoload' => 1,
                'updated_by' => $updatedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    protected function releaseTemporaryBlock(int $siteId, string $ip): void
    {
        $reputation = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', hash('sha256', $ip))
            ->first(['last_request_path', 'last_rule_code']);

        DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', hash('sha256', $ip))
            ->update([
                'status' => 'monitored',
                'blocked_until' => null,
                'updated_at' => now(),
            ]);

        $this->clearRuntimeBlocksForIp(
            $siteId,
            $ip,
            (string) ($reputation?->last_request_path ?? ''),
            (string) ($reputation?->last_rule_code ?? ''),
        );
    }

    protected function syncIpReputationPolicyStatus(int $siteId, string $ip, string $action): void
    {
        $query = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', hash('sha256', $ip));

        $row = $query->first(['blocked_until']);

        if (! $row) {
            return;
        }

        if ($action === 'block') {
            $query->update([
                'status' => 'blocked',
                'blocked_until' => null,
                'updated_at' => now(),
            ]);

            return;
        }

        if (in_array($action, ['allow', 'remove_allow', 'remove_block'], true) && $row->blocked_until === null) {
            $query->update([
                'status' => 'monitored',
                'updated_at' => now(),
            ]);
        }
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
            ->when(
                Schema::hasColumn('site_security_daily_stats', 'blocked_ip_blocklist'),
                fn ($query) => $query->selectRaw('SUM(blocked_ip_blocklist) as ip_blocklist')
            )
            ->when(
                Schema::hasColumn('site_security_daily_stats', 'blocked_bad_client'),
                fn ($query) => $query->selectRaw('SUM(blocked_bad_client) as bad_client')
            )
            ->when(
                Schema::hasColumn('site_security_daily_stats', 'blocked_bad_method'),
                fn ($query) => $query->selectRaw('SUM(blocked_bad_method) as bad_method')
            )
            ->when(
                Schema::hasColumn('site_security_daily_stats', 'blocked_bad_payload'),
                fn ($query) => $query->selectRaw('SUM(blocked_bad_payload) as bad_payload')
            )
            ->first();

        $allTypes = collect([
            ['code' => 'bad_path', 'label' => '恶意扫描', 'value' => (int) ($typeTotals->bad_path ?? 0)],
            ['code' => 'sql_injection', 'label' => 'SQL 注入', 'value' => (int) ($typeTotals->sql_injection ?? 0)],
            ['code' => 'xss', 'label' => 'XSS 攻击', 'value' => (int) ($typeTotals->xss ?? 0)],
            ['code' => 'path_traversal', 'label' => '路径穿越', 'value' => (int) ($typeTotals->path_traversal ?? 0)],
            ['code' => 'bad_upload', 'label' => '可疑上传', 'value' => (int) ($typeTotals->bad_upload ?? 0)],
            ['code' => 'rate_limit', 'label' => '频繁刷新', 'value' => (int) ($typeTotals->rate_limit ?? 0)],
            ['code' => 'probe_abuse', 'label' => '扫描试探超限', 'value' => (int) ($typeTotals->probe_abuse ?? 0)],
            ['code' => 'ip_blocklist', 'label' => '黑名单 IP', 'value' => (int) ($typeTotals->ip_blocklist ?? 0)],
            ['code' => 'bad_client', 'label' => '脚本扫描器', 'value' => (int) ($typeTotals->bad_client ?? 0)],
            ['code' => 'bad_method', 'label' => '异常方法', 'value' => (int) ($typeTotals->bad_method ?? 0)],
            ['code' => 'bad_payload', 'label' => '异常参数', 'value' => (int) ($typeTotals->bad_payload ?? 0)],
        ])->filter(fn (array $item): bool => $item['value'] > 0)
            ->map(fn (array $item) => [
                ...$item,
                'ratio' => $sevenDayTotal > 0 ? (int) round(($item['value'] / $sevenDayTotal) * 100) : 0,
                'is_high_risk' => in_array($this->defaultRiskLevel((string) $item['code']), ['critical', 'high'], true),
                'note' => $this->typeDistributionNote((string) $item['code']),
            ]);

        $types = $allTypes
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();

        $sevenDayHighRiskTotal = (int) $allTypes
            ->where('is_high_risk', true)
            ->sum('value');

        $eventWindowStart = $sevenDaysAgo->toDateTimeString();

        $eventBuckets = [
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('created_at', '>=', $eventWindowStart)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('created_at', '>=', $eventWindowStart)
                ->whereIn('rule_code', static::HIGH_RISK_RULE_CODES)
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ];

        foreach (['sql_injection', 'xss', 'path_traversal', 'bad_upload', 'probe_abuse', 'rate_limit', 'ip_blocklist', 'bad_client', 'bad_method', 'bad_payload'] as $ruleCode) {
            $eventBuckets[] = DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('created_at', '>=', $eventWindowStart)
                ->where('rule_code', $ruleCode)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        $events = collect($eventBuckets)
            ->flatten(1)
            ->unique(fn (object $event): int => (int) $event->id)
            ->sortBy([
                fn (object $event): int => -1 * $this->riskLevelPriority(trim((string) ($event->risk_level ?? '')) ?: $this->defaultRiskLevel((string) $event->rule_code)),
                fn (object $event): int => -1 * (int) $event->id,
            ])
            ->values()
            ->map(function (object $event): array {
                $profile = $this->eventProfile((string) $event->rule_code, (string) $event->rule_name);
                $riskLevel = trim((string) ($event->risk_level ?? '')) ?: $this->defaultRiskLevel((string) $event->rule_code);
                $action = trim((string) ($event->action ?? '')) ?: $this->defaultAction((string) $event->rule_code);

                return [
                    'rule_name' => (string) $event->rule_name,
                    'rule_code' => (string) $event->rule_code,
                    'risk_level' => $riskLevel,
                    'action' => $action,
                    'request_path' => (string) $event->request_path,
                    'request_method' => strtoupper((string) $event->request_method),
                    'client_ip' => (string) ($event->client_ip ?? '--'),
                    'created_at_label' => $event->created_at ? date('m-d H:i', strtotime((string) $event->created_at)) : '--',
                    'category_label' => $profile['category_label'],
                    'action_label' => $this->actionLabel($action),
                    'risk_label' => $this->riskLevelLabel($riskLevel),
                ];
            })
            ->all();

        $regionRows = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString())
            ->selectRaw("COALESCE(NULLIF(TRIM(region_name), ''), '未知地区') as region_label")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('region_label')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        $regionTotal = (int) DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString())
            ->count();
        $regions = $regionRows
            ->map(fn (object $row): array => [
                'label' => (string) $row->region_label,
                'value' => (int) $row->total,
                'ratio' => $regionTotal > 0 ? (int) round(((int) $row->total / $regionTotal) * 100) : 0,
            ])
            ->take(5)
            ->values()
            ->all();

        $suspiciousIps = [];

        if ($this->ipReputationTableReady()) {
            $globalAllowlist = $this->systemSettings->securityIpAllowlist();
            $globalBlocklist = $this->systemSettings->securityIpBlocklist();
            $siteAllowlist = $this->siteIpAllowlist($siteId);
            $siteBlocklist = $this->siteIpBlocklist($siteId);
            $suspiciousIps = DB::table('site_security_ip_reputations')
                ->where('site_id', $siteId)
                ->where('last_seen_at', '>=', $sevenDaysAgo->toDateTimeString())
                ->get()
                ->map(fn (object $row): array => $this->mapSuspiciousIpRow($row, $globalAllowlist, $globalBlocklist, $siteAllowlist, $siteBlocklist))
                ->sort(fn (array $left, array $right): int => $this->compareSuspiciousIpRows($left, $right))
                ->take(5)
                ->values()
                ->all();
        }

        return [
            'enabled' => $this->protectionEnabled(),
            'today_blocked' => $todayTotal,
            'status_label' => $this->protectionEnabled() ? '运行中' : '未启用',
            'status_tone' => $this->protectionEnabled() ? 'running' : 'disabled',
            'peak_blocked' => $peak,
            'seven_day_blocked' => $sevenDayTotal,
            'seven_day_high_risk' => $sevenDayHighRiskTotal,
            'suspicious_ips' => $suspiciousIps,
            'trend' => $trend->all(),
            'types' => $types,
            'events' => $events,
            'regions' => $regions,
        ];
    }

    public function siteIpDetailPayload(int $siteId, string $clientIp): ?array
    {
        if (! $this->ipReputationTableReady() || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $globalAllowlist = $this->systemSettings->securityIpAllowlist();
        $globalBlocklist = $this->systemSettings->securityIpBlocklist();
        $siteAllowlist = $this->siteIpAllowlist($siteId);
        $siteBlocklist = $this->siteIpBlocklist($siteId);
        $ipHash = hash('sha256', $clientIp);

        $row = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->first();

        $recentEvents = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->orderByRaw("CASE risk_level WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (object $event) use ($clientIp): array {
                $profile = $this->eventProfile((string) ($event->rule_code ?? ''), (string) ($event->rule_name ?? ''));

                return [
                    'time_label' => $event->created_at ? date('m-d H:i:s', strtotime((string) $event->created_at)) : '--',
                    'request_path' => (string) ($event->request_path ?? ''),
                    'request_method' => (string) ($event->request_method ?? ''),
                    'rule_code' => (string) ($event->rule_code ?? ''),
                    'rule_label' => (string) ($profile['category_label'] ?? '异常请求'),
                    'risk_level_label' => $this->riskLevelLabel((string) ($event->risk_level ?? 'medium')),
                    'action_label' => $this->actionLabel((string) ($event->action ?? 'block')),
                    'region_name' => trim((string) ($event->region_name ?? '')) ?: $this->resolveAttackRegionLabel($clientIp),
                    'request_query' => (string) ($event->request_query ?? ''),
                    'referer' => (string) ($event->referer ?? ''),
                    'user_agent' => (string) ($event->user_agent ?? ''),
                ];
            })
            ->values()
            ->all();
        $reasonSummary = $this->siteIpReasonSummary($siteId, $ipHash);

        if (! $row && $recentEvents === []) {
            return null;
        }

        $mapped = $row
            ? $this->mapSuspiciousIpRow($row, $globalAllowlist, $globalBlocklist, $siteAllowlist, $siteBlocklist)
            : [
                'client_ip' => $clientIp,
                'region_name' => $this->resolveAttackRegionLabel($clientIp),
                'hit_count' => count($recentEvents),
                'high_risk_count' => collect($recentEvents)->whereIn('risk_level_label', ['高危', '严重'])->count(),
                'last_rule_code' => (string) ($recentEvents[0]['rule_code'] ?? ''),
                'last_request_path' => (string) ($recentEvents[0]['request_path'] ?? ''),
                'status' => 'monitored',
                'status_label' => $this->ipReputationStatusLabel('monitored'),
                'blocked_until_label' => '',
                'last_seen_label' => (string) ($recentEvents[0]['time_label'] ?? '--'),
                'last_seen_at_ts' => 0,
                'is_global_allowlisted' => $this->ipMatchesList($clientIp, $globalAllowlist),
                'is_global_blocklisted' => $this->ipMatchesList($clientIp, $globalBlocklist),
                'is_site_allowlisted' => $this->ipMatchesList($clientIp, $siteAllowlist),
                'is_site_blocklisted' => $this->ipMatchesList($clientIp, $siteBlocklist),
                'site_policy_label' => '',
            ];

        $lastRuleProfile = $this->eventProfile((string) ($mapped['last_rule_code'] ?? ''), '');

        return [
            ...$mapped,
            'last_rule_label' => (string) ($lastRuleProfile['category_label'] ?? '暂无规则'),
            'reason_summary' => $reasonSummary,
            'recent_events' => $recentEvents,
        ];
    }

    public function siteEventsModalPaginator(int $siteId, string $riskFilter = 'all', int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $sevenDaysAgo = now('Asia/Shanghai')->startOfDay()->subDays(6);
        $normalizedFilter = in_array($riskFilter, ['all', 'critical', 'high', 'medium'], true) ? $riskFilter : 'all';
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);

        $query = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString());

        $this->applySecurityEventRiskFilter($query, $normalizedFilter);

        $paginator = $query
            ->select([
                'id',
                'rule_name',
                'rule_code',
                'risk_level',
                'action',
                'request_path',
                'request_method',
                'client_ip',
                'region_name',
                'created_at',
            ])
            ->selectRaw($this->securityEventRiskPrioritySql().' as risk_priority')
            ->orderByDesc('risk_priority')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($safePerPage, ['*'], 'security_event_page', $safePage)
            ->withPath(route('admin.security.index'));

        $paginator->setCollection(
            $paginator->getCollection()->map(function (object $event): array {
                $profile = $this->eventProfile((string) ($event->rule_code ?? ''), (string) ($event->rule_name ?? ''));
                $riskLevel = trim((string) ($event->risk_level ?? '')) ?: $this->defaultRiskLevel((string) ($event->rule_code ?? ''));
                $action = trim((string) ($event->action ?? '')) ?: $this->defaultAction((string) ($event->rule_code ?? ''));

                return [
                    'id' => (int) $event->id,
                    'rule_name' => (string) ($event->rule_name ?? ''),
                    'rule_code' => (string) ($event->rule_code ?? ''),
                    'risk_level' => $riskLevel,
                    'action' => $action,
                    'request_path' => (string) ($event->request_path ?? ''),
                    'request_method' => strtoupper((string) ($event->request_method ?? 'GET')),
                    'client_ip' => (string) ($event->client_ip ?? '--'),
                    'region_name' => trim((string) ($event->region_name ?? '')) ?: $this->resolveAttackRegionLabel((string) ($event->client_ip ?? '')),
                    'created_at_label' => $event->created_at ? date('m-d H:i', strtotime((string) $event->created_at)) : '--',
                    'created_at_ts' => $event->created_at ? strtotime((string) $event->created_at) : 0,
                    'category_label' => (string) ($profile['category_label'] ?? '异常请求'),
                    'action_label' => $this->actionLabel($action),
                    'risk_label' => $this->riskLevelLabel($riskLevel),
                ];
            })
        );

        return $paginator;
    }

    public function siteSuspiciousIpsModalPaginator(int $siteId, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $sevenDaysAgo = now('Asia/Shanghai')->startOfDay()->subDays(6);

        if (! $this->ipReputationTableReady()) {
            return $this->paginateArray(collect(), $page, $perPage, 'security_ip_page');
        }

        $globalAllowlist = $this->systemSettings->securityIpAllowlist();
        $globalBlocklist = $this->systemSettings->securityIpBlocklist();
        $siteAllowlist = $this->siteIpAllowlist($siteId);
        $siteBlocklist = $this->siteIpBlocklist($siteId);
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);

        $paginator = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('last_seen_at', '>=', $sevenDaysAgo->toDateTimeString())
            ->select('*')
            ->selectRaw("CASE WHEN status = 'blocked' THEN 3 WHEN status = 'limited' THEN 2 ELSE 1 END as status_weight")
            ->orderByDesc('status_weight')
            ->orderByDesc('high_risk_count')
            ->orderByDesc('hit_count')
            ->orderByDesc('last_seen_at')
            ->orderBy('client_ip')
            ->paginate($safePerPage, ['*'], 'security_ip_page', $safePage)
            ->withPath(route('admin.security.index'));

        $rows = $paginator->getCollection();
        $ipHashes = $rows->pluck('ip_hash')->filter()->unique()->values()->all();

        $latestEventsByHash = $ipHashes === []
            ? []
            : DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString())
                ->whereIn('ip_hash', $ipHashes)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['ip_hash', 'request_method', 'request_query', 'user_agent', 'referer', 'region_name', 'created_at'])
                ->groupBy('ip_hash')
                ->map(fn (Collection $events): object => $events->first())
                ->all();

        $paginator->setCollection(
            $rows->map(fn (object $row): array => $this->mapSuspiciousIpRow(
                $row,
                $globalAllowlist,
                $globalBlocklist,
                $siteAllowlist,
                $siteBlocklist,
                $latestEventsByHash[(string) ($row->ip_hash ?? '')] ?? null,
            ))
                ->sort(fn (array $left, array $right): int => $this->compareSuspiciousIpRows($left, $right))
                ->values()
        );

        return $paginator;
    }

    public function deleteSiteSecurityEventRecord(int $siteId, int $eventId): bool
    {
        $event = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('id', $eventId)
            ->first(['id', 'client_ip']);

        if (! $event) {
            return false;
        }

        DB::transaction(function () use ($siteId, $event): void {
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('id', (int) $event->id)
                ->delete();

            $ip = trim((string) ($event->client_ip ?? ''));
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $this->rebuildIpReputationFromEvents($siteId, $ip);
            }
        });

        return true;
    }

    public function clearSiteSecurityEventRecords(int $siteId, string $riskFilter = 'all'): array
    {
        $sevenDaysAgo = now('Asia/Shanghai')->startOfDay()->subDays(6);
        $normalizedFilter = in_array($riskFilter, ['all', 'critical', 'high', 'medium'], true) ? $riskFilter : 'all';
        $deletedEvents = 0;
        $affectedIps = [];

        $query = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $sevenDaysAgo->toDateTimeString());

        $this->applySecurityEventRiskFilter($query, $normalizedFilter);

        do {
            $events = (clone $query)
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'client_ip']);

            if ($events->isEmpty()) {
                break;
            }

            $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($events as $event) {
                $ip = trim((string) ($event->client_ip ?? ''));
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $affectedIps[$ip] = true;
                }
            }

            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->whereIn('id', $eventIds)
                ->delete();

            $deletedEvents += count($eventIds);
        } while (true);

        foreach (array_keys($affectedIps) as $ip) {
            $this->rebuildIpReputationFromEvents($siteId, $ip);
        }

        return [
            'deleted_events' => $deletedEvents,
            'affected_ips' => count($affectedIps),
        ];
    }

    public function deleteSiteSuspiciousIpRecord(int $siteId, string $clientIp): bool
    {
        $ip = trim($clientIp);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $reputation = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', hash('sha256', $ip))
            ->first(['last_request_path', 'last_rule_code']);

        $ipHash = hash('sha256', $ip);
        $hasEvents = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->exists();

        if (! $reputation && ! $hasEvents) {
            return false;
        }

        DB::transaction(function () use ($siteId, $ip, $ipHash, $reputation): void {
            DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->where('ip_hash', $ipHash)
                ->delete();
            DB::table('site_security_ip_reputations')
                ->where('site_id', $siteId)
                ->where('ip_hash', $ipHash)
                ->delete();

            $this->clearRuntimeBlocksForIp(
                $siteId,
                $ip,
                (string) ($reputation?->last_request_path ?? ''),
                (string) ($reputation?->last_rule_code ?? ''),
            );
        });

        return true;
    }

    public function clearSiteSuspiciousIpRecords(int $siteId): array
    {
        $sevenDaysAgo = now('Asia/Shanghai')->startOfDay()->subDays(6);

        if (! $this->ipReputationTableReady()) {
            return ['deleted_ips' => 0, 'deleted_events' => 0];
        }

        $rowsQuery = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('last_seen_at', '>=', $sevenDaysAgo->toDateTimeString());
        $deletedIps = 0;
        $deletedEvents = 0;

        do {
            $rows = (clone $rowsQuery)
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'client_ip', 'ip_hash', 'last_request_path', 'last_rule_code']);

            if ($rows->isEmpty()) {
                break;
            }

            $ipHashes = $rows->pluck('ip_hash')->filter()->unique()->values()->all();
            $ipRows = $rows->filter(fn (object $row): bool => is_string($row->client_ip) && trim((string) $row->client_ip) !== '' && filter_var(trim((string) $row->client_ip), FILTER_VALIDATE_IP) !== false)
                ->map(function (object $row): array {
                    return [
                        'client_ip' => trim((string) $row->client_ip),
                        'ip_hash' => (string) $row->ip_hash,
                        'last_request_path' => (string) ($row->last_request_path ?? ''),
                        'last_rule_code' => (string) ($row->last_rule_code ?? ''),
                    ];
                })
                ->unique('ip_hash')
                ->values();

            DB::transaction(function () use ($siteId, $rows, $ipRows, $ipHashes, &$deletedEvents, &$deletedIps): void {
                if ($ipHashes !== []) {
                    $deletedEvents += DB::table('site_security_events')
                        ->where('site_id', $siteId)
                        ->whereIn('ip_hash', $ipHashes)
                        ->delete();
                }

                $deletedEvents += DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->whereIn('client_ip', $ipRows->pluck('client_ip')->all())
                    ->delete();

                $deletedIps += DB::table('site_security_ip_reputations')
                    ->where('site_id', $siteId)
                    ->whereIn('id', $rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
                    ->delete();

                foreach ($ipRows as $row) {
                    $this->clearRuntimeBlocksForIp(
                        $siteId,
                        (string) $row['client_ip'],
                        (string) $row['last_request_path'],
                        (string) $row['last_rule_code'],
                    );
                }
            });
        } while (true);

        return [
            'deleted_ips' => $deletedIps,
            'deleted_events' => $deletedEvents,
        ];
    }

    protected function rebuildIpReputationFromEvents(int $siteId, string $clientIp): void
    {
        if (! $this->ipReputationTableReady() || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $ipHash = hash('sha256', $clientIp);
        $existingRow = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->first(['last_request_path', 'last_rule_code', 'last_seen_at', 'region_name']);

        $latestEvent = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['rule_code', 'request_path', 'created_at', 'region_name']);

        if (! $latestEvent) {
            if ($this->shouldPersistPolicyOnlyReputation($siteId, $clientIp)) {
                $payload = [
                    'client_ip' => $clientIp,
                    'region_name' => trim((string) ($existingRow?->region_name ?? '')) ?: $this->resolveAttackRegionLabel($clientIp),
                    'hit_count' => 0,
                    'high_risk_count' => 0,
                    'last_rule_code' => '',
                    'last_request_path' => '',
                    'status' => $this->policyOnlyStatus($siteId, $clientIp),
                    'blocked_until' => null,
                    'last_seen_at' => $existingRow?->last_seen_at ?? now(),
                    'updated_at' => now(),
                ];

                if ($existingRow) {
                    DB::table('site_security_ip_reputations')
                        ->where('site_id', $siteId)
                        ->where('ip_hash', $ipHash)
                        ->update($payload);
                } else {
                    DB::table('site_security_ip_reputations')->insert($payload + [
                        'site_id' => $siteId,
                        'ip_hash' => $ipHash,
                        'created_at' => now(),
                    ]);
                }
            } else {
                DB::table('site_security_ip_reputations')
                    ->where('site_id', $siteId)
                    ->where('ip_hash', $ipHash)
                    ->delete();
            }

            $this->clearRuntimeBlocksForIp(
                $siteId,
                $clientIp,
                (string) ($existingRow?->last_request_path ?? ''),
                (string) ($existingRow?->last_rule_code ?? ''),
            );

            return;
        }

        $hitCount = (int) DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->count();

        $highRiskCount = (int) DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->whereIn('rule_code', static::HIGH_RISK_RULE_CODES)
            ->count();

        $payload = [
            'client_ip' => $clientIp,
            'region_name' => trim((string) ($latestEvent->region_name ?? '')) ?: $this->resolveAttackRegionLabel($clientIp),
            'hit_count' => $hitCount,
            'high_risk_count' => $highRiskCount,
            'last_rule_code' => (string) ($latestEvent->rule_code ?? ''),
            'last_request_path' => (string) ($latestEvent->request_path ?? ''),
            'status' => $this->policyOnlyStatus($siteId, $clientIp),
            'blocked_until' => null,
            'last_seen_at' => $latestEvent->created_at ?? now(),
            'updated_at' => now(),
        ];

        if ($existingRow) {
            DB::table('site_security_ip_reputations')
                ->where('site_id', $siteId)
                ->where('ip_hash', $ipHash)
                ->update($payload);
        } else {
            DB::table('site_security_ip_reputations')->insert($payload + [
                'site_id' => $siteId,
                'ip_hash' => $ipHash,
                'created_at' => now(),
            ]);
        }

        $this->clearRuntimeBlocksForIp(
            $siteId,
            $clientIp,
            (string) ($existingRow?->last_request_path ?? (string) ($latestEvent->request_path ?? '')),
            (string) ($existingRow?->last_rule_code ?? (string) ($latestEvent->rule_code ?? '')),
        );
    }

    protected function shouldPersistPolicyOnlyReputation(int $siteId, string $clientIp): bool
    {
        return $this->isGlobalAllowlisted($clientIp)
            || $this->isGlobalBlocklisted($clientIp)
            || $this->ipMatchesList($clientIp, $this->siteIpAllowlist($siteId))
            || $this->ipMatchesList($clientIp, $this->siteIpBlocklist($siteId));
    }

    protected function policyOnlyStatus(int $siteId, string $clientIp): string
    {
        return ($this->isGlobalBlocklisted($clientIp) || $this->ipMatchesList($clientIp, $this->siteIpBlocklist($siteId)))
            ? 'blocked'
            : 'monitored';
    }

    protected function paginateArray(Collection $items, int $page, int $perPage, string $pageName): LengthAwarePaginator
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);
        $total = $items->count();
        $slice = $items->slice(($safePage - 1) * $safePerPage, $safePerPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $safePerPage,
            $safePage,
            [
                'path' => route('admin.security.index'),
                'pageName' => $pageName,
            ]
        );
    }

    protected function applySecurityEventRiskFilter($query, string $riskFilter): void
    {
        if ($riskFilter === 'all') {
            return;
        }

        $ruleCodes = match ($riskFilter) {
            'critical' => ['ip_blocklist', 'probe_abuse'],
            'high' => ['sql_injection', 'path_traversal', 'xss', 'bad_upload', 'bad_client', 'bad_payload'],
            default => ['bad_path', 'bad_method', 'rate_limit'],
        };

        $query->where(function ($query) use ($riskFilter, $ruleCodes): void {
            $query
                ->where('risk_level', $riskFilter)
                ->orWhere(function ($query) use ($ruleCodes): void {
                    $query
                        ->where(function ($query): void {
                            $query->whereNull('risk_level')->orWhere('risk_level', '');
                        })
                        ->whereIn('rule_code', $ruleCodes);
                });
        });
    }

    protected function securityEventRiskPrioritySql(): string
    {
        return "CASE
            WHEN risk_level = 'critical' OR ((risk_level IS NULL OR risk_level = '') AND rule_code IN ('ip_blocklist', 'probe_abuse')) THEN 4
            WHEN risk_level = 'high' OR ((risk_level IS NULL OR risk_level = '') AND rule_code IN ('sql_injection', 'path_traversal', 'xss', 'bad_upload', 'bad_client', 'bad_payload')) THEN 3
            WHEN risk_level = 'medium' OR ((risk_level IS NULL OR risk_level = '') AND rule_code IN ('bad_path', 'bad_method', 'rate_limit')) THEN 2
            WHEN risk_level = 'low' THEN 1
            ELSE 0
        END";
    }

    /**
     * @param  array<int, string>  $globalAllowlist
     * @param  array<int, string>  $globalBlocklist
     * @param  array<int, string>  $siteAllowlist
     * @param  array<int, string>  $siteBlocklist
     * @return array<string, mixed>
     */
    protected function mapSuspiciousIpRow(object $row, array $globalAllowlist, array $globalBlocklist, array $siteAllowlist, array $siteBlocklist, ?object $latestEvent = null): array
    {
        $clientIp = (string) ($row->client_ip ?? '');
        $isGlobalAllowlisted = $this->ipMatchesList($clientIp, $globalAllowlist);
        $isGlobalBlocklisted = $this->ipMatchesList($clientIp, $globalBlocklist);
        $isSiteAllowlisted = $this->ipMatchesList($clientIp, $siteAllowlist);
        $isSiteBlocklisted = $this->ipMatchesList($clientIp, $siteBlocklist);
        $blockedUntilTs = $row->blocked_until ? strtotime((string) $row->blocked_until) : false;
        $hasActiveTemporaryBlock = $blockedUntilTs !== false && $blockedUntilTs > now('Asia/Shanghai')->getTimestamp();
        $rowStatus = (string) ($row->status ?? 'monitored');
        $effectiveRowStatus = $rowStatus === 'blocked' && ! $hasActiveTemporaryBlock
            ? 'monitored'
            : $rowStatus;
        $effectiveStatus = $isGlobalAllowlisted
            ? 'monitored'
            : ($isGlobalBlocklisted
                ? 'blocked'
                : ($isSiteAllowlisted
                    ? 'monitored'
                    : ($isSiteBlocklisted ? 'blocked' : $effectiveRowStatus)));
        $policyLabel = $isGlobalAllowlisted
            ? '平台已加白'
            : ($isGlobalBlocklisted
                ? '平台已拉黑'
                : ($isSiteAllowlisted
                    ? '已加白'
                    : ($isSiteBlocklisted ? '已拉黑' : '')));
        $lastRuleCode = (string) ($row->last_rule_code ?? '');
        $lastRuleProfile = $this->eventProfile($lastRuleCode, '');
        $requestQuery = trim((string) ($latestEvent?->request_query ?? ''));
        $userAgent = trim((string) ($latestEvent?->user_agent ?? ''));
        $referer = trim((string) ($latestEvent?->referer ?? ''));

        return [
            'client_ip' => $clientIp !== '' ? $clientIp : '--',
            'region_name' => trim((string) ($row->region_name ?? '')) ?: trim((string) ($latestEvent?->region_name ?? '')) ?: $this->resolveAttackRegionLabel($clientIp),
            'hit_count' => (int) ($row->hit_count ?? 0),
            'high_risk_count' => (int) ($row->high_risk_count ?? 0),
            'last_rule_code' => $lastRuleCode,
            'last_rule_label' => (string) ($lastRuleProfile['category_label'] ?? '异常请求'),
            'last_request_path' => (string) ($row->last_request_path ?? ''),
            'last_request_method' => strtoupper((string) ($latestEvent?->request_method ?? 'GET')),
            'last_request_query' => $requestQuery,
            'last_request_query_preview' => $this->compactSecurityText($requestQuery, 120, '无明显参数'),
            'last_user_agent_preview' => $this->compactSecurityText($userAgent, 96, '无 UA 记录'),
            'last_referer_preview' => $this->compactSecurityText($referer, 96, '无来源记录'),
            'status' => $effectiveStatus,
            'status_label' => $this->ipReputationStatusLabel($effectiveStatus),
            'blocked_until_label' => ($isGlobalAllowlisted || $isGlobalBlocklisted || $isSiteAllowlisted || $isSiteBlocklisted || ! $hasActiveTemporaryBlock) ? '' : date('m-d H:i', $blockedUntilTs),
            'last_seen_label' => $row->last_seen_at ? date('m-d H:i', strtotime((string) $row->last_seen_at)) : '--',
            'last_seen_at_ts' => $row->last_seen_at ? strtotime((string) $row->last_seen_at) : 0,
            'is_global_allowlisted' => $isGlobalAllowlisted,
            'is_global_blocklisted' => $isGlobalBlocklisted,
            'is_site_allowlisted' => $isSiteAllowlisted,
            'is_site_blocklisted' => $isSiteBlocklisted,
            'site_policy_label' => $policyLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    protected function compareSuspiciousIpRows(array $left, array $right): int
    {
        $statusWeight = static fn (string $status): int => match ($status) {
            'blocked' => 3,
            'limited' => 2,
            default => 1,
        };

        return [
            $statusWeight((string) ($right['status'] ?? 'monitored')),
            (int) ($right['high_risk_count'] ?? 0),
            (int) ($right['hit_count'] ?? 0),
            (int) ($right['last_seen_at_ts'] ?? 0),
            (string) ($right['client_ip'] ?? ''),
        ] <=> [
            $statusWeight((string) ($left['status'] ?? 'monitored')),
            (int) ($left['high_risk_count'] ?? 0),
            (int) ($left['hit_count'] ?? 0),
            (int) ($left['last_seen_at_ts'] ?? 0),
            (string) ($left['client_ip'] ?? ''),
        ];
    }

    protected function compactSecurityText(string $value, int $limit, string $fallback): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($plain === '') {
            return $fallback;
        }

        return mb_strlen($plain) > $limit
            ? mb_substr($plain, 0, $limit - 1).'…'
            : $plain;
    }

    protected function enrichRule(array $rule): array
    {
        $code = (string) ($rule['code'] ?? '');
        $level = (string) ($rule['risk_level'] ?? $this->defaultRiskLevel($code));
        $action = (string) ($rule['action'] ?? $this->defaultAction($code));

        return [
            ...$rule,
            'risk_level' => $level,
            'action' => $action,
        ];
    }

    protected function defaultRiskLevel(string $code): string
    {
        return match ($code) {
            'ip_blocklist', 'probe_abuse' => 'critical',
            'sql_injection', 'path_traversal', 'xss', 'bad_upload', 'bad_client', 'bad_payload' => 'high',
            'bad_path', 'bad_method', 'rate_limit' => 'medium',
            default => 'medium',
        };
    }

    protected function defaultAction(string $code): string
    {
        return match ($code) {
            'probe_abuse' => 'temporary_block',
            'rate_limit' => 'rate_limited',
            default => 'block',
        };
    }

    protected function riskLevelLabel(string $level): string
    {
        return match ($level) {
            'critical' => '严重',
            'high' => '高危',
            'medium' => '中危',
            'low' => '低危',
            default => '中危',
        };
    }

    protected function riskLevelPriority(string $level): int
    {
        return match ($level) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    protected function actionLabel(string $action): string
    {
        return match ($action) {
            'temporary_block' => '临时封禁',
            'rate_limited' => '限速拦截',
            'record' => '记录观察',
            default => '直接拦截',
        };
    }

    protected function matchIpReputationBlock(Request $request, int $siteId): ?array
    {
        if ($this->siteSecurityMode($siteId) === 'observe') {
            return null;
        }

        if (! $this->ipReputationTableReady()) {
            return null;
        }

        $ip = $request->ip();

        if (! $ip) {
            return null;
        }

        try {
            $now = now('Asia/Shanghai');
            $row = DB::table('site_security_ip_reputations')
                ->where('site_id', $siteId)
                ->where('ip_hash', hash('sha256', (string) $ip))
                ->where('status', 'blocked')
                ->where('blocked_until', '>', $now->toDateTimeString())
                ->first(['blocked_until']);
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'ip-reputation-db')) {
                Log::warning('Site security ip reputation lookup failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }

            return null;
        }

        if (! $row) {
            return null;
        }

        $blockedUntilTimestamp = strtotime((string) $row->blocked_until);

        if ($blockedUntilTimestamp !== false) {
            $this->seedRuntimeIpBlock($siteId, (string) $ip, max(1, $blockedUntilTimestamp - $now->getTimestamp()));
        }

        return $this->runtimeIpBlockRule('IP 临时封禁拦截');
    }

    protected function updateIpReputation(int $siteId, array $rule, Request $request, mixed $now): void
    {
        if (! $this->ipReputationTableReady()) {
            return;
        }

        $ip = $request->ip();

        if (! $ip) {
            return;
        }

        $ipHash = hash('sha256', (string) $ip);
        $code = (string) $rule['code'];
        $isHighRisk = in_array($code, static::HIGH_RISK_RULE_CODES, true) || in_array((string) $rule['risk_level'], ['high', 'critical'], true);
        $existing = DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->first(['blocked_until']);
        $isObserveMode = $this->siteSecurityMode($siteId) === 'observe';
        $recentHighRiskHits = $isHighRisk ? $this->hitRecentHighRiskCounter($siteId, $ipHash) : 0;
        $autoBlockEnabled = $this->systemSettings->securityMaliciousAutoBlockEnabled();
        $maliciousHits = $autoBlockEnabled && $this->shouldCountMaliciousAutoBlock($code)
            ? $this->hitRecentMaliciousCounter($siteId, $ipHash)
            : 0;
        $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();
        $autoBlockSeconds = $this->systemSettings->securityMaliciousAutoBlockSeconds();
        $shouldShortBlock = (string) $rule['action'] === 'temporary_block' || ($blockSeconds > 0 && $recentHighRiskHits >= 3);
        $shouldAutoBlock = $autoBlockEnabled
            && $autoBlockSeconds > 0
            && $maliciousHits >= $this->systemSettings->securityMaliciousAutoBlockThreshold();
        $shouldBlock = ! $isObserveMode && ($shouldShortBlock || $shouldAutoBlock);
        $blockedUntil = $shouldBlock && ($shouldAutoBlock || $blockSeconds > 0)
            ? $now->copy()->addSeconds($shouldAutoBlock ? $autoBlockSeconds : $blockSeconds)->toDateTimeString()
            : null;
        $currentBlockedUntil = $existing?->blocked_until ? strtotime((string) $existing->blocked_until) : false;

        if (! $isObserveMode && $currentBlockedUntil !== false && $currentBlockedUntil > $now->getTimestamp() && ($blockedUntil === null || $currentBlockedUntil > strtotime($blockedUntil))) {
            $shouldBlock = true;
            $blockedUntil = (string) $existing->blocked_until;
        }

        $values = [
            'client_ip' => (string) $ip,
            'last_rule_code' => $code,
            'last_request_path' => $this->normalizedRequestPath($request),
            'status' => $shouldBlock ? 'blocked' : ((! $isObserveMode && (string) $rule['action'] === 'rate_limited') ? 'limited' : 'monitored'),
            'blocked_until' => $blockedUntil,
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('site_security_ip_reputations')->insertOrIgnore([
            'site_id' => $siteId,
            'client_ip' => (string) $ip,
            'ip_hash' => $ipHash,
            'hit_count' => 0,
            'high_risk_count' => 0,
            'last_rule_code' => $code,
            'last_request_path' => $this->normalizedRequestPath($request),
            'status' => 'monitored',
            'blocked_until' => null,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('site_security_ip_reputations')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->update([
                ...$values,
                'hit_count' => DB::raw('hit_count + 1'),
                'high_risk_count' => $isHighRisk ? DB::raw('high_risk_count + 1') : DB::raw('high_risk_count'),
            ]);

        if ($shouldBlock && $blockedUntil !== null) {
            $blockedUntilTimestamp = strtotime($blockedUntil);

            if ($blockedUntilTimestamp !== false) {
                $this->seedRuntimeIpBlock($siteId, (string) $ip, max(1, $blockedUntilTimestamp - $now->getTimestamp()));
            }
        }
    }

    protected function ipReputationTableReady(): bool
    {
        return static::$ipReputationTableReady ??= Schema::hasTable('site_security_ip_reputations');
    }

    protected function ipReputationStatusLabel(string $status): string
    {
        return match ($status) {
            'blocked' => '已封禁',
            'limited' => '访问受限',
            default => '观察中',
        };
    }

    protected function siteSecurityModeLabel(string $mode): string
    {
        return match ($mode) {
            'observe' => '观察模式',
            'strict' => '严格模式',
            'custom' => '自定义模式',
            default => '标准模式',
        };
    }

    protected function trimHeader(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    protected function shouldSampleSecurityEvent(int $siteId, string $fingerprint): bool
    {
        return Cache::add(
            'site-security:event-sample:'.$siteId.':'.$fingerprint,
            1,
            now('Asia/Shanghai')->addSeconds(static::EVENT_SAMPLE_WINDOW_SECONDS)
        );
    }

    protected function hitRecentHighRiskCounter(int $siteId, string $ipHash): int
    {
        $key = 'site-security:recent-high-risk:'.$siteId.':'.$ipHash;

        try {
            RateLimiter::hit($key, 600);

            return RateLimiter::attempts($key);
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'high-risk-counter')) {
                Log::warning('Site security high risk counter failed.', [
                    'site_id' => $siteId,
                    'message' => $exception->getMessage(),
                ]);
            }

            return 0;
        }
    }

    protected function hitRecentMaliciousCounter(int $siteId, string $ipHash): int
    {
        $key = 'site-security:recent-malicious:'.$siteId.':'.$ipHash;

        try {
            RateLimiter::hit($key, $this->systemSettings->securityMaliciousAutoBlockWindowSeconds());

            return RateLimiter::attempts($key);
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'malicious-counter')) {
                Log::warning('Site security malicious counter failed.', [
                    'site_id' => $siteId,
                    'message' => $exception->getMessage(),
                ]);
            }

            return 0;
        }
    }

    protected function shouldCountMaliciousAutoBlock(string $code): bool
    {
        return in_array($code, static::MALICIOUS_AUTO_BLOCK_RULE_CODES, true);
    }

    protected function shouldLogRecordFailure(int $siteId): bool
    {
        return $this->shouldLogRuntimeFailure($siteId, 'record');
    }

    protected function shouldLogRuntimeFailure(int $siteId, string $scope): bool
    {
        $bucket = (int) floor(time() / 60);
        $key = $siteId.':'.$scope.':'.$bucket;

        if (isset(static::$recordFailureLogBuckets[$key])) {
            return false;
        }

        static::$recordFailureLogBuckets[$key] = $bucket;

        if (count(static::$recordFailureLogBuckets) > 100) {
            static::$recordFailureLogBuckets = array_slice(static::$recordFailureLogBuckets, -50, null, true);
        }

        return true;
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return static::$columnExistsCache[$key] ??= Schema::hasColumn($table, $column);
    }

    protected function requestQuerySample(Request $request): ?string
    {
        $query = $request->query();

        if ($query === []) {
            return null;
        }

        array_walk_recursive($query, function (&$value, $key): void {
            if ($this->isSensitiveQueryKey((string) $key)) {
                $value = '[filtered]';
            }
        });

        $json = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== '' ? mb_substr($json, 0, 1000) : null;
    }

    protected function isSensitiveQueryKey(string $key): bool
    {
        $key = mb_strtolower(trim($key));

        if ($key === '') {
            return false;
        }

        if (in_array($key, ['password', 'passwd', 'pwd', 'token', 'secret', 'authorization', 'auth'], true)) {
            return true;
        }

        return preg_match('/(^|[_-])(token|secret|api[_-]?key|access[_-]?key|private[_-]?key|session[_-]?id)($|[_-])/i', $key) === 1;
    }

    protected function eventFingerprint(int $siteId, array $rule, Request $request): string
    {
        return hash('sha256', implode('|', [
            $siteId,
            (string) $rule['code'],
            (string) ($request->ip() ?: 'guest'),
            mb_strtolower($this->normalizedRequestPath($request)),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function siteIpReasonSummary(int $siteId, string $ipHash): array
    {
        $since = now('Asia/Shanghai')->subHours(24)->toDateTimeString();

        return DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', $since)
            ->select(['rule_code', 'rule_name', 'risk_level'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MAX(created_at) as last_seen_at')
            ->groupBy('rule_code', 'rule_name', 'risk_level')
            ->orderByDesc('total')
            ->orderByDesc('last_seen_at')
            ->limit(6)
            ->get()
            ->map(function (object $row): array {
                $ruleCode = (string) ($row->rule_code ?? '');
                $profile = $this->eventProfile($ruleCode, (string) ($row->rule_name ?? ''));
                $riskLevel = trim((string) ($row->risk_level ?? '')) ?: $this->defaultRiskLevel($ruleCode);

                return [
                    'rule_code' => $ruleCode,
                    'rule_label' => (string) ($profile['category_label'] ?? '异常请求'),
                    'risk_level' => $riskLevel,
                    'risk_label' => $this->riskLevelLabel($riskLevel),
                    'total' => (int) ($row->total ?? 0),
                    'last_seen_label' => $row->last_seen_at ? date('m-d H:i', strtotime((string) $row->last_seen_at)) : '--',
                ];
            })
            ->values()
            ->all();
    }

    protected function pathMatchesAllowlist(Request $request, int $siteId): bool
    {
        $path = mb_strtolower($this->normalizedRequestPath($request));

        foreach ($this->sitePathAllowlist($siteId) as $allowedPath) {
            if ($this->pathMatchesPrefix($path, $allowedPath)) {
                return true;
            }
        }

        return false;
    }

    protected function exceptedRule(?array $rule, int $siteId): ?array
    {
        if ($rule === null) {
            return null;
        }

        return in_array((string) ($rule['code'] ?? ''), $this->siteRuleExceptions($siteId), true) ? null : $rule;
    }

    /**
     * @return array{mode: string, ip_allowlist: array<int, string>, ip_blocklist: array<int, string>, path_allowlist: array<int, string>, rule_exceptions: array<int, string>, custom_rate_limit_max_requests: ?int, custom_rate_limit_sensitive_max_requests: ?int, custom_scan_probe_threshold: ?int}
     */
    protected function sitePolicy(int $siteId): array
    {
        $cacheKey = 'site-security:site-policy:'.$siteId;
        $resolver = function () use ($siteId): array {
            $settings = DB::table('site_settings')
                ->where('site_id', $siteId)
                ->whereIn('setting_key', [
                    'security.mode',
                    'security.ip_allowlist',
                    'security.ip_blocklist',
                    'security.path_allowlist',
                    'security.rule_exceptions',
                    'security.custom_rate_limit_max_requests',
                    'security.custom_rate_limit_sensitive_max_requests',
                    'security.custom_scan_probe_threshold',
                ])
                ->pluck('setting_value', 'setting_key');

            return [
                'mode' => $this->normalizeSiteSecurityMode((string) ($settings['security.mode'] ?? 'standard')),
                'ip_allowlist' => $this->normalizeSiteIpList((string) ($settings['security.ip_allowlist'] ?? '')),
                'ip_blocklist' => $this->normalizeSiteIpList((string) ($settings['security.ip_blocklist'] ?? '')),
                'path_allowlist' => $this->normalizeSitePathAllowlist((string) ($settings['security.path_allowlist'] ?? '')),
                'rule_exceptions' => $this->normalizeSiteRuleExceptions((string) ($settings['security.rule_exceptions'] ?? '')),
                'custom_rate_limit_max_requests' => $this->normalizePositiveNullableInt((string) ($settings['security.custom_rate_limit_max_requests'] ?? '')),
                'custom_rate_limit_sensitive_max_requests' => $this->normalizePositiveNullableInt((string) ($settings['security.custom_rate_limit_sensitive_max_requests'] ?? '')),
                'custom_scan_probe_threshold' => $this->normalizePositiveNullableInt((string) ($settings['security.custom_scan_probe_threshold'] ?? '')),
            ];
        };

        return app()->runningUnitTests()
            ? $resolver()
            : Cache::remember($cacheKey, now('Asia/Shanghai')->addMinute(), $resolver);
    }

    protected function siteSecurityMode(int $siteId): string
    {
        return $this->sitePolicy($siteId)['mode'];
    }

    protected function normalizeSiteSecurityMode(string $mode): string
    {
        $mode = trim(mb_strtolower($mode));

        return in_array($mode, ['observe', 'standard', 'strict', 'custom'], true) ? $mode : 'standard';
    }

    protected function applySiteMode(?array $rule, string $mode): ?array
    {
        if ($rule === null) {
            return null;
        }

        if ($mode === 'observe') {
            $rule['action'] = 'record';
        }

        return $rule;
    }

    /**
     * @return array<int, string>
     */
    protected function siteIpAllowlist(int $siteId): array
    {
        return $this->sitePolicy($siteId)['ip_allowlist'];
    }

    /**
     * @return array<int, string>
     */
    protected function siteIpBlocklist(int $siteId): array
    {
        return $this->sitePolicy($siteId)['ip_blocklist'];
    }

    /**
     * @return array<int, string>
     */
    protected function sitePathAllowlist(int $siteId): array
    {
        return $this->sitePolicy($siteId)['path_allowlist'];
    }

    /**
     * @return array<int, string>
     */
    protected function siteRuleExceptions(int $siteId): array
    {
        return $this->sitePolicy($siteId)['rule_exceptions'];
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeSitePathAllowlist(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item): string {
                $path = '/'.ltrim(parse_url($item, PHP_URL_PATH) ?: $item, '/');

                return mb_strtolower(rtrim($path, '/')) ?: '/';
            })
            ->map(fn (string $item): string => $item === '' ? '/' : $item)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeSiteIpList(string $value): array
    {
        return collect(preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function customRateLimitMaxRequests(int $siteId): ?int
    {
        return $this->sitePolicy($siteId)['custom_rate_limit_max_requests'];
    }

    protected function customRateLimitSensitiveMaxRequests(int $siteId): ?int
    {
        return $this->sitePolicy($siteId)['custom_rate_limit_sensitive_max_requests'];
    }

    protected function customScanProbeThreshold(int $siteId): ?int
    {
        return $this->sitePolicy($siteId)['custom_scan_probe_threshold'];
    }

    protected function normalizePositiveNullableInt(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeSiteRuleExceptions(string $value): array
    {
        $allowed = collect([
            'bad_path',
            'sql_injection',
            'xss',
            'path_traversal',
            'bad_upload',
            'rate_limit',
            'probe_abuse',
            'ip_blocklist',
            'bad_client',
            'bad_method',
            'bad_payload',
        ]);

        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn ($item): string => trim(mb_strtolower((string) $item)))
            ->filter(fn (string $item): bool => $item !== '' && $allowed->contains($item))
            ->unique()
            ->values()
            ->all();
    }

    protected function matchBadMethod(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockBadMethodEnabled()) {
            return null;
        }

        $method = strtoupper((string) $request->method());

        if (in_array($method, ['TRACE', 'TRACK', 'CONNECT', 'DEBUG'], true)) {
            return ['code' => 'bad_method', 'name' => '异常请求方法拦截'];
        }

        return null;
    }

    protected function matchBadClient(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockBadClientEnabled()) {
            return null;
        }

        $userAgent = mb_strtolower(trim((string) $request->userAgent()));

        if ($userAgent === '') {
            return null;
        }

        $needles = [
            'sqlmap',
            'nuclei',
            'nikto',
            'acunetix',
            'nessus',
            'openvas',
            'zgrab',
            'masscan',
            'python-requests',
            'python-httpx',
            'go-http-client',
            'libwww-perl',
            'java/',
        ];

        foreach ($needles as $needle) {
            if (str_contains($userAgent, $needle)) {
                return ['code' => 'bad_client', 'name' => '脚本扫描器拦截'];
            }
        }

        if (preg_match('/\b(curl|wget)\/[0-9]/i', $userAgent) === 1) {
            return ['code' => 'bad_client', 'name' => '脚本扫描器拦截'];
        }

        return null;
    }

    protected function matchBadPayload(Request $request): ?array
    {
        if (! $this->systemSettings->securityBlockBadPayloadEnabled()) {
            return null;
        }

        $metrics = $this->requestPayloadMetrics($request);

        if ($metrics['fields'] > $this->systemSettings->securityPayloadMaxFields()) {
            return ['code' => 'bad_payload', 'name' => '异常请求参数拦截'];
        }

        if ($metrics['max_length'] > $this->systemSettings->securityPayloadMaxValueLength()) {
            return ['code' => 'bad_payload', 'name' => '异常请求参数拦截'];
        }

        return null;
    }

    /**
     * @return array{fields: int, max_length: int}
     */
    protected function requestPayloadMetrics(Request $request): array
    {
        $metrics = ['fields' => 0, 'max_length' => 0];

        foreach ([$request->query(), $request->request->all()] as $input) {
            $this->walkPayloadInput($input, $metrics);
        }

        return $metrics;
    }

    /**
     * @param  array{fields: int, max_length: int}  $metrics
     */
    protected function walkPayloadInput(mixed $value, array &$metrics): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->walkPayloadInput($child, $metrics);
            }

            return;
        }

        if (! is_scalar($value) && $value !== null) {
            return;
        }

        $metrics['fields']++;
        $metrics['max_length'] = max($metrics['max_length'], mb_strlen((string) $value));
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
            '/server-info',
            '/thinkphp',
            '/runtime/log',
            '/storage/logs',
            '/debug/default/view',
            '/.aws',
            '/.docker',
        ];
        $suffixMatches = [
            '/.env',
            '/.env.local',
            '/.env.production',
            '/.env.backup',
            '/.git/config',
            '/.svn/entries',
            '/.ds_store',
            '/.user.ini',
            '/.htaccess',
            '/.htpasswd',
            '/phpinfo.php',
            '/composer.json',
            '/composer.lock',
            '/package.json',
            '/package-lock.json',
            '/pnpm-lock.yaml',
            '/yarn.lock',
            '/vite.config.js',
            '/phpunit.xml',
            '/config/database.php',
        ];
        $riskyExtensions = [
            '.bak',
            '.backup',
            '.conf',
            '.config',
            '.dump',
            '.ini',
            '.log',
            '.old',
            '.orig',
            '.sql',
            '.swp',
            '.tar',
            '.tar.gz',
            '.tgz',
            '.zip',
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

        foreach ($riskyExtensions as $extension) {
            if (str_ends_with($path, $extension)) {
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
            $isObserveMode = $this->siteSecurityMode($siteId) === 'observe';
            $window = $this->systemSettings->securityRateLimitWindowSeconds();
            $isSensitive = $this->isSensitiveRequest($request);
            $maxAttempts = $isSensitive
                ? $this->systemSettings->securityRateLimitSensitiveMaxRequests()
                : $this->systemSettings->securityRateLimitMaxRequests();
            $maxAttempts = $this->rateLimitMaxAttemptsForMode($siteId, $maxAttempts, $isSensitive);
            $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();
            $blockKey = $this->rateLimitBlockKey($siteId, $request);

            if (! $isObserveMode && $blockSeconds > 0 && RateLimiter::tooManyAttempts($blockKey, 1)) {
                return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
            }

            if ($this->isFrontendPageRequest($request)) {
                $siteWideKey = 'site-security-rate:'.$siteId.':site:'.sha1($request->ip() ?: 'guest');
                $siteWideMaxAttempts = $this->rateLimitMaxAttemptsForMode(
                    $siteId,
                    $this->systemSettings->securityRateLimitMaxRequests(),
                    false
                );

                if (RateLimiter::tooManyAttempts($siteWideKey, $siteWideMaxAttempts)) {
                    if (! $isObserveMode && $blockSeconds > 0) {
                        RateLimiter::hit($blockKey, $blockSeconds);
                    }

                    return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
                }

                RateLimiter::hit($siteWideKey, $window);
            }

            if ($this->isStateChangingRequest($request)) {
                $formWideKey = 'site-security-rate:'.$siteId.':form:'.sha1($request->ip() ?: 'guest');

                if (RateLimiter::tooManyAttempts($formWideKey, $maxAttempts)) {
                    if (! $isObserveMode && $blockSeconds > 0) {
                        RateLimiter::hit($blockKey, $blockSeconds);
                    }

                    return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
                }

                RateLimiter::hit($formWideKey, $window);
            }

            if ($this->isMediaRequest($request)) {
                $mediaWideKey = 'site-security-rate:'.$siteId.':media:'.sha1($request->ip() ?: 'guest');

                if (RateLimiter::tooManyAttempts($mediaWideKey, $maxAttempts)) {
                    if (! $isObserveMode && $blockSeconds > 0) {
                        RateLimiter::hit($blockKey, $blockSeconds);
                    }

                    return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
                }

                RateLimiter::hit($mediaWideKey, $window);
            }

            if ($scanRule = $this->matchRapidPathScan($request, $siteId)) {
                if (! $isObserveMode && $blockSeconds > 0) {
                    RateLimiter::hit($this->probeBlockKey($siteId, $request), $blockSeconds);
                }

                return $scanRule;
            }

            $key = 'site-security-rate:'.$siteId.':'.sha1(
                ($request->ip() ?: 'guest').'|'.mb_strtolower($this->normalizedRequestPath($request))
            );

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                if (! $isObserveMode && $blockSeconds > 0) {
                    RateLimiter::hit($blockKey, $blockSeconds);
                }

                return ['code' => 'rate_limit', 'name' => '频繁刷新拦截'];
            }

            RateLimiter::hit($key, $window);
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'rate-limit')) {
                Log::warning('Site security rate limit cache failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }

            return null;
        }

        return null;
    }

    protected function matchRapidPathScan(Request $request, int $siteId): ?array
    {
        if (! $this->systemSettings->securityScanProbeEnabled()) {
            return null;
        }

        if ($this->isFrontendPageRequest($request) || $this->isMediaRequest($request) || $this->isStateChangingRequest($request)) {
            return null;
        }

        if (! in_array(strtoupper((string) $request->method()), ['GET', 'HEAD'], true)) {
            return null;
        }

        $ip = $request->ip();

        if (! $ip) {
            return null;
        }

        $window = $this->systemSettings->securityScanProbeWindowSeconds();
        $path = mb_strtolower($this->normalizedRequestPath($request));
        $cacheKey = $this->pathScanKey($siteId, $ip);
        $entries = Cache::get($cacheKey, []);
        $now = now('Asia/Shanghai')->getTimestamp();
        $cutoff = $now - max(1, $window);

        if (! is_array($entries)) {
            $entries = [];
        }

        $normalizedEntries = [];

        foreach ($entries as $entryPath => $seenAt) {
            if (is_int($entryPath) && is_string($seenAt) && $seenAt !== '') {
                continue;
            }

            if (is_string($entryPath) && $entryPath !== '' && is_numeric($seenAt) && (int) $seenAt >= $cutoff) {
                $normalizedEntries[$entryPath] = (int) $seenAt;
            }
        }

        $normalizedEntries[$path] = $now;
        Cache::put($cacheKey, $normalizedEntries, now('Asia/Shanghai')->addSeconds(max(1, $window)));

        if (count($normalizedEntries) < $this->probeThresholdForMode($siteId, $this->systemSettings->securityScanProbeThreshold())) {
            return null;
        }

        return ['code' => 'probe_abuse', 'name' => '扫描试探超限'];
    }

    protected function matchProbeBlock(Request $request, int $siteId): ?array
    {
        if ($this->siteSecurityMode($siteId) === 'observe') {
            return null;
        }

        if (! $this->systemSettings->securityScanProbeEnabled()) {
            return null;
        }

        $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();

        if ($blockSeconds <= 0) {
            return null;
        }

        $blockKey = $this->probeBlockKey($siteId, $request);

        try {
            if (RateLimiter::tooManyAttempts($blockKey, 1)) {
                return ['code' => 'probe_abuse', 'name' => '扫描试探超限', '_skip_record' => true];
            }
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'probe-block')) {
                Log::warning('Site security probe block cache failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function matchRuntimeIpBlock(Request $request, int $siteId): ?array
    {
        if ($this->siteSecurityMode($siteId) === 'observe') {
            return null;
        }

        $ip = $request->ip();

        if (! $ip) {
            return null;
        }

        try {
            if (RateLimiter::tooManyAttempts($this->reputationBlockKey($siteId, (string) $ip), 1)) {
                return $this->runtimeIpBlockRule('IP 临时封禁拦截');
            }

            if (RateLimiter::tooManyAttempts($this->maliciousBlockKey($siteId, (string) $ip), 1)) {
                return $this->runtimeIpBlockRule('连续攻击封禁拦截');
            }
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'runtime-block')) {
                Log::warning('Site security runtime block cache failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }
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
        $threshold = $this->probeThresholdForMode($siteId, $this->systemSettings->securityScanProbeThreshold());
        $blockSeconds = $this->systemSettings->securityRateLimitBlockSeconds();
        $totalKey = $this->probeTotalKey($siteId, $request);
        $ruleKey = $this->probeRuleKey($siteId, $request, (string) $rule['code']);

        try {
            RateLimiter::hit($totalKey, $window);
            RateLimiter::hit($ruleKey, $window);

            if (! RateLimiter::tooManyAttempts($totalKey, $threshold) && ! RateLimiter::tooManyAttempts($ruleKey, $threshold)) {
                return null;
            }

            if ($this->siteSecurityMode($siteId) !== 'observe' && $blockSeconds > 0) {
                RateLimiter::hit($this->probeBlockKey($siteId, $request), $blockSeconds);
            }

            return ['code' => 'probe_abuse', 'name' => '扫描试探超限'];
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'probe-escalation')) {
                Log::warning('Site security probe escalation cache failed.', [
                    'site_id' => $siteId,
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                ]);
            }

            return null;
        }
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

        if ($this->isMediaRequest($request)) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if (is_string($routeName) && $routeName !== '') {
            if (str_starts_with($routeName, 'site.') || in_array($routeName, ['login', 'login.captcha'], true)) {
                return true;
            }
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

        if ($this->isStateChangingRequest($request)) {
            return true;
        }

        foreach (['/guestbook', '/payroll', '/login'] as $needle) {
            if (str_starts_with($path, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function isStateChangingRequest(Request $request): bool
    {
        return in_array(strtoupper((string) $request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    protected function isMediaRequest(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');
        $normalizedPath = mb_strtolower($path);

        return $normalizedPath === 'site-media'
            || str_starts_with($normalizedPath, 'site-media/')
            || $normalizedPath === 'atts'
            || str_starts_with($normalizedPath, 'atts/')
            || $normalizedPath === 'theme-assets'
            || str_starts_with($normalizedPath, 'theme-assets/')
            || $normalizedPath === 'up'
            || str_starts_with($normalizedPath, 'up/')
            || $this->isStaticAssetPath($normalizedPath);
    }

    protected function isStaticAssetPath(string $path): bool
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $extension = mb_strtolower((string) $extension);

        if ($extension === '') {
            return false;
        }

        return in_array($extension, [
            'css', 'js', 'mjs', 'map',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            'mp3', 'wav', 'ogg', 'mp4', 'webm',
            'pdf', 'txt', 'xml', 'json',
        ], true);
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
            'ip_blocklist' => 'blocked_ip_blocklist',
            'bad_client' => 'blocked_bad_client',
            'bad_method' => 'blocked_bad_method',
            'bad_payload' => 'blocked_bad_payload',
            default => 'blocked_total',
        };
    }

    protected function eventProfile(string $ruleCode, string $ruleName): array
    {
        return match ($ruleCode) {
            'sql_injection' => [
                'category_label' => 'SQL 注入攻击',
            ],
            'xss' => [
                'category_label' => 'XSS 脚本攻击',
            ],
            'path_traversal' => [
                'category_label' => '路径穿越探测',
            ],
            'bad_upload' => [
                'category_label' => '可疑上传尝试',
            ],
            'probe_abuse' => [
                'category_label' => '扫描试探超限',
            ],
            'rate_limit' => [
                'category_label' => '异常高频访问',
            ],
            'bad_path' => [
                'category_label' => '恶意扫描路径',
            ],
            'bad_client' => [
                'category_label' => '脚本扫描器',
            ],
            'bad_method' => [
                'category_label' => '异常请求方法',
            ],
            'bad_payload' => [
                'category_label' => '异常请求参数',
            ],
            'ip_blocklist' => [
                'category_label' => 'IP 黑名单',
            ],
            default => [
                'category_label' => $ruleName !== '' ? $ruleName : '异常请求',
            ],
        };
    }

    /**
     * @param  array<int, string>  $patterns
     */
    protected function ipMatchesList(string $ip, array $patterns): bool
    {
        if ($ip === '' || $patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            if ($pattern === $ip) {
                return true;
            }

            if (str_contains($pattern, '/') && $this->ipMatchesCidr($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);

        if (! is_numeric($mask) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $mask = (int) $mask;

        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask));

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
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
            'ip_blocklist' => '命中平台黑名单的固定来源 IP 或网段。',
            'bad_client' => 'sqlmap、nuclei 等脚本扫描器客户端特征。',
            'bad_method' => 'TRACE、TRACK、CONNECT、DEBUG 等异常请求方法。',
            'bad_payload' => '参数数量异常或单个参数过长的 payload 灌入。',
            default => '安护盾记录到的异常访问类型。',
        };
    }

    protected function resolveAttackRegionLabel(string $ip): string
    {
        return $this->ipRegionResolver->resolve($ip);
    }

    protected function pruneEvents(int $siteId): void
    {
        $limit = $this->systemSettings->securityEventRetentionLimit();
        $keepIds = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');
        $highRiskKeepIds = DB::table('site_security_events')
            ->where('site_id', $siteId)
            ->whereIn('rule_code', static::HIGH_RISK_RULE_CODES)
            ->orderByDesc('id')
            ->limit(max(20, min(200, (int) floor($limit / 2))))
            ->pluck('id');

        $preservedIds = $keepIds
            ->concat($highRiskKeepIds)
            ->unique()
            ->values();

        if ($preservedIds->isNotEmpty()) {
            do {
                $deleteIds = DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->whereNotIn('id', $preservedIds->all())
                    ->orderBy('id')
                    ->limit(static::EVENT_PRUNE_BATCH_SIZE)
                    ->pluck('id');

                if ($deleteIds->isEmpty()) {
                    break;
                }

                DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->whereIn('id', $deleteIds->all())
                    ->delete();
            } while ($deleteIds->count() === static::EVENT_PRUNE_BATCH_SIZE);
        } else {
            $deleteIds = DB::table('site_security_events')
                ->where('site_id', $siteId)
                ->orderBy('id')
                ->limit(static::EVENT_PRUNE_BATCH_SIZE)
                ->pluck('id');

            if ($deleteIds->isNotEmpty()) {
                DB::table('site_security_events')
                    ->where('site_id', $siteId)
                    ->whereIn('id', $deleteIds->all())
                    ->delete();
            }
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

    protected function isProbeCandidateRule(string $code): bool
    {
        return in_array($code, ['bad_path', 'sql_injection', 'xss', 'path_traversal', 'bad_upload'], true);
    }

    protected function rateLimitMaxAttemptsForMode(int $siteId, int $maxAttempts, bool $isSensitive): int
    {
        $mode = $this->siteSecurityMode($siteId);

        if ($mode === 'custom') {
            $custom = $isSensitive
                ? $this->customRateLimitSensitiveMaxRequests($siteId)
                : $this->customRateLimitMaxRequests($siteId);

            return $custom ?? $maxAttempts;
        }

        if ($mode === 'strict') {
            return max(1, (int) floor($maxAttempts * 0.6));
        }

        return $maxAttempts;
    }

    protected function probeThresholdForMode(int $siteId, int $threshold): int
    {
        $mode = $this->siteSecurityMode($siteId);

        if ($mode === 'custom') {
            return $this->customScanProbeThreshold($siteId) ?? $threshold;
        }

        if ($mode === 'strict') {
            return max(1, $threshold - 1);
        }

        return $threshold;
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

    protected function reputationBlockKey(int $siteId, string $ip): string
    {
        return 'site-security-reputation-block:'.$siteId.':'.sha1($ip !== '' ? $ip : 'guest');
    }

    protected function maliciousBlockKey(int $siteId, string $ip): string
    {
        return 'site-security-malicious-block:'.$siteId.':'.sha1($ip !== '' ? $ip : 'guest');
    }

    protected function seedRuntimeIpBlock(int $siteId, string $ip, int $seconds): void
    {
        try {
            RateLimiter::hit($this->reputationBlockKey($siteId, $ip), max(1, $seconds));
        } catch (\Throwable $exception) {
            if ($this->shouldLogRuntimeFailure($siteId, 'seed-runtime-block')) {
                Log::warning('Site security runtime block seed failed.', [
                    'site_id' => $siteId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function runtimeIpBlockRule(string $name): array
    {
        return [
            'code' => 'probe_abuse',
            'name' => $name,
            'risk_level' => 'critical',
            'action' => 'temporary_block',
            '_skip_record' => true,
        ];
    }

    protected function pathScanKey(int $siteId, string $ip): string
    {
        return 'site-security-path-scan:'.$siteId.':'.sha1($ip);
    }

    protected function rateLimitBlockKey(int $siteId, Request $request): string
    {
        return 'site-security-rate-block:'.$siteId.':'.sha1($request->ip() ?: 'guest');
    }

    /**
     * @return Collection<int, UploadedFile>
     */
    protected function flattenFiles(mixed $files): Collection
    {
        if (is_array($files)) {
            return collect($files)->flatMap(fn ($item) => $this->flattenFiles($item));
        }

        if ($files instanceof UploadedFile) {
            return collect([$files]);
        }

        return collect();
    }
}
