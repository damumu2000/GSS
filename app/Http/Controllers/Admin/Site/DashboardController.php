<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteStorageUsage;
use App\Support\SiteSecurity;
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
     * Display the site-operator dashboard.
     */
    public function __invoke(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $sitePermissionCodes = $this->sitePermissionCodes($request->user()->id, $currentSite->id);
        $articleReviewEnabled = DB::table('site_settings')
            ->where('site_id', $currentSite->id)
            ->where('setting_key', 'content.article_requires_review')
            ->value('setting_value') === '1';

        $contentQuery = DB::table('contents')->where('site_id', $currentSite->id);
        $this->applySiteContentVisibilityScope($contentQuery, $request->user()->id, $currentSite->id);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $recentContents = (clone $contentQuery)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get(['id', 'title', 'type', 'status', 'updated_at', 'channel_id']);

        $pendingContents = (clone $contentQuery)
            ->whereIn('status', ['draft', 'pending'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get(['id', 'title', 'type', 'status', 'updated_at']);

        $platformNoticeChannelId = $this->ensurePlatformNoticeChannelId($request->user()->id);
        $platformNotices = $this->platformNoticeItems(3);
        $platformNoticeSiteKey = $this->platformSiteKey();
        $securitySummary = $this->siteSecurity->dashboardSummary((int) $currentSite->id);
        $insights = $this->siteInsights((int) $currentSite->id, (int) $request->user()->id, $securitySummary);

        $quickLinks = collect([
            ['code' => 'content.manage', 'label' => '文章管理', 'route' => 'admin.articles.index'],
            ['code' => 'content.manage', 'label' => '单页面管理', 'route' => 'admin.pages.index'],
            ['code' => 'channel.manage', 'label' => '栏目管理', 'route' => 'admin.channels.index'],
            ['code' => 'promo.manage', 'label' => '图宣管理', 'route' => 'admin.promos.index'],
            ['code' => 'attachment.manage', 'label' => '附件管理', 'route' => 'admin.attachments.index'],
            ['code' => 'theme.use', 'label' => '模板管理', 'route' => 'admin.themes.index'],
            ['code' => 'setting.manage', 'label' => '站点设置', 'route' => 'admin.settings.index'],
            ['code' => 'site.user.manage', 'label' => '操作员管理', 'route' => 'admin.site-users.index'],
            ['code' => 'log.view', 'label' => '站点日志', 'route' => 'admin.site-logs.index'],
        ])->filter(fn (array $item) => in_array($item['code'], $sitePermissionCodes, true))
            ->values();

        return view('admin.site.dashboard', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'recentContents' => $recentContents,
            'pendingContents' => $pendingContents,
            'platformNotices' => $platformNotices,
            'platformNoticeSiteKey' => $platformNoticeSiteKey,
            'platformNoticeChannelId' => $platformNoticeChannelId,
            'showPlatformNoticeLink' => $platformNoticeSiteKey !== '',
            'sitePermissionCodes' => $sitePermissionCodes,
            'quickLinks' => $quickLinks,
            'canManageContent' => in_array('content.manage', $sitePermissionCodes, true),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'manageableChannelCount' => count($manageableChannelIds),
            'articleReviewEnabled' => $articleReviewEnabled,
            'isChannelRestricted' => ! $this->canViewAllSiteContent($request->user()->id, $currentSite->id)
                && $manageableChannelIds !== [],
            'insights' => $insights,
        ]);
    }

    private function siteInsights(int $siteId, int $userId, array $securitySummary): array
    {
        $today = now('Asia/Shanghai')->startOfDay();
        $yesterday = $today->copy()->subDay();
        $sevenDaysAgo = $today->copy()->subDays(6);
        $thirtyDaysAgo = $today->copy()->subDays(29);

        $visitRows = DB::table('site_visit_daily_stats')
            ->where('site_id', $siteId)
            ->whereBetween('stat_date', [$thirtyDaysAgo->toDateString(), $today->toDateString()])
            ->orderBy('stat_date')
            ->get([
                'stat_date',
                'page_views',
                'article_views',
                'channel_views',
                'home_views',
            ]);

        $visitsByDate = $visitRows->keyBy(fn (object $row): string => (string) $row->stat_date);
        $todayRow = $visitsByDate->get($today->toDateString());
        $yesterdayRow = $visitsByDate->get($yesterday->toDateString());

        $trend = collect(range(6, 0))
            ->map(function (int $offset) use ($today, $visitsByDate): array {
                $date = $today->copy()->subDays($offset);
                $row = $visitsByDate->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('m-d'),
                    'value' => (int) ($row->page_views ?? 0),
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
        $todayPageViews = (int) ($todayRow->page_views ?? 0);
        $yesterdayPageViews = (int) ($yesterdayRow->page_views ?? 0);
        $todayDelta = $todayPageViews - $yesterdayPageViews;

        $topArticlesQuery = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $siteId)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->where(function ($query) use ($today): void {
                $cutoff = $today->copy()->subDays(30)->startOfDay()->toDateTimeString();
                $query->where('contents.published_at', '>=', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff): void {
                        $fallback->whereNull('contents.published_at')
                            ->where('contents.created_at', '>=', $cutoff);
                    });
            });

        $topArticles = $topArticlesQuery
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
            ]);

        $articleMaxViews = max(1, (int) ($topArticles->max('view_count') ?? 0));
        $topArticles = $topArticles->map(fn (object $article): array => [
            'id' => (int) $article->id,
            'title' => (string) $article->title,
            'channel_name' => (string) ($article->channel_name ?? '未分栏'),
            'status' => (string) $article->status,
            'view_count' => (int) $article->view_count,
            'bar_width' => max(10, (int) round(((int) $article->view_count / $articleMaxViews) * 100)),
        ])->all();

        $hottestArticle = $topArticles[0] ?? [
            'title' => '暂无数据',
            'view_count' => 0,
            'channel_name' => '当前站点还没有文章浏览数据',
        ];

        $topAuthorsQuery = DB::table('contents')
            ->leftJoin('users', 'users.id', '=', 'contents.created_by')
            ->where('contents.site_id', $siteId)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->where('contents.created_at', '>=', $today->copy()->startOfYear()->toDateTimeString());

        $topAuthorsRows = $topAuthorsQuery
            ->groupBy('contents.created_by', 'users.name', 'users.username')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('contents.created_by')
            ->limit(5)
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

        $attachmentsQuery = DB::table('attachments')->where('site_id', $siteId);
        $totalAttachments = (int) (clone $attachmentsQuery)->count();
        $totalAttachmentBytes = SiteStorageUsage::attachmentBytes($siteId);
        $usedAttachmentsQuery = (clone $attachmentsQuery)->where('usage_count', '>', 0);
        $usedAttachments = (int) (clone $usedAttachmentsQuery)->count();
        $usedAttachmentBytes = (int) (clone $usedAttachmentsQuery)->sum('size');
        $unusedAttachments = max(0, $totalAttachments - $usedAttachments);
        $unusedAttachmentBytes = max(0, $totalAttachmentBytes - $usedAttachmentBytes);
        $themeAssetCount = SiteStorageUsage::themeAssetCount($siteId);
        $themeAssetBytes = SiteStorageUsage::themeAssetBytes($siteId);
        $assetChartTotal = max(0, $usedAttachments + $unusedAttachments + $themeAssetCount);
        $assetSegments = $this->buildAssetSegments([
            'used' => $usedAttachments,
            'unused' => $unusedAttachments,
            'theme' => $themeAssetCount,
        ], [
            'used' => $usedAttachmentBytes,
            'unused' => $unusedAttachmentBytes,
            'theme' => $themeAssetBytes,
        ]);

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
                    'note' => ! ($securitySummary['enabled'] ?? false)
                        ? '安护盾未启用'
                        : ($securitySummary['today_blocked'] > 0 ? '今日已拦下异常请求' : '今日暂未命中异常请求'),
                ],
                [
                    'label' => '总拦截次数',
                    'value' => number_format($securitySummary['total_blocked']),
                    'accent' => 'security-total',
                    'note' => ! ($securitySummary['enabled'] ?? false)
                        ? '平台端已关闭安护盾'
                        : ($securitySummary['total_blocked'] > 0 ? '站点安护盾已持续生效' : '安护盾已启用，等待后续统计'),
                ],
            ],
            'trend' => $trend,
            'top_articles' => $topArticles,
            'top_authors' => $topAuthors,
            'assets' => [
                'total' => $totalAttachments,
                'used' => $usedAttachments,
                'unused' => $unusedAttachments,
                'theme' => $themeAssetCount,
                'used_ratio' => $assetChartTotal > 0 ? (int) round(($usedAttachments / $assetChartTotal) * 100) : 0,
                'unused_ratio' => $assetChartTotal > 0 ? (int) round(($unusedAttachments / $assetChartTotal) * 100) : 0,
                'theme_ratio' => $assetChartTotal > 0 ? (int) round(($themeAssetCount / $assetChartTotal) * 100) : 0,
                'used_size_label' => SiteStorageUsage::formatBytes(SiteStorageUsage::totalBytes($siteId)),
                'storage_limit_label' => $this->siteAttachmentStorageLimitLabel($siteId),
                'chart_total_size_label' => SiteStorageUsage::formatBytes($totalAttachmentBytes + $themeAssetBytes),
                'chart_total' => $assetSegments['total'],
                'segments' => $assetSegments['segments'],
            ],
        ];
    }

    /**
     * @param  array{used:int,unused:int,theme:int}  $counts
     * @param  array{used:int,unused:int,theme:int}  $bytes
     * @return array{total:int,segments:array<int,array<string,mixed>>}
     */
    private function buildAssetSegments(array $counts, array $bytes): array
    {
        $total = max(0, (int) array_sum($counts));
        $circumference = 490.0884539600077;
        $dashOffset = 0.0;

        $definitions = [
            'used' => ['label' => '已引用', 'color_class' => 'is-used'],
            'unused' => ['label' => '未引用', 'color_class' => 'is-unused'],
            'theme' => ['label' => '模板资源', 'color_class' => 'is-theme'],
        ];

        $segments = [];

        foreach (['used', 'unused', 'theme'] as $key) {
            $value = max(0, (int) ($counts[$key] ?? 0));
            $size = max(0, (int) ($bytes[$key] ?? 0));
            $ratio = $total > 0 ? round(($value / $total) * 100) : 0;
            $segmentLength = $total > 0 ? ($circumference * $value / $total) : 0.0;
            $segments[] = [
                'key' => $key,
                'label' => $definitions[$key]['label'],
                'value' => $value,
                'size' => $size,
                'size_label' => SiteStorageUsage::formatBytes($size),
                'ratio' => $ratio,
                'detail' => '占比 '.$ratio.'%',
                'detail_full' => '占比 '.$ratio.'% · '.SiteStorageUsage::formatBytes($size),
                'color_class' => $definitions[$key]['color_class'],
                'dasharray' => sprintf('%.3F %.3F', $segmentLength, max(0, $circumference - $segmentLength)),
                'dashoffset' => sprintf('%.3F', -$dashOffset),
            ];
            $dashOffset += $segmentLength;
        }

        return [
            'total' => $total,
            'segments' => $segments,
        ];
    }

    private function siteAttachmentStorageLimitLabel(int $siteId): string
    {
        $limitMb = max(0, (int) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.storage_limit_mb')
            ->value('setting_value'));

        if ($limitMb <= 0) {
            return '不限';
        }

        return $this->formatAttachmentSize($limitMb * 1024 * 1024);
    }

    private function formatAttachmentSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 1).' '.$units[$unitIndex];
    }

}
