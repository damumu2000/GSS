<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the site-operator dashboard.
     */
    public function __invoke(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $sitePermissionCodes = $this->sitePermissionCodes($request->user()->id, $currentSite->id);
        $now = now()->timezone('Asia/Shanghai');

        $contentQuery = DB::table('contents')->where('site_id', $currentSite->id);
        $this->applySiteContentVisibilityScope($contentQuery, $request->user()->id, $currentSite->id);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $stats = [
            'channel_count' => DB::table('channels')->where('site_id', $currentSite->id)->count(),
            'content_count' => (clone $contentQuery)->count(),
            'attachment_count' => DB::table('attachments')->where('site_id', $currentSite->id)->count(),
            'draft_count' => (clone $contentQuery)
                ->where('status', 'draft')
                ->count(),
            'pending_count' => (clone $contentQuery)
                ->whereIn('status', ['draft', 'pending'])
                ->count(),
            'review_count' => (clone $contentQuery)
                ->where('status', 'pending')
                ->count(),
        ];

        $recentContents = (clone $contentQuery)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'type', 'status', 'updated_at', 'channel_id']);

        $pendingContents = (clone $contentQuery)
            ->whereIn('status', ['draft', 'pending'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get(['id', 'title', 'type', 'status', 'updated_at']);

        $platformNoticeChannelId = $this->ensurePlatformNoticeChannelId($request->user()->id);
        $platformNotices = $this->platformNoticeItems(5);
        $platformNoticeSiteKey = $this->platformSiteKey();

        $domainCount = DB::table('site_domains')->where('site_id', $currentSite->id)->count();
        $primaryDomain = DB::table('site_domains')
            ->where('site_id', $currentSite->id)
            ->orderByDesc('is_primary')
            ->orderBy('domain')
            ->value('domain');
        $filingNumber = DB::table('site_settings')
            ->where('site_id', $currentSite->id)
            ->where('setting_key', 'site.filing_number')
            ->value('setting_value');
        $boundSiteCount = $this->boundSites($request->user()->id)->count();
        $expiresAt = $currentSite->expires_at ? Carbon::parse($currentSite->expires_at) : null;
        $daysUntilExpiry = $expiresAt
            ? now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)
            : null;
        $roleNames = DB::table('site_user_roles')
            ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
            ->where('site_user_roles.site_id', $currentSite->id)
            ->where('site_user_roles.user_id', $request->user()->id)
            ->orderBy('site_roles.id')
            ->pluck('site_roles.name')
            ->all();

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
            'dashboardGreeting' => sprintf(
                '欢迎你：今天是%s，农历%s。%s，%s。',
                $now->format('Y.n.j'),
                $this->formatLunarDate($now),
                $this->timeGreeting($now),
                $this->randomGreetingSentence()
            ),
            'stats' => $stats,
            'recentContents' => $recentContents,
            'pendingContents' => $pendingContents,
            'platformNotices' => $platformNotices,
            'platformNoticeSiteKey' => $platformNoticeSiteKey,
            'platformNoticeChannelId' => $platformNoticeChannelId,
            'showPlatformNoticeLink' => $platformNoticeSiteKey !== '',
            'sitePermissionCodes' => $sitePermissionCodes,
            'domainCount' => $domainCount,
            'primaryDomain' => $primaryDomain,
            'boundSiteCount' => $boundSiteCount,
            'filingNumber' => $filingNumber,
            'roleNames' => $roleNames,
            'expiresAt' => $expiresAt,
            'daysUntilExpiry' => $daysUntilExpiry,
            'quickLinks' => $quickLinks,
            'canManageContent' => in_array('content.manage', $sitePermissionCodes, true),
            'showSiteSwitcher' => $this->shouldShowSiteSwitcher($request->user()->id),
            'manageableChannelCount' => count($manageableChannelIds),
            'isChannelRestricted' => ! $this->canViewAllSiteContent($request->user()->id, $currentSite->id)
                && $manageableChannelIds !== [],
        ]);
    }

    private function timeGreeting(Carbon $time): string
    {
        $hour = (int) $time->format('G');

        return match (true) {
            $hour >= 5 && $hour < 11 => '早上好',
            $hour >= 11 && $hour < 13 => '中午好',
            $hour >= 13 && $hour < 18 => '下午好',
            default => '晚上好',
        };
    }

    private function formatLunarDate(Carbon $time): string
    {
        $formatter = new \IntlDateFormatter(
            'zh_CN@calendar=chinese',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Asia/Shanghai',
            \IntlDateFormatter::TRADITIONAL,
            'M-d'
        );

        $formatted = $formatter->format($time);

        if (! is_string($formatted) || ! str_contains($formatted, '-')) {
            return '未知';
        }

        [$month, $dayLabel] = explode('-', $formatted, 2);
        $monthNumber = (int) preg_replace('/\D/u', '', $month);

        return $this->chineseMonthName($monthNumber) . $this->normalizeLunarDayLabel($dayLabel);
    }

    private function chineseMonthName(int $month): string
    {
        $months = [
            1 => '正月',
            2 => '二月',
            3 => '三月',
            4 => '四月',
            5 => '五月',
            6 => '六月',
            7 => '七月',
            8 => '八月',
            9 => '九月',
            10 => '十月',
            11 => '冬月',
            12 => '腊月',
        ];

        return $months[$month] ?? ($month . '月');
    }

    private function normalizeLunarDayLabel(string $dayLabel): string
    {
        $dayLabel = trim($dayLabel);

        if (preg_match('/^\d+$/', $dayLabel) !== 1) {
            return $dayLabel;
        }

        $day = (int) $dayLabel;
        $units = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九'];

        return match (true) {
            $day === 10 => '初十',
            $day < 10 => '初' . $units[$day],
            $day < 20 => '十' . $units[$day - 10],
            $day === 20 => '二十',
            $day < 30 => '廿' . $units[$day - 20],
            $day === 30 => '三十',
            default => (string) $day,
        };
    }

    private function randomGreetingSentence(): string
    {
        $sentences = [
            '祝您有个美好的一天',
            '愿今天的节奏刚刚好',
            '愿你今天顺顺利利',
            '希望今天有新的收获',
            '愿你抬头就有好消息',
            '愿今天的忙碌都有回响',
            '愿你此刻心情明亮',
            '希望今天一切推进顺畅',
            '愿你今天所想皆有回应',
            '愿今天的每一步都算数',
            '希望你今天状态满格',
            '愿你今天做事特别顺手',
            '愿今天的努力都有结果',
            '希望今天能遇见小确幸',
            '愿你今天心里有光',
            '愿你今天比昨天更从容',
            '希望今天灵感不断',
            '愿你今天效率在线',
            '愿你今天好事发生',
            '希望今天烦恼少一点',
            '愿你今天保持好心情',
            '愿今天的工作轻快有序',
            '希望今天的安排都很合拍',
            '愿你今天一路通畅',
            '愿你今天轻松完成目标',
            '希望今天处处有惊喜',
            '愿你今天心想事成',
            '愿今天的日程井井有条',
            '希望今天每件事都刚刚好',
            '愿你今天拥有满满元气',
            '愿你今天顺风顺水',
            '希望今天的小目标都达成',
            '愿你今天收获好心情',
            '愿你今天充满笃定感',
            '希望今天解决难题特别快',
            '愿你今天眼里有光心里有底',
            '愿今天每一步都更靠近目标',
            '希望今天一路绿灯',
            '愿你今天忙而不乱',
            '愿你今天被温柔以待',
            '希望今天的坚持都值得',
            '愿你今天拥有好状态',
            '愿今天每个决定都很漂亮',
            '希望今天的思路特别清晰',
            '愿你今天收获更多认可',
            '愿今天的时间对你格外友好',
            '希望今天多一点轻松感',
            '愿你今天保持稳定发挥',
            '愿今天适合推进重要的事',
            '希望今天比预期更顺利',
            '愿你今天做什么都很有手感',
            '愿今天值得你微笑',
            '希望今天遇到的人都很友善',
            '愿你今天把复杂的事都理顺',
            '愿今天有一个好结果在等你',
            '希望今天连小事也很顺心',
            '愿你今天状态沉稳又有力量',
            '愿今天每一份投入都有回报',
            '希望今天能多一点好消息',
            '愿你今天收获踏实感',
            '愿今天的安排都往好的方向走',
            '希望今天心情和天气一样晴朗',
            '愿你今天保持专注也保持轻松',
            '愿今天是充满进展的一天',
            '希望今天特别适合完成计划',
            '愿你今天一切都在掌控中',
            '愿今天每个节点都顺利推进',
            '希望今天拥有充足耐心',
            '愿你今天把重要的事都做好',
            '愿今天所有等待都有回音',
            '希望今天更靠近想要的生活',
            '愿你今天轻装上阵也能赢',
            '愿今天是一个舒服的工作日',
            '希望今天多一些满意时刻',
            '愿你今天充满清晰与果断',
            '愿今天每个小进步都被看见',
            '希望今天安排少一点波折',
            '愿你今天稳稳向前',
            '愿今天遇事都能迎刃而解',
            '希望今天每段时间都用得刚好',
            '愿你今天拥有被支持的感觉',
            '愿今天所有事情都有好着落',
            '希望今天特别适合开新局',
            '愿你今天从容又高效',
            '愿今天的目标都能落地',
            '希望今天把想做的事都做成',
            '愿你今天感到轻松而坚定',
            '愿今天比昨天更多一点幸运',
            '希望今天值得期待',
            '愿你今天灵感和行动并行',
            '愿今天每次沟通都很顺畅',
            '希望今天少一点内耗多一点笃定',
            '愿你今天一整天都顺心',
            '愿今天多一点愉快的反馈',
            '希望今天能遇到让你开心的事',
            '愿你今天始终保持节奏感',
            '愿今天做出的努力都不被辜负',
            '希望今天有好的开始也有好的结束',
            '愿你今天忙得有价值',
            '愿今天的你比想象中更出色',
            '希望今天顺利到想给自己点个赞',
            '愿你今天充满春天一样的生机',
            '愿今天的每一刻都值得',
        ];

        return $sentences[array_rand($sentences)];
    }
}
