<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class Controller
{
    protected function activeSiteTemplate(int|object $site): ?object
    {
        $siteId = is_object($site) ? (int) $site->id : (int) $site;
        $activeTemplateId = is_object($site) && isset($site->active_site_template_id)
            ? $site->active_site_template_id
            : DB::table('sites')->where('id', $siteId)->value('active_site_template_id');

        if (! $activeTemplateId) {
            return null;
        }

        return DB::table('site_templates')
            ->where('site_id', $siteId)
            ->where('id', (int) $activeTemplateId)
            ->first();
    }

    /**
     * Resolve the currently active site template key for a site.
     */
    protected function siteThemeCode(int|object $site): string
    {
        $template = $this->activeSiteTemplate($site);
        $templateKey = is_object($template) ? trim((string) ($template->template_key ?? '')) : '';

        return $templateKey !== '' ? $templateKey : '';
    }

    /**
     * Determine whether the user belongs to the platform admin system.
     */
    protected function isPlatformAdmin(?int $userId = null): bool
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return false;
        }

        return DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->where('platform_user_roles.user_id', $userId)
            ->exists();
    }

    /**
     * Determine whether the user is a site operator.
     */
    protected function isSiteOperator(?int $userId = null): bool
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return false;
        }

        return ! $this->isPlatformAdmin($userId) && $this->boundSites($userId)->isNotEmpty();
    }

    /**
     * Fetch the site bindings of the current operator.
     */
    protected function boundSites(?int $userId = null): Collection
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return collect();
        }

        return DB::table('sites')
            ->join('site_user_roles', 'site_user_roles.site_id', '=', 'sites.id')
            ->where('site_user_roles.user_id', $userId)
            ->distinct()
            ->orderBy('name')
            ->get(['sites.id', 'sites.name', 'sites.site_key', 'sites.status']);
    }

    /**
     * Determine whether the site switcher should be visible.
     */
    protected function shouldShowSiteSwitcher(?int $userId = null): bool
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return false;
        }

        if ($this->isPlatformAdmin($userId)) {
            return $this->adminSites($userId)->count() > 1;
        }

        return $this->boundSites($userId)->count() > 1;
    }

    /**
     * Resolve the default dashboard route for the current user.
     */
    protected function defaultAdminRoute(?int $userId = null): string
    {
        $userId ??= auth()->id();

        return $this->isPlatformAdmin($userId) ? 'admin.dashboard' : 'admin.site-dashboard';
    }

    /**
     * Resolve the active site from the session or fall back to the first site.
     */
    protected function currentSite(Request $request): object
    {
        $sites = $this->adminSites($request->user()?->id);
        $siteId = $request->session()->get('current_site_id');
        $selectedSite = $sites->firstWhere('id', $siteId) ?: $sites->first();

        if (! $selectedSite) {
            throw new NotFoundHttpException('暂无可用站点。');
        }

        $site = DB::table('sites')->where('id', $selectedSite->id)->first();

        if (! $site) {
            throw new NotFoundHttpException('当前站点不存在。');
        }

        $request->session()->put('current_site_id', $site->id);

        return $site;
    }

    /**
     * Fetch all available sites for the admin site switcher.
     */
    protected function adminSites(?int $userId = null): Collection
    {
        $userId ??= auth()->id();

        if (! $userId) {
            return collect();
        }

        if ($this->isPlatformAdmin($userId)) {
            return DB::table('sites')
                ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get(['id', 'name', 'site_key', 'status']);
        }

        return $this->boundSites($userId);
    }

    /**
     * Determine whether the user is a platform super admin.
     */
    protected function isSuperAdmin(int $userId): bool
    {
        return $userId === $this->superAdminUserId();
    }

    /**
     * Resolve the fixed platform super admin user id.
     */
    protected function superAdminUserId(): int
    {
        return (int) config('cms.super_admin_user_id', 1);
    }

    /**
     * Resolve platform permission codes for the given user.
     *
     * @return array<int, string>
     */
    protected function platformPermissionCodes(int $userId): array
    {
        if ($this->isSuperAdmin($userId)) {
            return DB::table('platform_permissions')
                ->pluck('code')
                ->all();
        }

        return DB::table('platform_user_roles')
            ->join('platform_roles', 'platform_roles.id', '=', 'platform_user_roles.role_id')
            ->join('platform_role_permissions', 'platform_role_permissions.role_id', '=', 'platform_roles.id')
            ->join('platform_permissions', 'platform_permissions.id', '=', 'platform_role_permissions.permission_id')
            ->where('platform_user_roles.user_id', $userId)
            ->distinct()
            ->pluck('platform_permissions.code')
            ->all();
    }

    /**
     * Determine whether the user has a specific platform permission.
     */
    protected function hasPlatformPermission(int $userId, string $permissionCode): bool
    {
        return in_array($permissionCode, $this->platformPermissionCodes($userId), true);
    }

    /**
     * Resolve site permission codes for the given user and site.
     *
     * @return array<int, string>
     */
    protected function sitePermissionCodes(int $userId, int $siteId): array
    {
        if ($this->isPlatformAdmin($userId)) {
            return DB::table('site_permissions')
                ->pluck('code')
                ->all();
        }

        return DB::table('site_user_roles')
            ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
            ->join('site_role_permissions', function ($join) use ($siteId): void {
                $join->on('site_role_permissions.role_id', '=', 'site_roles.id')
                    ->where('site_role_permissions.site_id', '=', $siteId);
            })
            ->join('site_permissions', 'site_permissions.id', '=', 'site_role_permissions.permission_id')
            ->where('site_user_roles.user_id', $userId)
            ->where('site_user_roles.site_id', $siteId)
            ->where(function ($query) use ($siteId): void {
                $query->whereNull('site_roles.site_id')
                    ->orWhere('site_roles.site_id', $siteId);
            })
            ->distinct()
            ->pluck('site_permissions.code')
            ->all();
    }

    protected function siteRolesQuery(int $siteId)
    {
        return DB::table('site_roles')
            ->where(function ($query) use ($siteId): void {
                $query->whereNull('site_id')
                    ->orWhere('site_id', $siteId);
            });
    }

    /**
     * Resolve platform roles query.
     */
    protected function platformRolesQuery()
    {
        return DB::table('platform_roles');
    }

    /**
     * Determine whether the given user belongs to the platform identity system.
     */
    protected function isPlatformIdentity(int $userId): bool
    {
        return DB::table('platform_user_roles')
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Resolve a platform role id by role code.
     */
    protected function platformRoleIdByCode(string $code): ?int
    {
        $roleId = DB::table('platform_roles')
            ->where('code', $code)
            ->value('id');

        return $roleId ? (int) $roleId : null;
    }

    /**
     * Abort the request when the user lacks a platform permission.
     */
    protected function authorizePlatform(Request $request, string $permissionCode): void
    {
        abort_unless(
            in_array($permissionCode, $this->platformPermissionCodes($request->user()->id), true),
            403,
            '当前账号没有平台权限。',
        );
    }

    /**
     * Abort the request when the user lacks a site permission.
     */
    protected function authorizeSite(Request $request, int $siteId, string $permissionCode): void
    {
        abort_unless(
            in_array($permissionCode, $this->sitePermissionCodes($request->user()->id, $siteId), true),
            403,
            '当前账号没有站点权限。',
        );
    }

    /**
     * Resolve content-manageable channel ids for the user within the site.
     *
     * @return array<int, int>
     */
    protected function manageableChannelIds(int $userId, int $siteId): array
    {
        $allChannelIds = DB::table('channels')
            ->where('site_id', $siteId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            $this->isPlatformAdmin($userId)
            || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')
            || $this->canAuditContent($userId, $siteId)
            || in_array('channel.manage', $this->sitePermissionCodes($userId, $siteId), true)
        ) {
            return $allChannelIds;
        }

        $mappedChannelIds = DB::table('site_user_channels')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $mappedChannelIds;
    }

    /**
     * Normalize a content channel payload into unique numeric ids.
     *
     * @param  array<int, mixed>|int|string|null  $value
     * @return array<int, int>
     */
    protected function parseContentChannelIds(array|int|string|null $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value)
            ? $value
            : preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

        return collect($items ?: [])
            ->map(fn ($item): int => (int) trim((string) $item))
            ->filter(fn (int $channelId): bool => $channelId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Convert a content channel selection to a stored CSV string.
     *
     * @param  array<int, mixed>|int|string|null  $value
     */
    protected function contentChannelCsv(array|int|string|null $value): ?string
    {
        $channelIds = $this->parseContentChannelIds($value);

        return $channelIds === [] ? null : implode(',', $channelIds);
    }

    /**
     * Resolve the primary content channel id from a single or multi-selection payload.
     *
     * @param  array<int, mixed>|int|string|null  $value
     */
    protected function primaryContentChannelId(array|int|string|null $value): ?int
    {
        return $this->parseContentChannelIds($value)[0] ?? null;
    }

    /**
     * Resolve a SQL expression that selects the first channel id from the CSV storage field.
     */
    protected function primaryContentChannelExpression(string $column = 'contents.channel_id'): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "CAST(CASE WHEN INSTR(COALESCE({$column}, ''), ',') > 0 THEN SUBSTR(COALESCE({$column}, ''), 1, INSTR(COALESCE({$column}, ''), ',') - 1) ELSE COALESCE({$column}, '') END AS INTEGER)";
        }

        return "CAST(NULLIF(SUBSTRING_INDEX(COALESCE({$column}, ''), ',', 1), '') AS UNSIGNED)";
    }

    /**
     * Apply a CSV-based channel membership filter.
     *
     * @param  object  $query
     * @param  array<int, int>  $channelIds
     */
    protected function applyContentChannelMembership($query, string $column, array $channelIds, bool $includeNull = false): void
    {
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $channelIds))));

        if ($channelIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $driver = DB::getDriverName();

        $query->where(function ($subQuery) use ($column, $channelIds, $includeNull, $driver): void {
            if ($includeNull) {
                $subQuery->whereNull($column);
            }

            foreach ($channelIds as $channelId) {
                if ($driver === 'sqlite') {
                    $subQuery->orWhereRaw("(',' || COALESCE({$column}, '') || ',') LIKE ?", ['%,'.$channelId.',%']);
                    continue;
                }

                $subQuery->orWhereRaw("FIND_IN_SET(?, COALESCE({$column}, '')) > 0", [(string) $channelId]);
            }
        });
    }

    /**
     * Determine whether the content can be filtered by a given channel id.
     *
     * @param  array<int, int>  $channelIds
     */
    protected function contentHasChannel(array|int|string|null $storedChannelIds, int $channelId): bool
    {
        return in_array($channelId, $this->parseContentChannelIds($storedChannelIds), true);
    }

    /**
     * Determine whether the user has the specified site role code.
     */
    protected function hasSiteRoleCode(int $userId, int $siteId, string $roleCode): bool
    {
        return DB::table('site_user_roles')
            ->join('site_roles', 'site_roles.id', '=', 'site_user_roles.role_id')
            ->where('site_user_roles.user_id', $userId)
            ->where('site_user_roles.site_id', $siteId)
            ->where('site_roles.code', $roleCode)
            ->exists();
    }

    /**
     * Determine whether the user can audit content within the current site.
     */
    protected function canAuditContent(int $userId, int $siteId): bool
    {
        return in_array('content.audit', $this->sitePermissionCodes($userId, $siteId), true);
    }

    /**
     * Determine whether the user can view all content within the current site.
     */
    protected function canViewAllSiteContent(int $userId, int $siteId): bool
    {
        return $this->isPlatformAdmin($userId)
            || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')
            || $this->canAuditContent($userId, $siteId);
    }

    /**
     * Determine whether article sharing is enabled for the site.
     */
    protected function siteSharesArticles(int $siteId): bool
    {
        return DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'content.article_share_enabled')
            ->value('setting_value') === '1';
    }

    /**
     * Determine whether attachment sharing is enabled for the site.
     */
    protected function siteSharesAttachments(int $siteId): bool
    {
        return DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.share_enabled')
            ->value('setting_value') === '1';
    }

    /**
     * Determine whether the user can view all attachments within the current site.
     */
    protected function canViewAllAttachments(int $userId, int $siteId): bool
    {
        return $this->isPlatformAdmin($userId)
            || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')
            || $this->siteSharesAttachments($siteId);
    }

    /**
     * Determine whether the user can access the attachment workspace.
     */
    protected function canAccessAttachmentWorkspace(int $userId, int $siteId): bool
    {
        $permissionCodes = $this->sitePermissionCodes($userId, $siteId);

        return $this->isPlatformAdmin($userId)
            || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')
            || in_array('attachment.manage', $permissionCodes, true)
            || in_array('content.manage', $permissionCodes, true);
    }

    /**
     * Determine whether the user can manage site operators within the current site.
     */
    protected function canManageSiteUsers(int $userId, int $siteId): bool
    {
        return $this->isPlatformAdmin($userId)
            || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')
            || in_array('site.user.manage', $this->sitePermissionCodes($userId, $siteId), true);
    }

    /**
     * Abort the request when the user lacks attachment workspace access.
     */
    protected function authorizeAttachmentWorkspace(Request $request, int $siteId): void
    {
        abort_unless(
            $this->canAccessAttachmentWorkspace($request->user()->id, $siteId),
            403,
            '当前账号没有附件权限。',
        );
    }

    /**
     * Determine whether the user can access the attachment library feed for the given mode.
     */
    protected function canAccessAttachmentLibraryFeed(int $userId, int $siteId, string $mode, string $context = 'workspace'): bool
    {
        if ($context === 'avatar') {
            return $this->canManageSiteUsers($userId, $siteId)
                || DB::table('site_user_roles')
                    ->where('site_id', $siteId)
                    ->where('user_id', $userId)
                    ->exists();
        }

        $permissionCodes = $this->sitePermissionCodes($userId, $siteId);

        if ($this->isPlatformAdmin($userId) || $this->hasSiteRoleCode($userId, $siteId, 'site_admin')) {
            return true;
        }

        return match ($context) {
            'content' => in_array('content.manage', $permissionCodes, true),
            'promo' => in_array('promo.manage', $permissionCodes, true),
            'theme' => in_array('theme.edit', $permissionCodes, true),
            'guestbook' => in_array('guestbook.setting', $permissionCodes, true),
            default => $this->canAccessAttachmentWorkspace($userId, $siteId),
        };
    }

    /**
     * Abort the request when the user lacks attachment library feed access.
     */
    protected function authorizeAttachmentLibraryFeed(Request $request, int $siteId, string $mode, string $context = 'workspace'): void
    {
        abort_unless(
            $this->canAccessAttachmentLibraryFeed((int) $request->user()->id, $siteId, $mode, $context),
            403,
            '当前账号没有资源库访问权限。',
        );
    }

    /**
     * Apply attachment library visibility restrictions for the given picker mode.
     *
     * @param  object  $query
     */
    protected function applyAttachmentLibraryVisibilityScope(
        $query,
        int $userId,
        int $siteId,
        string $mode = 'editor',
        string $tableAlias = 'attachments'
    ): void {
        if ($mode === 'avatar' && $this->canManageSiteUsers($userId, $siteId)) {
            return;
        }

        $this->applyAttachmentVisibilityScope($query, $userId, $siteId, $tableAlias);
    }

    /**
     * Apply attachment visibility restrictions for non-privileged site operators.
     *
     * @param object $query
     */
    protected function applyAttachmentVisibilityScope($query, int $userId, int $siteId, string $tableAlias = 'attachments'): void
    {
        if ($this->canViewAllAttachments($userId, $siteId)) {
            return;
        }

        $query->where("{$tableAlias}.uploaded_by", $userId);
    }

    /**
     * Determine whether the user can access a specific attachment id under the current visibility scope.
     */
    protected function canAccessVisibleAttachmentId(int $siteId, int $userId, int $attachmentId, bool $imageOnly = false): bool
    {
        if ($attachmentId < 1) {
            return false;
        }

        $query = DB::table('attachments')
            ->where('site_id', $siteId)
            ->where('id', $attachmentId);

        if ($imageOnly) {
            $query->whereIn(DB::raw('LOWER(extension)'), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }

        $this->applyAttachmentVisibilityScope($query, $userId, $siteId);

        return $query->exists();
    }

    /**
     * Determine whether the user can access a specific attachment URL or path under the current visibility scope.
     *
     * @param  array<int, string>  $candidates
     */
    protected function canAccessVisibleAttachmentUrl(int $siteId, int $userId, array $candidates, bool $imageOnly = false): bool
    {
        $candidates = collect($candidates)
            ->flatMap(static function ($value): array {
                $value = trim((string) $value);

                if ($value === '') {
                    return [];
                }

                $normalized = [$value];
                $valueWithoutQuery = preg_replace('/[?#].*$/', '', $value) ?: $value;
                $normalized[] = trim((string) $valueWithoutQuery);
                $parsedPath = parse_url($value, PHP_URL_PATH);

                if (is_string($parsedPath) && trim($parsedPath) !== '') {
                    $normalized[] = trim($parsedPath);
                }

                return $normalized;
            })
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($candidates === []) {
            return false;
        }

        $query = DB::table('attachments')
            ->where('site_id', $siteId)
            ->where(function ($builder) use ($candidates): void {
                $builder->whereIn('url', $candidates)
                    ->orWhereIn(DB::raw("CONCAT('/', path)"), $candidates);
            });

        if ($imageOnly) {
            $query->whereIn(DB::raw('LOWER(extension)'), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }

        $this->applyAttachmentVisibilityScope($query, $userId, $siteId);

        return $query->exists();
    }

    /**
     * @param  array<int, int>  $attachmentIds
     * @return array<int, int>
     */
    protected function visibleAttachmentIds(int $siteId, int $userId, array $attachmentIds, bool $imageOnly = false): array
    {
        $attachmentIds = array_values(array_unique(array_filter(array_map('intval', $attachmentIds))));

        if ($attachmentIds === []) {
            return [];
        }

        $query = DB::table('attachments')
            ->where('site_id', $siteId)
            ->whereIn('id', $attachmentIds);

        if ($imageOnly) {
            $query->whereIn(DB::raw('LOWER(extension)'), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        }

        $this->applyAttachmentVisibilityScope($query, $userId, $siteId);

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Apply content visibility restrictions for non-privileged site operators.
     *
     * @param  object  $query
     */
    protected function applySiteContentVisibilityScope($query, int $userId, int $siteId, string $tableAlias = 'contents'): void
    {
        if ($this->canViewAllSiteContent($userId, $siteId)) {
            return;
        }

        $manageableChannelIds = $this->manageableChannelIds($userId, $siteId);

        if ($manageableChannelIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($subQuery) use ($manageableChannelIds, $tableAlias): void {
            $subQuery->whereNull("{$tableAlias}.channel_id")
                ->orWhereIn("{$tableAlias}.channel_id", $manageableChannelIds)
                ->orWhereExists(function ($channelQuery) use ($manageableChannelIds, $tableAlias): void {
                    $channelQuery->selectRaw('1')
                        ->from('content_channels')
                        ->whereColumn('content_channels.content_id', "{$tableAlias}.id")
                        ->whereIn('content_channels.channel_id', $manageableChannelIds);
                });
        });

        if (! $this->siteSharesArticles($siteId)) {
            $query->where("{$tableAlias}.created_by", $userId);

            return;
        }

        $query->where(function ($subQuery) use ($tableAlias, $userId): void {
            $subQuery->where("{$tableAlias}.type", 'article')
                ->orWhere(function ($ownerQuery) use ($tableAlias, $userId): void {
                    $ownerQuery->where("{$tableAlias}.type", '!=', 'article')
                        ->where("{$tableAlias}.created_by", $userId);
                });
        });
    }

    protected function sitePermissionsQuery()
    {
        return DB::table('site_permissions');
    }

    /**
     * Fetch published platform notices from the main site.
     */
    protected function platformNoticeItems(int $limit = 6)
    {
        $noticeChannelId = $this->platformNoticeChannelId();

        if (! $noticeChannelId) {
            return collect();
        }

        return DB::table('contents')
            ->where('site_id', $this->platformSiteId())
            ->where('type', 'article')
            ->where('status', 'published')
            ->where('channel_id', $noticeChannelId)
            ->orderByDesc('is_top')
            ->orderByDesc('sort')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'summary',
                'content',
                'published_at',
                'title_color',
                'title_bold',
                'title_italic',
                'is_top',
                'is_recommend',
            ]);
    }

    /**
     * Resolve the official platform site id.
     */
    protected function platformSiteId(): int
    {
        return 1;
    }

    /**
     * Resolve the official platform site key.
     */
    protected function platformSiteKey(): string
    {
        return (string) DB::table('sites')
            ->where('id', $this->platformSiteId())
            ->value('site_key');
    }

    /**
     * Resolve the official platform notice channel id.
     */
    protected function platformNoticeChannelId(): ?int
    {
        $channelId = DB::table('channels')
            ->where('site_id', $this->platformSiteId())
            ->where('slug', 'platform-notices')
            ->value('id');

        return $channelId ? (int) $channelId : null;
    }

    /**
     * Ensure the official platform notice channel exists and return its id.
     */
    protected function ensurePlatformNoticeChannelId(int $userId): int
    {
        $existingId = $this->platformNoticeChannelId();

        if ($existingId !== null) {
            return $existingId;
        }

        return (int) DB::table('channels')->insertGetId([
            'site_id' => $this->platformSiteId(),
            'parent_id' => null,
            'name' => '平台公告',
            'slug' => 'platform-notices',
            'type' => 'article_list',
            'path' => '/platform-notices',
            'depth' => 0,
            'sort' => 0,
            'status' => 1,
            'is_nav' => 0,
            'list_template' => 'list',
            'detail_template' => 'detail',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Write an operation log entry.
     *
     * @param array<string, mixed>|null $payload
     */
    protected function logOperation(
        string $scope,
        string $module,
        string $action,
        ?int $siteId = null,
        ?int $userId = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?array $payload = null,
        ?Request $request = null,
    ): void {
        DB::table('operation_logs')->insert([
            'site_id' => $siteId,
            'user_id' => $userId,
            'scope' => $scope,
            'module' => $module,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->trimOperationLogs($scope, $siteId);
    }

    protected function trimOperationLogs(string $scope, ?int $siteId = null, int $keep = 500): void
    {
        $query = DB::table('operation_logs')
            ->where('scope', $scope)
            ->orderByDesc('id');

        if ($siteId === null) {
            $query->whereNull('site_id');
        } else {
            $query->where('site_id', $siteId);
        }

        $cutoffId = (clone $query)
            ->skip(max($keep - 1, 0))
            ->take(1)
            ->value('id');

        if ($cutoffId === null) {
            return;
        }

        $deleteQuery = DB::table('operation_logs')
            ->where('scope', $scope)
            ->where('id', '<', $cutoffId);

        if ($siteId === null) {
            $deleteQuery->whereNull('site_id');
        } else {
            $deleteQuery->where('site_id', $siteId);
        }

        $deleteQuery->delete();
    }

    protected function decorateOperationLogs(LengthAwarePaginator $logs): LengthAwarePaginator
    {
        $items = collect($logs->items());

        if ($items->isEmpty()) {
            return $logs;
        }

        $namesByType = $items
            ->filter(fn ($log) => ! empty($log->target_type) && ! empty($log->target_id))
            ->groupBy('target_type')
            ->map(function (Collection $group, string $targetType): array {
                $targetIds = $group
                    ->pluck('target_id')
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                return $this->resolveOperationLogTargetNames($targetType, $targetIds);
            });

        $items->transform(function ($log) use ($namesByType) {
            $payload = $this->decodeOperationLogPayload($log->payload ?? null);
            $targetType = is_string($log->target_type ?? null) ? trim((string) $log->target_type) : '';
            $targetId = $log->target_id ?? null;
            $resolvedName = null;

            if ($targetType !== '' && $targetId !== null && $targetId !== '') {
                $resolvedName = $namesByType->get($targetType)[(int) $targetId] ?? null;
            }

            $payloadName = $this->extractOperationLogPayloadName($payload);
            $targetLabel = $this->operationLogTargetTypeLabel(
                $targetType,
                is_string($log->scope ?? null) ? $log->scope : null,
            );
            $targetName = $resolvedName ?: $payloadName;
            $targetDisplay = $targetLabel !== '' ? $targetLabel : '-';

            if ($targetName) {
                $targetDisplay .= ' · ' . $targetName;
            }

            if ($targetId !== null && $targetId !== '') {
                $targetDisplay .= ' #' . $targetId;
            }

            $log->target_label = $targetLabel;
            $log->target_name = $targetName;
            $log->target_display = $targetDisplay;

            return $log;
        });

        $logs->setCollection($items);

        return $logs;
    }

    /**
     * @param array<int, int> $targetIds
     * @return array<int, string>
     */
    protected function resolveOperationLogTargetNames(string $targetType, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return match ($targetType) {
            'site' => DB::table('sites')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'user' => DB::table('users')
                ->whereIn('id', $targetIds)
                ->select('id', 'name', 'username')
                ->get()
                ->mapWithKeys(fn ($user) => [(int) $user->id => trim((string) ($user->name ?: $user->username))])
                ->all(),
            'content' => DB::table('contents')
                ->whereIn('id', $targetIds)
                ->pluck('title', 'id')
                ->map(fn ($title) => trim((string) $title))
                ->all(),
            'channel' => DB::table('channels')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'attachment' => DB::table('attachments')
                ->whereIn('id', $targetIds)
                ->select('id', 'origin_name', 'stored_name')
                ->get()
                ->mapWithKeys(fn ($attachment) => [(int) $attachment->id => trim((string) ($attachment->origin_name ?: $attachment->stored_name))])
                ->all(),
            'theme', 'site_template' => DB::table('site_templates')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'platform_role' => DB::table('platform_roles')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'site_role' => DB::table('site_roles')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'role' => DB::table('site_roles')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'module' => DB::table('modules')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'promo_position' => DB::table('promo_positions')
                ->whereIn('id', $targetIds)
                ->pluck('name', 'id')
                ->map(fn ($name) => trim((string) $name))
                ->all(),
            'promo_item' => DB::table('promo_items')
                ->whereIn('id', $targetIds)
                ->pluck('title', 'id')
                ->map(fn ($title) => trim((string) $title))
                ->all(),
            'guestbook_message' => DB::table('module_guestbook_messages')
                ->whereIn('id', $targetIds)
                ->select('id', 'display_no', 'name')
                ->get()
                ->mapWithKeys(function ($message): array {
                    $parts = array_filter([
                        $message->display_no ? '编号' . $message->display_no : null,
                        trim((string) $message->name) ?: null,
                    ]);

                    return [(int) $message->id => implode(' · ', $parts)];
                })
                ->all(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeOperationLogPayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function extractOperationLogPayloadName(?array $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['title', 'name', 'username', 'site_name', 'channel_name', 'role_name', 'module_name', 'code', 'domain', 'month_key'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function operationLogTargetTypeLabel(?string $targetType, ?string $scope = null): string
    {
        $targetType = trim((string) $targetType);
        $scope = trim((string) $scope);

        if ($targetType === 'user') {
            return $scope === 'platform' ? '管理员' : '操作员';
        }

        return match ($targetType) {
            'site' => '站点',
            'content' => '内容',
            'channel' => '栏目',
            'attachment' => '资源',
            'theme' => '主题',
            'site_template' => '站点模板',
            'site_template_orphan' => '孤立模板',
            'platform_role' => '平台角色',
            'site_role' => '站点角色',
            'role' => '站点角色',
            'module' => '模块',
            'site_module_binding' => '站点模块绑定',
            'site_media' => '站点媒体',
            'system_setting' => '系统设置',
            'cache_action' => '缓存操作',
            'promo_position' => '广告位',
            'promo_item' => '图宣',
            'guestbook_message' => '留言',
            default => trim((string) $targetType),
        };
    }
}
