<?php

namespace App\Support\Admin;

use App\Support\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminLayoutData
{
    public function __construct(
        protected ModuleManager $moduleManager,
    ) {
    }

    public function build(Request $request, mixed $authUser, mixed $currentSite, mixed $sites, bool $showSiteSwitcher = false): array
    {
        $currentSite = $currentSite ?? null;
        $sites = collect($sites ?? []);
        $showSiteSwitcher = $showSiteSwitcher && $sites->count() > 1 && ! empty($currentSite?->id);
        $displayName = $authUser->real_name ?? $authUser->name ?? $authUser->username ?? '管理员';

        $isSuperAdmin = false;
        $isPlatformAdmin = false;
        $platformPermissionCodes = [];
        $sitePermissionCodes = [];
        $boundSitesCount = 0;
        $headerRoleLabel = '管理员';

        if ($authUser) {
            $isSuperAdmin = (int) $authUser->id === (int) config('cms.super_admin_user_id', 1);
            $isPlatformAdmin = $isSuperAdmin || DB::table('platform_user_roles')
                ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                ->where('platform_user_roles.user_id', $authUser->id)
                ->exists();

            $platformPermissionCodes = $isSuperAdmin
                ? DB::table('platform_permissions')->pluck('code')->all()
                : DB::table('platform_user_roles')
                    ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                    ->join('platform_role_permissions', 'platform_role_permissions.role_id', '=', 'platform_roles.id')
                    ->join('platform_permissions', 'platform_permissions.id', '=', 'platform_role_permissions.permission_id')
                    ->where('platform_user_roles.user_id', $authUser->id)
                    ->distinct()
                    ->pluck('platform_permissions.code')
                    ->all();

            $boundSitesCount = DB::table('site_user_roles')
                ->where('user_id', $authUser->id)
                ->distinct('site_id')
                ->count('site_id');

            if ($isSuperAdmin) {
                $headerRoleLabel = '总管理员';
            } elseif ($isPlatformAdmin) {
                $headerRoleLabel = (string) (DB::table('platform_user_roles')
                    ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
                    ->where('platform_user_roles.user_id', $authUser->id)
                    ->value('platform_roles.name') ?: '平台管理员');
            } elseif (! empty($currentSite?->id)) {
                $siteRoleNames = DB::table('site_user_roles')
                    ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
                    ->where('site_user_roles.user_id', $authUser->id)
                    ->where('site_user_roles.site_id', $currentSite->id)
                    ->orderBy('site_roles.id')
                    ->pluck('site_roles.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($siteRoleNames !== []) {
                    $headerRoleLabel = implode('、', $siteRoleNames);
                } elseif ($boundSitesCount > 0) {
                    $headerRoleLabel = '操作员';
                }
            } elseif ($boundSitesCount > 0) {
                $headerRoleLabel = '操作员';
            }

            $sitePermissionCodes = ($isPlatformAdmin || ! empty($currentSite?->id))
                ? ($isPlatformAdmin
                    ? DB::table('site_permissions')->pluck('code')->all()
                    : DB::table('site_user_roles')
                        ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
                        ->join('site_role_permissions', function ($join) use ($currentSite): void {
                            $join->on('site_role_permissions.role_id', '=', 'site_roles.id')
                                ->where('site_role_permissions.site_id', '=', $currentSite->id ?? 0);
                        })
                        ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
                        ->where('site_user_roles.user_id', $authUser->id)
                        ->where('site_user_roles.site_id', $currentSite->id ?? 0)
                        ->where(function ($query) use ($currentSite): void {
                            $query->whereNull('site_roles.site_id')
                                ->orWhere('site_roles.site_id', $currentSite->id ?? 0);
                        })
                        ->distinct()
                        ->pluck('site_permissions.code')
                        ->all())
                : [];
        }

        $profileRoute = $authUser
            ? ($isPlatformAdmin
                ? route('admin.platform.users.edit', $authUser->id)
                : route('admin.site-users.edit', $authUser->id))
            : '#';

        $isPlatformArea = $request->is('admin/platform*') || $request->is('admin/dashboard') || $request->is('admin/logs');
        $activeAdminArea = $isPlatformArea ? 'platform' : 'site';

        $articleRejectedCount = ($currentSite && in_array('content.manage', $sitePermissionCodes, true))
            ? (int) DB::table('contents')->where('site_id', $currentSite->id)->where('type', 'article')->where('status', 'rejected')->whereNull('deleted_at')->count()
            : 0;
        $articlePendingCount = ($currentSite && in_array('content.audit', $sitePermissionCodes, true))
            ? (int) DB::table('contents')->where('site_id', $currentSite->id)->where('type', 'article')->where('status', 'pending')->whereNull('deleted_at')->count()
            : 0;
        $articleReviewEnabled = $currentSite
            ? (DB::table('site_settings')->where('site_id', $currentSite->id)->where('setting_key', 'content.article_requires_review')->value('setting_value') === '1')
            : false;
        $guestbookMenuName = $currentSite
            ? (string) (DB::table('site_settings')->where('site_id', $currentSite->id)->where('setting_key', 'module.guestbook.name')->value('setting_value') ?: '')
            : '';
        $recycleCount = ($currentSite && in_array('content.manage', $sitePermissionCodes, true))
            ? (int) DB::table('contents')->where('site_id', $currentSite->id)->whereNotNull('deleted_at')->count()
            : 0;

        $boundSiteModules = $currentSite
            ? $this->moduleManager->boundSiteModules((int) $currentSite->id)
                ->filter(function (array $module) use ($sitePermissionCodes, $isPlatformAdmin): bool {
                    if ($isPlatformAdmin) {
                        return true;
                    }

                    $entryPermission = $module['entry_permission'] ?? null;

                    return ! is_string($entryPermission) || $entryPermission === '' || in_array($entryPermission, $sitePermissionCodes, true);
                })
                ->values()
            : collect();

        $guestbookPendingCount = ($currentSite && $boundSiteModules->contains(fn (array $module): bool => ($module['code'] ?? null) === 'guestbook'))
            ? (int) DB::table('module_guestbook_messages')->where('site_id', $currentSite->id)->where('status', 'pending')->count()
            : 0;
        $payrollEmployeePendingCount = ($currentSite && $boundSiteModules->contains(fn (array $module): bool => ($module['code'] ?? null) === 'payroll'))
            ? (int) DB::table('module_payroll_employees')->where('site_id', $currentSite->id)->where('status', 'pending')->count()
            : 0;

        $siteMenuGroups = [
            [
                'title' => '内容管理',
                'items' => array_values(array_filter([
                    ['label' => '文章管理', 'route' => 'admin.articles.index', 'active' => $request->routeIs('admin.articles.*'), 'icon' => 'article', 'badge' => $articleRejectedCount, 'badge_class' => '', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
                    ['label' => '文章审核', 'route' => 'admin.article-reviews.index', 'active' => $request->routeIs('admin.article-reviews.*'), 'icon' => 'tag', 'badge' => $articlePendingCount, 'badge_class' => '', 'show' => in_array('content.audit', $sitePermissionCodes, true) && $articleReviewEnabled],
                    ['label' => '单页面管理', 'route' => 'admin.pages.index', 'active' => $request->routeIs('admin.pages.*'), 'icon' => 'page', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
                    ['label' => '资源库管理', 'route' => 'admin.attachments.index', 'active' => $request->routeIs('admin.attachments.*'), 'icon' => 'attachment', 'show' => in_array('attachment.manage', $sitePermissionCodes, true) || in_array('content.manage', $sitePermissionCodes, true)],
                    ['label' => '回收站', 'route' => 'admin.recycle-bin.index', 'active' => $request->routeIs('admin.recycle-bin.*'), 'icon' => 'recycle', 'badge' => $recycleCount, 'badge_class' => 'is-title', 'show' => in_array('content.manage', $sitePermissionCodes, true)],
                ], fn ($item) => $item['show'])),
            ],
            [
                'title' => '功能模块',
                'items' => array_values(array_filter(
                    $boundSiteModules->map(function (array $module) use ($guestbookMenuName, $guestbookPendingCount, $payrollEmployeePendingCount, $request): array {
                        $entryRoute = is_string($module['site_entry_route'] ?? null) && ($module['site_entry_route'] ?? '') !== ''
                            ? (string) $module['site_entry_route']
                            : 'admin.site-modules.show';
                        $routeParams = $entryRoute === 'admin.site-modules.show'
                            ? ['module' => $module['code']]
                            : [];
                        $activePattern = $entryRoute === 'admin.site-modules.show'
                            ? 'admin.site-modules.show'
                            : preg_replace('/\.[^.]+$/', '.*', $entryRoute);
                        $moduleLevelPattern = $activePattern;

                        if ($entryRoute !== 'admin.site-modules.show' && preg_match('/^([^.]+\.[^.]+)\./', $entryRoute, $matches)) {
                            $moduleLevelPattern = $matches[1].'.*';
                        }

                        $isActive = $request->routeIs($activePattern)
                            || $request->routeIs($moduleLevelPattern)
                            || ($request->routeIs('admin.site-modules.show') && $request->route('module') === $module['code']);

                        return [
                            'label' => $module['code'] === 'guestbook' && $guestbookMenuName !== '' ? $guestbookMenuName : $module['name'],
                            'route' => $entryRoute,
                            'route_params' => $routeParams,
                            'active' => $isActive,
                            'icon' => match ($module['code'] ?? null) {
                                'guestbook' => 'guestbook',
                                'payroll' => 'payroll',
                                default => 'module',
                            },
                            'prefix_badge' => ! empty($module['binding_is_trial']) ? '试用' : null,
                            'badge' => match ($module['code'] ?? null) {
                                'guestbook' => $guestbookPendingCount,
                                'payroll' => $payrollEmployeePendingCount,
                                default => 0,
                            },
                            'badge_class' => in_array(($module['code'] ?? null), ['guestbook', 'payroll'], true) ? 'is-title' : '',
                            'show' => true,
                        ];
                    })->all(),
                    fn ($item) => $item['show']
                )),
            ],
            [
                'title' => '站点配置',
                'items' => array_values(array_filter([
                    ['label' => '站点工作台', 'route' => 'admin.site-dashboard', 'active' => $request->routeIs('admin.site-dashboard'), 'icon' => 'dashboard', 'show' => $currentSite !== null],
                    ['label' => '站点设置', 'route' => 'admin.settings.index', 'active' => $request->routeIs('admin.settings.*'), 'icon' => 'setting', 'show' => in_array('setting.manage', $sitePermissionCodes, true)],
                    ['label' => '栏目管理', 'route' => 'admin.channels.index', 'active' => $request->routeIs('admin.channels.*'), 'icon' => 'channel', 'show' => in_array('channel.manage', $sitePermissionCodes, true)],
                    ['label' => '图宣管理', 'route' => 'admin.promos.index', 'active' => $request->routeIs('admin.promos.*'), 'icon' => 'promo', 'show' => in_array('promo.manage', $sitePermissionCodes, true)],
                    ['label' => '模板管理', 'route' => 'admin.themes.index', 'active' => $request->routeIs('admin.themes.*'), 'icon' => 'theme', 'show' => in_array('theme.use', $sitePermissionCodes, true) || in_array('theme.edit', $sitePermissionCodes, true)],
                    ['label' => '安护盾', 'route' => 'admin.security.index', 'active' => $request->routeIs('admin.security.*'), 'icon' => 'shield', 'show' => in_array('security.view', $sitePermissionCodes, true)],
                    ['label' => '操作员管理', 'route' => 'admin.site-users.index', 'active' => $request->routeIs('admin.site-users.*'), 'icon' => 'user', 'show' => in_array('site.user.manage', $sitePermissionCodes, true)],
                    ['label' => '操作角色管理', 'route' => 'admin.site-roles.index', 'active' => $request->routeIs('admin.site-roles.*'), 'icon' => 'setting', 'show' => in_array('site.user.manage', $sitePermissionCodes, true)],
                    ['label' => '站点日志', 'route' => 'admin.site-logs.index', 'active' => $request->routeIs('admin.site-logs.*'), 'icon' => 'log', 'show' => in_array('log.view', $sitePermissionCodes, true)],
                ], fn ($item) => $item['show'])),
            ],
        ];

        $platformMenuGroups = [
            [
                'title' => '业务管理',
                'items' => $isPlatformAdmin ? array_values(array_filter([
                    ['label' => '站点管理', 'route' => 'admin.platform.sites.index', 'active' => $request->routeIs('admin.platform.sites.*'), 'icon' => 'site', 'show' => in_array('site.manage', $platformPermissionCodes, true)],
                    ['label' => '模块管理', 'route' => 'admin.platform.modules.index', 'active' => $request->routeIs('admin.platform.modules.*'), 'icon' => 'module', 'show' => in_array('module.manage', $platformPermissionCodes, true)],
                ], fn ($item) => $item['show'])) : [],
            ],
            [
                'title' => '平台配置',
                'items' => $isPlatformAdmin ? array_values(array_filter([
                    ['label' => '平台工作台', 'route' => 'admin.dashboard', 'active' => $request->routeIs('admin.dashboard'), 'icon' => 'dashboard', 'show' => true],
                    ['label' => '平台管理员', 'route' => 'admin.platform.users.index', 'active' => $request->routeIs('admin.platform.users.*'), 'icon' => 'user', 'show' => in_array('platform.user.manage', $platformPermissionCodes, true)],
                    ['label' => '平台角色管理', 'route' => 'admin.platform.roles.index', 'active' => $request->routeIs('admin.platform.roles.*'), 'icon' => 'setting', 'show' => in_array('platform.role.manage', $platformPermissionCodes, true)],
                    ['label' => '数据库管理', 'route' => 'admin.platform.database.index', 'active' => $request->routeIs('admin.platform.database.*'), 'icon' => 'database', 'show' => in_array('database.manage', $platformPermissionCodes, true)],
                    ['label' => '系统设置', 'route' => 'admin.platform.settings.index', 'active' => $request->routeIs('admin.platform.settings.*'), 'icon' => 'setting', 'show' => in_array('system.setting.manage', $platformPermissionCodes, true)],
                    ['label' => '系统检查', 'route' => 'admin.platform.system-checks.index', 'active' => $request->routeIs('admin.platform.system-checks.*'), 'icon' => 'database', 'show' => in_array('system.check.view', $platformPermissionCodes, true)],
                    ['label' => '操作日志', 'route' => 'admin.logs.index', 'active' => $request->routeIs('admin.logs.*'), 'icon' => 'log', 'show' => in_array('platform.log.view', $platformPermissionCodes, true)],
                ], fn ($item) => $item['show'])) : [],
            ],
        ];

        if ($activeAdminArea === 'platform' && $isPlatformAdmin && $currentSite) {
            $platformMenuGroups[] = [
                'title' => '站点视角',
                'items' => [[
                    'label' => '进入站点工作台',
                    'route' => 'admin.site-dashboard',
                    'active' => false,
                    'icon' => 'dashboard',
                    'show' => true,
                ]],
            ];
        }

        if ($activeAdminArea === 'site' && $isPlatformAdmin) {
            $siteMenuGroups[] = [
                'title' => '平台视角',
                'items' => [[
                    'label' => '进入平台工作台',
                    'route' => 'admin.dashboard',
                    'active' => false,
                    'icon' => 'dashboard',
                    'show' => true,
                ]],
            ];
        }

        return [
            'currentSite' => $currentSite,
            'sites' => $sites,
            'showSiteSwitcher' => $showSiteSwitcher,
            'displayName' => $displayName,
            'headerRoleLabel' => $headerRoleLabel,
            'profileRoute' => $profileRoute,
            'activeAdminArea' => $activeAdminArea,
            'menuGroups' => $activeAdminArea === 'platform' ? $platformMenuGroups : $siteMenuGroups,
        ];
    }
}
