<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SiteSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected SiteSecurity $siteSecurity,
    ) {
    }

    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $this->isPlatformAdmin($request->user()->id)) {
            return redirect()->route('admin.site-dashboard');
        }

        $sites = $this->adminSites($request->user()->id);
        $currentSite = $this->currentSite($request);
        $recentContents = DB::table('contents')
            ->join('sites', 'sites.id', '=', 'contents.site_id')
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->orderByDesc('contents.updated_at')
            ->orderByDesc('contents.id')
            ->limit(3)
            ->get([
                'contents.id',
                'contents.title',
                'contents.status',
                'contents.updated_at',
                'sites.name as site_name',
                'sites.site_key',
            ]);

        $platformNotices = $this->platformNoticeItems(3);
        $platformNoticeSiteKey = $this->platformSiteKey();
        $insights = $this->globalInsights();

        return view('admin.platform.dashboard', [
            'sites' => $sites,
            'currentSite' => $currentSite,
            'recentContents' => $recentContents,
            'platformNotices' => $platformNotices,
            'platformNoticeSiteKey' => $platformNoticeSiteKey,
            'showPlatformNoticeLink' => $platformNoticeSiteKey !== '',
            'insights' => $insights,
        ]);
    }

    private function globalInsights(): array
    {
        $today = now('Asia/Shanghai')->startOfDay();
        $yesterday = $today->copy()->subDay();
        $sevenDaysAgo = $today->copy()->subDays(6);
        $thirtyDaysAgo = $today->copy()->subDays(29);

        $visitRows = DB::table('site_visit_daily_stats')
            ->whereBetween('stat_date', [$thirtyDaysAgo->toDateString(), $today->toDateString()])
            ->orderBy('stat_date')
            ->get(['stat_date', 'page_views']);

        $visitsByDate = $visitRows->groupBy(fn (object $row): string => (string) $row->stat_date)
            ->map(fn ($rows): int => (int) collect($rows)->sum('page_views'));

        $todayPageViews = (int) ($visitsByDate->get($today->toDateString()) ?? 0);
        $yesterdayPageViews = (int) ($visitsByDate->get($yesterday->toDateString()) ?? 0);
        $todayDelta = $todayPageViews - $yesterdayPageViews;

        $trend = collect(range(6, 0))
            ->map(function (int $offset) use ($today, $visitsByDate): array {
                $date = $today->copy()->subDays($offset);
                $value = (int) ($visitsByDate->get($date->toDateString()) ?? 0);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('m-d'),
                    'value' => $value,
                ];
            })
            ->values();

        $trendMax = max(1, (int) $trend->max('value'));
        $trend = $trend->map(fn (array $item): array => $item + [
            'height' => max(16, (int) round(($item['value'] / $trendMax) * 100)),
        ])->all();

        $weekPageViews = (int) $visitRows
            ->filter(fn (object $row): bool => (string) $row->stat_date >= $sevenDaysAgo->toDateString())
            ->sum('page_views');
        $monthPageViews = (int) $visitRows->sum('page_views');

        $securitySummary = $this->globalSecuritySummary($today);

        $topArticlesRows = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->leftJoin('sites', 'sites.id', '=', 'contents.site_id')
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->where(function ($query) use ($today): void {
                $cutoff = $today->copy()->subDays(30)->startOfDay()->toDateTimeString();
                $query->where('contents.published_at', '>=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('contents.published_at')
                            ->where('contents.created_at', '>=', $cutoff);
                    });
            })
            ->orderByDesc('contents.view_count')
            ->orderByDesc('contents.published_at')
            ->orderByDesc('contents.id')
            ->limit(3)
            ->get([
                'contents.id',
                'contents.title',
                'contents.status',
                'contents.view_count',
                'channels.name as channel_name',
                'sites.name as site_name',
            ]);

        $articleMaxViews = max(1, (int) ($topArticlesRows->max('view_count') ?? 0));
        $topArticles = $topArticlesRows->map(fn (object $article): array => [
            'id' => (int) $article->id,
            'title' => (string) $article->title,
            'channel_name' => trim(implode(' · ', array_filter([
                (string) ($article->site_name ?? '未分站点'),
                (string) ($article->channel_name ?? '未分栏'),
            ]))),
            'status' => (string) $article->status,
            'view_count' => (int) $article->view_count,
            'bar_width' => max(10, (int) round(((int) $article->view_count / $articleMaxViews) * 100)),
        ])->all();

        $topAuthorsRows = DB::table('contents')
            ->leftJoin('users', 'users.id', '=', 'contents.created_by')
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->where('contents.created_at', '>=', $today->copy()->startOfYear()->toDateTimeString())
            ->groupBy('contents.created_by', 'users.name', 'users.username')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('contents.created_by')
            ->limit(3)
            ->get([
                'contents.created_by',
                DB::raw("COALESCE(NULLIF(users.name, ''), NULLIF(users.username, ''), '未记录') AS author_name"),
                DB::raw('COUNT(*) AS total_count'),
                DB::raw("SUM(CASE WHEN contents.status = 'published' THEN 1 ELSE 0 END) AS published_count"),
            ]);

        $authorMaxCount = max(1, (int) ($topAuthorsRows->max('total_count') ?? 0));
        $topAuthors = $topAuthorsRows->map(fn (object $author): array => [
            'name' => (string) $author->author_name,
            'total_count' => (int) $author->total_count,
            'published_count' => (int) $author->published_count,
            'bar_width' => max(10, (int) round(((int) $author->total_count / $authorMaxCount) * 100)),
        ])->all();

        $totalAttachments = (int) DB::table('attachments')->count();
        $usedAttachmentBytes = (int) DB::table('attachments')->sum('size');
        $usedAttachments = (int) DB::table('attachments')
            ->where('usage_count', '>', 0)
            ->count();
        $unusedAttachments = max(0, $totalAttachments - $usedAttachments);
        $unusedRatio = $totalAttachments > 0
            ? (int) round(($unusedAttachments / $totalAttachments) * 100)
            : 0;

        return [
            'hero' => [
                [
                    'label' => '今日访问',
                    'value' => number_format($todayPageViews),
                    'accent' => 'visits',
                    'note' => $todayDelta === 0
                        ? '和昨天持平'
                        : (($todayDelta > 0 ? '较昨天 +' : '较昨天 ') . number_format($todayDelta) . ' PV'),
                ],
                [
                    'label' => '近 7 天访问',
                    'value' => number_format($weekPageViews),
                    'accent' => 'trend',
                    'note' => '近 30 天累计 ' . number_format($monthPageViews) . ' PV',
                ],
                [
                    'label' => '今日拦截攻击',
                    'value' => number_format($securitySummary['today_blocked']),
                    'accent' => 'security',
                    'note' => ! $securitySummary['enabled']
                        ? '安护盾未启用'
                        : ($securitySummary['today_blocked'] > 0 ? '全系统今日已拦下异常请求' : '今日暂未命中异常请求'),
                ],
                [
                    'label' => '总拦截次数',
                    'value' => number_format($securitySummary['total_blocked']),
                    'accent' => 'security-total',
                    'note' => ! $securitySummary['enabled']
                        ? '平台端已关闭安护盾'
                        : ($securitySummary['total_blocked'] > 0 ? '安护盾正在全系统持续生效' : '安护盾已启用，等待后续统计'),
                ],
            ],
            'trend' => $trend,
            'top_articles' => $topArticles,
            'top_authors' => $topAuthors,
            'assets' => [
                'total' => $totalAttachments,
                'used' => $usedAttachments,
                'unused' => $unusedAttachments,
                'unused_ratio' => $unusedRatio,
                'used_ratio' => $totalAttachments > 0 ? 100 - $unusedRatio : 0,
                'used_size_label' => $this->humanReadableBytes($usedAttachmentBytes),
                'storage_limit_label' => '全系统资源库',
            ],
        ];
    }

    private function globalSecuritySummary($today): array
    {
        return [
            'enabled' => $this->siteSecurity->protectionEnabled(),
            'today_blocked' => (int) DB::table('site_security_daily_stats')
                ->where('stat_date', $today->toDateString())
                ->sum('blocked_total'),
            'total_blocked' => (int) DB::table('site_security_daily_stats')->sum('blocked_total'),
        ];
    }

    private function humanReadableBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return rtrim(rtrim(number_format($value, $power === 0 ? 0 : 1, '.', ''), '0'), '.') . ' ' . $units[$power];
    }
}
