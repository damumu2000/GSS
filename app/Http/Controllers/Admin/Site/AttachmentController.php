<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\AttachmentUsageTracker;
use App\Support\Site as SitePath;
use App\Support\SystemSettings;
use App\Support\ThemeTemplateLocator;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttachmentController extends Controller
{
    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    /**
     * Display a listing of attachments for the current site.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);
        $filters = $this->normalizeAttachmentBrowserFilters($request);
        $imageExtensions = $this->normalizedImageExtensions();

        $attachmentQuery = DB::table('attachments')
            ->leftJoin('users', 'users.id', '=', 'attachments.uploaded_by')
            ->where('attachments.site_id', $currentSite->id);

        $this->applyAttachmentBrowserFilters(
            $attachmentQuery,
            $filters,
            $imageExtensions,
            'attachments',
            'attachments.usage_count',
            'attachments.last_used_at',
            'attachments.created_at',
        );
        $this->applyAttachmentBrowserSorting($attachmentQuery, $filters, 'attachments.created_at', 'attachments.id');

        $this->applyAttachmentVisibilityScope($attachmentQuery, $request->user()->id, $currentSite->id);

        $attachmentTotalSize = (clone $attachmentQuery)->sum('attachments.size');

        $attachments = $attachmentQuery
            ->paginate(9, [
                'attachments.id',
                'attachments.origin_name',
                'attachments.extension',
                'attachments.size',
                'attachments.width',
                'attachments.height',
                'attachments.url',
                'attachments.created_at',
                'attachments.path',
                'attachments.uploaded_by',
                DB::raw("COALESCE(NULLIF(users.name, ''), NULLIF(users.username, ''), '未记录') AS uploaded_by_name"),
                'attachments.usage_count',
            ])
            ->withQueryString();

        return view('admin.site.attachments.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'attachments' => $attachments,
            'attachmentTotalSizeLabel' => $this->formatAttachmentSize((int) $attachmentTotalSize),
            'attachmentStorageLimitLabel' => $this->siteAttachmentStorageLimitLabel((int) $currentSite->id),
            'keyword' => $filters['keyword'],
            'selectedFilter' => $filters['filter'],
            'selectedUsage' => $filters['usage'],
            'selectedSort' => $filters['sort'],
            'unusedDays' => $filters['unusedDays'],
        ]);
    }

    /**
     * Store a newly uploaded attachment.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $validated = $request->validate([
            'file' => $this->attachmentUploadRules(),
        ], [], [
            'file' => '附件文件',
        ]);

        $this->validateImageDimensionsIfNeeded($validated['file'], '附件文件');
        $preparedFile = $this->prepareStoredAttachmentFile($validated['file'], false);
        $this->validatePreparedAttachmentSize($preparedFile, '附件文件', false);
        $this->validateSiteAttachmentStorageLimit($currentSite->id, (int) $preparedFile['size']);

        $attachmentId = $this->storeAttachment($currentSite, $validated['file'], $request->user()->id, $preparedFile);

        $this->logOperation(
            'site',
            'attachment',
            'create',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            $attachmentId,
            ['name' => $validated['file']->getClientOriginalName()],
            $request,
        );

        return redirect()
            ->route('admin.attachments.index')
            ->with('status', '附件已上传。');
    }

    /**
     * Handle TinyMCE image uploads.
     */
    public function imageUpload(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $validated = $request->validate([
            'file' => $this->attachmentUploadRules(true),
        ], [], [
            'file' => '图片文件',
        ]);

        $this->validateImageDimensionsIfNeeded($validated['file'], '图片文件');
        $preparedFile = $this->prepareStoredAttachmentFile($validated['file'], true);
        $this->validatePreparedAttachmentSize($preparedFile, '图片文件', true);
        $this->validateSiteAttachmentStorageLimit($currentSite->id, (int) $preparedFile['size']);

        $attachmentId = $this->storeAttachment($currentSite, $validated['file'], $request->user()->id, $preparedFile);
        $attachment = $this->findAttachmentForSite($currentSite->id, $attachmentId);

        abort_unless($attachment, 404);

        $this->logOperation(
            'site',
            'attachment',
            'upload_image',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            $attachmentId,
            ['name' => $validated['file']->getClientOriginalName()],
            $request,
        );

        return response()->json([
            'location' => $attachment->url,
        ]);
    }

    /**
     * Fetch attachments for the shared library popup.
     */
    public function libraryFeed(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $mode = trim((string) $request->query('mode', 'editor'));
        $context = trim((string) $request->query('context', 'workspace'));
        $keyword = trim((string) $request->query('keyword', ''));
        $filter = trim((string) $request->query('filter', 'all'));
        $usage = trim((string) $request->query('usage', 'all'));
        $sort = trim((string) $request->query('sort', 'latest'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(24, (int) $request->query('per_page', 9)));
        $filters = $this->normalizeAttachmentBrowserFilters(new Request([
            'keyword' => $keyword,
            'filter' => $filter,
            'usage' => $usage,
            'sort' => $sort,
        ]));
        $mode = in_array($mode, ['editor', 'picker', 'avatar', 'cover'], true) ? $mode : 'editor';
        $context = in_array($context, ['workspace', 'content', 'promo', 'theme', 'guestbook', 'avatar'], true) ? $context : 'workspace';

        $this->authorizeAttachmentLibraryFeed($request, (int) $currentSite->id, $mode, $context);

        $imageOnly = $request->boolean('image_only') || in_array($mode, ['avatar', 'cover'], true);
        $imageExtensions = $this->normalizedImageExtensions();
        $aggregatedQuery = DB::table('attachments')
            ->leftJoin('attachment_relations', 'attachment_relations.attachment_id', '=', 'attachments.id')
            ->leftJoin('users', 'users.id', '=', 'attachments.uploaded_by')
            ->where('attachments.site_id', (int) $currentSite->id);

        $this->applyAttachmentBrowserFilters(
            $aggregatedQuery,
            $filters,
            $imageExtensions,
            'attachments',
            null,
            null,
            'attachments.created_at',
            $imageOnly,
        );

        $this->applyAttachmentLibraryVisibilityScope($aggregatedQuery, (int) $request->user()->id, (int) $currentSite->id, $mode, 'attachments');

        $aggregatedQuery = $aggregatedQuery
            ->groupBy(
                'attachments.id',
                'attachments.origin_name',
                'attachments.url',
                'attachments.path',
                'attachments.extension',
                'attachments.width',
                'attachments.height',
                'attachments.created_at',
                'users.name',
                'users.username'
            )
            ->select([
                'attachments.id',
                'attachments.origin_name',
                'attachments.url',
                'attachments.path',
                'attachments.extension',
                'attachments.width',
                'attachments.height',
                'attachments.created_at',
                DB::raw('COUNT(attachment_relations.id) as usage_count'),
                DB::raw("COALESCE(NULLIF(users.name, ''), NULLIF(users.username, ''), '未记录') AS uploaded_by_name"),
            ]);

        if ($filters['usage'] === 'used') {
            $aggregatedQuery->having('usage_count', '>', 0);
        } elseif ($filters['usage'] === 'unused') {
            $aggregatedQuery->having('usage_count', '=', 0);
        }

        $this->applyAttachmentBrowserSorting($aggregatedQuery, $filters, 'attachments.created_at', 'attachments.id');

        $total = DB::query()
            ->fromSub(clone $aggregatedQuery, 'attachment_feed')
            ->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $attachments = (clone $aggregatedQuery)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($attachment) => $this->serializeAttachmentLibraryItem($attachment))
            ->values()
            ->all();

        return response()->json([
            'attachments' => $attachments,
            'workspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * Handle resource library uploads.
     */
    public function libraryUpload(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $validated = $request->validate([
            'file' => $this->attachmentUploadRules(),
        ], [], [
            'file' => '资源文件',
        ]);

        $this->validateImageDimensionsIfNeeded($validated['file'], '资源文件');
        $preparedFile = $this->prepareStoredAttachmentFile($validated['file'], false);
        $this->validatePreparedAttachmentSize($preparedFile, '资源文件', false);
        $this->validateSiteAttachmentStorageLimit($currentSite->id, (int) $preparedFile['size']);

        $attachmentId = $this->storeAttachment($currentSite, $validated['file'], $request->user()->id, $preparedFile);
        $attachment = $this->findAttachmentForSite($currentSite->id, $attachmentId);

        abort_unless($attachment, 404);

        $this->logOperation(
            'site',
            'attachment',
            'upload_library',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            $attachmentId,
            ['name' => $validated['file']->getClientOriginalName()],
            $request,
        );

        return response()->json([
            'attachment' => [
                ...$this->serializeAttachmentLibraryItem((object) [
                    'id' => $attachment->id,
                    'origin_name' => $attachment->origin_name,
                    'url' => $attachment->url,
                    'path' => $attachment->path,
                    'extension' => $attachment->extension,
                    'width' => $attachment->width,
                    'height' => $attachment->height,
                    'created_at' => $attachment->created_at,
                    'usage_count' => 0,
                    'uploaded_by_name' => trim((string) ($request->user()->name ?? '')) ?: trim((string) ($request->user()->username ?? '')) ?: '未记录',
                ]),
            ],
        ]);
    }

    /**
     * Replace an existing attachment file while preserving its id and path.
     */
    public function replace(Request $request, string $attachmentId): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $attachment = $this->findAttachmentForSite($currentSite->id, $attachmentId);
        abort_unless($attachment, 404);

        $originalExtension = strtolower((string) ($attachment->extension ?? ''));
        abort_if($originalExtension === '', 404);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.$originalExtension,
            ],
        ], [
            'file.mimes' => '替换文件必须与原附件保持相同后缀名。',
        ], [
            'file' => '替换文件',
        ]);

        $uploadedExtension = strtolower((string) ($validated['file']->getClientOriginalExtension() ?: $validated['file']->extension() ?: ''));

        if ($uploadedExtension !== $originalExtension) {
            throw ValidationException::withMessages([
                'file' => '替换文件必须与原附件保持相同后缀名。',
            ]);
        }

        $this->validateImageDimensionsIfNeeded($validated['file'], '替换文件');
        $preparedFile = $this->prepareStoredAttachmentFile($validated['file'], false, $originalExtension);

        if (strtolower((string) ($preparedFile['extension'] ?? '')) !== $originalExtension) {
            throw ValidationException::withMessages([
                'file' => '替换文件必须与原附件保持相同后缀名。',
            ]);
        }

        $this->validatePreparedAttachmentSize($preparedFile, '替换文件', false);
        $this->validateSiteAttachmentReplacementStorageLimit(
            (int) $currentSite->id,
            (int) $preparedFile['size'],
            (int) ($attachment->size ?? 0),
        );

        $this->overwriteAttachmentFile((string) $attachment->path, $validated['file'], $preparedFile);

        DB::table('attachments')
            ->where('id', $attachment->id)
            ->update([
                'origin_name' => $validated['file']->getClientOriginalName(),
                'mime_type' => (string) $preparedFile['mime_type'],
                'extension' => $originalExtension,
                'size' => (int) $preparedFile['size'],
                'width' => $preparedFile['width'],
                'height' => $preparedFile['height'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $uploadedByName = trim((string) DB::table('users')
            ->where('id', (int) ($attachment->uploaded_by ?? 0))
            ->selectRaw("COALESCE(NULLIF(name, ''), NULLIF(username, ''), '未记录') as display_name")
            ->value('display_name'));

        $this->logOperation(
            'site',
            'attachment',
            'replace',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            $attachment->id,
            [
                'old_name' => (string) ($attachment->origin_name ?? ''),
                'new_name' => $validated['file']->getClientOriginalName(),
                'path' => (string) ($attachment->path ?? ''),
            ],
            $request,
        );

        return response()->json([
            'attachment' => [
                ...$this->serializeAttachmentLibraryItem((object) [
                    'id' => $attachment->id,
                    'origin_name' => $validated['file']->getClientOriginalName(),
                    'url' => $attachment->url,
                    'path' => $attachment->path,
                    'extension' => $originalExtension,
                    'width' => $preparedFile['width'],
                    'height' => $preparedFile['height'],
                    'created_at' => now(),
                    'usage_count' => (int) ($attachment->usage_count ?? 0),
                    'uploaded_by_name' => $uploadedByName !== '' ? $uploadedByName : '未记录',
                ]),
            ],
            'message' => '附件已替换，原路径保持不变。',
        ]);
    }

    /**
     * Fetch attachment usage details within accessible content.
     */
    public function usages(Request $request, string $attachmentId): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $attachment = $this->findAttachmentForSite($currentSite->id, $attachmentId);
        abort_unless($attachment, 404);
        abort_if(
            ! $this->canViewAllAttachments($request->user()->id, $currentSite->id)
                && (int) ($attachment->uploaded_by ?? 0) !== (int) $request->user()->id,
            404,
        );

        $contentQuery = DB::table('attachment_relations')
            ->join('contents', function ($join): void {
                $join->on('contents.id', '=', 'attachment_relations.relation_id')
                    ->where('attachment_relations.relation_type', '=', 'content');
            })
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('attachment_relations.attachment_id', $attachmentId)
            ->where('contents.site_id', $currentSite->id)
            ->select([
                'attachment_relations.usage_slot',
                'contents.id',
                'contents.title',
                'contents.type',
                'contents.status',
                'contents.deleted_at',
                'contents.updated_at',
                'channels.name as channel_name',
            ])
            ->orderByRaw("case when contents.status = 'published' then 0 else 1 end")
            ->orderByDesc('contents.updated_at');

        $usageSlotLabels = [
            'cover_image' => '封面图',
            'body_image' => '正文图片',
            'body_link' => '正文链接',
            'avatar' => '头像',
            'promo_image' => '图宣图片',
            'template_image' => '模板图片',
            'template_link' => '模板链接',
            'template_asset' => '模板资源',
        ];
        $usageSlotOrder = [
            'cover_image' => 0,
            'body_image' => 1,
            'body_link' => 2,
            'avatar' => 3,
            'promo_image' => 4,
            'template_image' => 5,
            'template_link' => 6,
            'template_asset' => 7,
        ];

        $contentItems = $contentQuery->get()
            ->groupBy('id')
            ->map(function ($group) use ($usageSlotLabels, $usageSlotOrder) {
                $item = $group->first();

                $relationLabels = $group
                    ->pluck('usage_slot')
                    ->map(fn ($slot) => (string) $slot)
                    ->unique()
                    ->sortBy(fn ($slot) => $usageSlotOrder[$slot] ?? 99)
                    ->map(fn ($slot) => $usageSlotLabels[$slot] ?? '资源引用')
                    ->values()
                    ->all();

                return [
                    'id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'type_label' => $item->type === 'page' ? '单页面' : '文章',
                    'channel_name' => (string) ($item->channel_name ?: '未归类'),
                    'status_label' => $item->deleted_at !== null
                        ? '回收站'
                        : match ((string) $item->status) {
                            'published' => '已发布',
                            'pending' => '待审核',
                            'rejected' => '已驳回',
                            default => '草稿',
                        },
                    'relation_labels' => $relationLabels,
                    'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('m-d H:i') : '--',
                    'edit_url' => $item->deleted_at !== null
                        ? null
                        : ($item->type === 'page'
                            ? route('admin.pages.edit', $item->id)
                            : route('admin.articles.edit', $item->id)),
                    'view_url' => $item->deleted_at !== null
                        ? null
                        : ($item->type === 'page'
                            ? route('site.page', $item->id)
                            : route('site.article', $item->id)),
                    'updated_sort' => $item->updated_at ? Carbon::parse($item->updated_at)->timestamp : 0,
                ];
            })
            ->values();

        $userItems = DB::table('attachment_relations')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'attachment_relations.relation_id')
                    ->where('attachment_relations.relation_type', '=', 'user');
            })
            ->join('site_user_roles', function ($join) use ($currentSite): void {
                $join->on('site_user_roles.user_id', '=', 'users.id')
                    ->where('site_user_roles.site_id', '=', $currentSite->id);
            })
            ->where('attachment_relations.attachment_id', $attachmentId)
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.status',
                'users.updated_at',
                'attachment_relations.usage_slot',
            ])
            ->orderByDesc('users.updated_at')
            ->get()
            ->groupBy('id')
            ->map(function ($group) use ($usageSlotLabels, $usageSlotOrder) {
                $item = $group->first();
                $relationLabels = $group
                    ->pluck('usage_slot')
                    ->map(fn ($slot) => (string) $slot)
                    ->unique()
                    ->sortBy(fn ($slot) => $usageSlotOrder[$slot] ?? 99)
                    ->map(fn ($slot) => $usageSlotLabels[$slot] ?? '资源引用')
                    ->values()
                    ->all();

                $displayName = trim((string) ($item->name ?? '')) ?: trim((string) ($item->username ?? '')) ?: '未命名操作员';

                return [
                    'id' => 'user-'.$item->id,
                    'title' => $displayName,
                    'type_label' => '操作员',
                    'channel_name' => '后台账号',
                    'status_label' => (int) ($item->status ?? 0) === 1 ? '启用中' : '已停用',
                    'relation_labels' => $relationLabels,
                    'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('m-d H:i') : '--',
                    'edit_url' => route('admin.site-users.edit', $item->id),
                    'view_url' => null,
                    'updated_sort' => $item->updated_at ? Carbon::parse($item->updated_at)->timestamp : 0,
                ];
            })
            ->values();

        $items = $contentItems
            ->concat($userItems)
            ->concat(
                DB::table('attachment_relations')
                    ->join('promo_items', function ($join): void {
                        $join->on('promo_items.id', '=', 'attachment_relations.relation_id')
                            ->where('attachment_relations.relation_type', '=', 'promo_item');
                    })
                    ->join('promo_positions', 'promo_positions.id', '=', 'promo_items.position_id')
                    ->where('attachment_relations.attachment_id', $attachmentId)
                    ->where('promo_items.site_id', $currentSite->id)
                    ->select([
                        'promo_items.id',
                        'promo_items.title',
                        'promo_items.status',
                        'promo_items.updated_at',
                        'promo_positions.id as position_id',
                        'promo_positions.name as position_name',
                        'promo_positions.code as position_code',
                        'attachment_relations.usage_slot',
                    ])
                    ->orderByDesc('promo_items.updated_at')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($group) use ($usageSlotLabels, $usageSlotOrder) {
                        $item = $group->first();
                        $relationLabels = $group
                            ->pluck('usage_slot')
                            ->map(fn ($slot) => (string) $slot)
                            ->unique()
                            ->sortBy(fn ($slot) => $usageSlotOrder[$slot] ?? 99)
                            ->map(fn ($slot) => $usageSlotLabels[$slot] ?? '资源引用')
                            ->reject(fn ($label) => $label === '模板资源')
                            ->values()
                            ->all();

                        return [
                            'id' => 'promo-'.$item->id,
                            'title' => trim((string) ($item->title ?? '')) !== '' ? (string) $item->title : ('图宣内容 #'.$item->id),
                            'type_label' => '图宣内容',
                            'channel_name' => sprintf('图宣位：%s', (string) $item->position_name),
                            'status_label' => (int) ($item->status ?? 0) === 1 ? '启用中' : '已停用',
                            'relation_labels' => $relationLabels,
                            'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('m-d H:i') : '--',
                            'edit_url' => route('admin.promos.items.index', $item->position_id).'#promo-item-'.$item->id,
                            'view_url' => null,
                            'updated_sort' => $item->updated_at ? Carbon::parse($item->updated_at)->timestamp : 0,
                        ];
                    })
                    ->values()
            )
            ->concat(
                DB::table('attachment_relations')
                    ->join('site_theme_template_meta', function ($join): void {
                        $join->on('site_theme_template_meta.id', '=', 'attachment_relations.relation_id')
                            ->where('attachment_relations.relation_type', '=', 'theme_template');
                    })
                    ->where('attachment_relations.attachment_id', $attachmentId)
                    ->where('site_theme_template_meta.site_id', $currentSite->id)
                    ->select([
                        'site_theme_template_meta.id',
                        'site_theme_template_meta.title',
                        'site_theme_template_meta.theme_code',
                        'site_theme_template_meta.template_name',
                        'site_theme_template_meta.updated_at',
                        'attachment_relations.usage_slot',
                    ])
                    ->orderByDesc('site_theme_template_meta.updated_at')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($group) use ($usageSlotLabels, $usageSlotOrder) {
                        $item = $group->first();
                        $relationLabels = $group
                            ->pluck('usage_slot')
                            ->map(fn ($slot) => (string) $slot)
                            ->unique()
                            ->sortBy(fn ($slot) => $usageSlotOrder[$slot] ?? 99)
                            ->map(fn ($slot) => $usageSlotLabels[$slot] ?? '资源引用')
                            ->reject(fn ($label) => $label === '模板资源')
                            ->values()
                            ->all();

                        $templateTitle = trim((string) ($item->title ?? ''));
                        $templateName = (string) ($item->template_name ?? '');
                        $baseTemplateLabel = ThemeTemplateLocator::labelFor($templateName);
                        $templateDisplayLabel = $templateTitle !== '' && $templateTitle !== $baseTemplateLabel
                            ? $baseTemplateLabel.' · '.$templateTitle
                            : $baseTemplateLabel;

                        return [
                            'id' => 'theme-template-'.$item->id,
                            'title' => $templateDisplayLabel,
                            'type_label' => '模板',
                            'channel_name' => sprintf('主题：%s', (string) $item->theme_code),
                            'status_label' => null,
                            'relation_labels' => array_values(array_merge(
                                [$templateDisplayLabel],
                                [sprintf('%s.tpl', $templateName)],
                                $relationLabels
                            )),
                            'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('m-d H:i') : '--',
                            'edit_url' => route('admin.themes.editor', ['template' => $templateName]),
                            'view_url' => null,
                            'updated_sort' => $item->updated_at ? Carbon::parse($item->updated_at)->timestamp : 0,
                        ];
                    })
                    ->values()
            )
            ->concat(
                DB::table('attachment_relations')
                    ->join('sites', function ($join): void {
                        $join->on('sites.id', '=', 'attachment_relations.relation_id')
                            ->where('attachment_relations.relation_type', '=', 'guestbook_setting');
                    })
                    ->where('attachment_relations.attachment_id', $attachmentId)
                    ->where('sites.id', $currentSite->id)
                    ->select([
                        'sites.id',
                        'sites.name',
                        'sites.site_key',
                        'sites.updated_at',
                        'attachment_relations.usage_slot',
                    ])
                    ->orderByDesc('sites.updated_at')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($group) use ($usageSlotLabels, $usageSlotOrder) {
                        $item = $group->first();
                        $relationLabels = $group
                            ->pluck('usage_slot')
                            ->map(fn ($slot) => (string) $slot)
                            ->unique()
                            ->sortBy(fn ($slot) => $usageSlotOrder[$slot] ?? 99)
                            ->map(function ($slot) use ($usageSlotLabels) {
                                return match ($slot) {
                                    'notice_image' => '发布须知背景图',
                                    'notice_link' => '发布须知链接',
                                    default => $usageSlotLabels[$slot] ?? '资源引用',
                                };
                            })
                            ->values()
                            ->all();

                        return [
                            'id' => 'guestbook-setting-'.$item->id,
                            'title' => '留言板设置',
                            'type_label' => '功能模块',
                            'channel_name' => sprintf('站点：%s', (string) $item->name),
                            'status_label' => null,
                            'relation_labels' => $relationLabels,
                            'updated_at' => $item->updated_at ? Carbon::parse($item->updated_at)->format('m-d H:i') : '--',
                            'edit_url' => route('admin.guestbook.settings'),
                            'view_url' => null,
                            'updated_sort' => $item->updated_at ? Carbon::parse($item->updated_at)->timestamp : 0,
                        ];
                    })
                    ->values()
            )
            ->sortByDesc('updated_sort')
            ->values()
            ->map(function ($item) {
                unset($item['updated_sort']);

                return $item;
            })
            ->all();

        return response()->json([
            'attachment' => [
                'id' => (int) $attachment->id,
                'name' => (string) $attachment->origin_name,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Delete an attachment if it is unused.
     */
    public function destroy(Request $request, string $attachmentId): RedirectResponse|JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $attachment = $this->findAttachmentForSite($currentSite->id, $attachmentId);

        abort_unless($attachment, 404);
        abort_if(
            ! $this->canViewAllAttachments($request->user()->id, $currentSite->id)
                && (int) ($attachment->uploaded_by ?? 0) !== (int) $request->user()->id,
            404,
        );

        $used = $this->attachmentIsUsed($attachmentId, $currentSite->id);

        if ($used) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => '该附件已被引用，暂不能删除。',
                ], 422);
            }

            return $this->redirectToAttachmentIndex($request, '该附件已被引用，暂不能删除。');
        }

        Storage::disk('site')->delete($attachment->path);
        DB::table('attachments')
            ->where('site_id', $currentSite->id)
            ->where('id', $attachmentId)
            ->delete();

        $this->logOperation(
            'site',
            'attachment',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            (int) $attachmentId,
            ['name' => $attachment->origin_name],
            $request,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => '附件已删除。',
                'id' => (int) $attachmentId,
            ]);
        }

        return $this->redirectToAttachmentIndex($request, '附件已删除。');
    }

    /**
     * Batch process attachments.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeAttachmentWorkspace($request, $currentSite->id);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $attachments = DB::table('attachments')
            ->where('site_id', $currentSite->id)
            ->whereIn('id', $validated['ids'])
            ->when(
                ! $this->canViewAllAttachments($request->user()->id, $currentSite->id),
                fn ($query) => $query->where('uploaded_by', $request->user()->id),
            )
            ->get(['id', 'disk', 'path']);

        $deleted = 0;
        $skipped = 0;

        foreach ($attachments as $attachment) {
            $used = $this->attachmentIsUsed($attachment->id, $currentSite->id);

            if ($used) {
                $skipped++;
                continue;
            }

            Storage::disk('site')->delete($attachment->path);
            DB::table('attachments')->where('id', $attachment->id)->delete();
            $deleted++;
        }

        $this->logOperation(
            'site',
            'attachment',
            'bulk_delete',
            $currentSite->id,
            $request->user()->id,
            'attachment',
            null,
            ['ids' => $validated['ids'], 'deleted' => $deleted, 'skipped' => $skipped],
            $request,
        );

        return $this->redirectToAttachmentIndex($request, "批量处理完成，删除 {$deleted} 个附件，跳过 {$skipped} 个。");
    }

    /**
     * @return array{
     *   id:int,
     *   name:string,
     *   url:string,
     *   path:string,
     *   relativeUrl:string,
     *   extension:string,
     *   width:int|null,
     *   height:int|null,
     *   usageCount:int,
     *   createdAt:string|null,
     *   uploadedByName:string
     * }
     */
    protected function serializeAttachmentLibraryItem(object $attachment): array
    {
        return [
            'id' => (int) ($attachment->id ?? 0),
            'name' => (string) ($attachment->origin_name ?? ''),
            'url' => (string) ($attachment->url ?? ''),
            'path' => (string) ($attachment->path ?? ''),
            'relativeUrl' => (string) (parse_url((string) ($attachment->url ?? ''), PHP_URL_PATH) ?: ''),
            'extension' => strtolower((string) ($attachment->extension ?? '')),
            'width' => isset($attachment->width) ? (int) $attachment->width : null,
            'height' => isset($attachment->height) ? (int) $attachment->height : null,
            'usageCount' => (int) ($attachment->usage_count ?? 0),
            'createdAt' => ! empty($attachment->created_at)
                ? Carbon::parse((string) $attachment->created_at)->toIso8601String()
                : null,
            'uploadedByName' => (string) ($attachment->uploaded_by_name ?? '未记录'),
        ];
    }

    protected function normalizeAttachmentBrowserFilters(Request $request): array
    {
        $filter = trim((string) $request->query('filter', 'all'));
        $usage = trim((string) $request->query('usage', 'all'));
        $sort = trim((string) $request->query('sort', 'latest'));

        return [
            'keyword' => trim((string) $request->query('keyword', '')),
            'filter' => in_array($filter, ['all', 'image', 'file'], true) ? $filter : 'all',
            'usage' => in_array($usage, ['all', 'used', 'unused'], true) ? $usage : 'all',
            'sort' => in_array($sort, ['latest', 'oldest'], true) ? $sort : 'latest',
            'unusedDays' => max(0, (int) $request->query('unused_days', 0)),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedImageExtensions(): array
    {
        return array_map('strtolower', $this->systemSettings->imageExtensions());
    }

    /**
     * @param  object  $query
     * @param  array{keyword:string,filter:string,usage:string,sort:string,unusedDays:int}  $filters
     * @param  array<int, string>  $imageExtensions
     */
    protected function applyAttachmentBrowserFilters(
        $query,
        array $filters,
        array $imageExtensions,
        string $tableAlias = 'attachments',
        ?string $usageCountColumn = 'attachments.usage_count',
        ?string $lastUsedColumn = 'attachments.last_used_at',
        string $createdAtColumn = 'attachments.created_at',
        bool $imageOnly = false,
    ): void {
        if ($filters['keyword'] !== '') {
            $query->where("{$tableAlias}.origin_name", 'like', '%'.$filters['keyword'].'%');
        }

        if ($imageOnly || $filters['filter'] === 'image') {
            $query->whereIn(DB::raw("LOWER({$tableAlias}.extension)"), $imageExtensions);
        } elseif ($filters['filter'] === 'file') {
            $query->whereNotIn(DB::raw("LOWER({$tableAlias}.extension)"), $imageExtensions);
        }

        if ($usageCountColumn !== null) {
            if ($filters['usage'] === 'used') {
                $query->where($usageCountColumn, '>', 0);
            } elseif ($filters['usage'] === 'unused') {
                $query->where($usageCountColumn, '=', 0);
            }

            if ($filters['unusedDays'] > 0 && $lastUsedColumn !== null) {
                $query->where($usageCountColumn, 0)
                    ->whereRaw(
                        "COALESCE({$lastUsedColumn}, {$createdAtColumn}) <= ?",
                        [now()->subDays($filters['unusedDays'])],
                    );
            }
        }
    }

    /**
     * @param  object  $query
     * @param  array{keyword:string,filter:string,usage:string,sort:string,unusedDays:int}  $filters
     */
    protected function applyAttachmentBrowserSorting(
        $query,
        array $filters,
        string $createdAtColumn = 'attachments.created_at',
        string $idColumn = 'attachments.id',
    ): void {
        if ($filters['sort'] === 'oldest') {
            $query->orderBy($createdAtColumn)->orderBy($idColumn);

            return;
        }

        $query->orderByDesc($createdAtColumn)->orderByDesc($idColumn);
    }

    protected function redirectToAttachmentIndex(Request $request, string $status): RedirectResponse
    {
        $fallback = route('admin.attachments.index');
        $returnUrl = (string) $request->input('return_url', '');

        if ($returnUrl !== '') {
            $attachmentIndexPath = parse_url($fallback, PHP_URL_PATH) ?: '';
            $returnPath = parse_url($returnUrl, PHP_URL_PATH) ?: '';

            if ($returnPath !== '' && Str::startsWith($returnPath, $attachmentIndexPath)) {
                return redirect()->to($returnUrl)->with('status', $status);
            }
        }

        return redirect()->route('admin.attachments.index')->with('status', $status);
    }

    protected function formatAttachmentSize(int $bytes): string
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

    protected function attachmentIsUsed(int|string $attachmentId, int $siteId): bool
    {
        return (int) DB::table('attachments')
            ->where('site_id', $siteId)
            ->where('id', $attachmentId)
            ->value('usage_count') > 0;
    }

    protected function siteAttachmentStorageLimitMb(int $siteId): int
    {
        return max(0, (int) DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'attachment.storage_limit_mb')
            ->value('setting_value'));
    }

    protected function siteAttachmentStorageLimitLabel(int $siteId): string
    {
        $limitMb = $this->siteAttachmentStorageLimitMb($siteId);

        if ($limitMb <= 0) {
            return '不限';
        }

        return $this->formatAttachmentSize($limitMb * 1024 * 1024);
    }

    protected function validateSiteAttachmentStorageLimit(int $siteId, int $incomingBytes): void
    {
        $limitMb = $this->siteAttachmentStorageLimitMb($siteId);

        if ($limitMb <= 0) {
            return;
        }

        $limitBytes = $limitMb * 1024 * 1024;
        $usedBytes = (int) DB::table('attachments')
            ->where('site_id', $siteId)
            ->sum('size');
        $remainingBytes = max(0, $limitBytes - $usedBytes);

        if (($usedBytes + $incomingBytes) <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => sprintf(
                '当前站点资源库容量不足，剩余 %s，本次上传需要 %s。',
                $this->formatAttachmentSize($remainingBytes),
                $this->formatAttachmentSize($incomingBytes),
            ),
        ]);
    }

    protected function validateSiteAttachmentReplacementStorageLimit(int $siteId, int $incomingBytes, int $currentBytes): void
    {
        $limitMb = $this->siteAttachmentStorageLimitMb($siteId);

        if ($limitMb <= 0) {
            return;
        }

        $limitBytes = $limitMb * 1024 * 1024;
        $usedBytes = (int) DB::table('attachments')
            ->where('site_id', $siteId)
            ->sum('size');
        $projectedBytes = max(0, $usedBytes - $currentBytes) + $incomingBytes;
        $remainingBytes = max(0, $limitBytes - max(0, $usedBytes - $currentBytes));

        if ($projectedBytes <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => sprintf(
                '当前站点资源库容量不足，替换后剩余 %s，本次文件需要 %s。',
                $this->formatAttachmentSize($remainingBytes),
                $this->formatAttachmentSize($incomingBytes),
            ),
        ]);
    }

    /**
     * @param array{temp_path:string|null,mime_type:string,size:int,extension:string,width:int|null,height:int|null} $preparedFile
     */
    protected function storeAttachment(object $site, mixed $file, int $userId, array $preparedFile): int
    {
        $extension = strtolower((string) $preparedFile['extension']);
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = SitePath::attachmentRelative($site, now()->format('Y/m')).'/'.$storedName;

        if (! empty($preparedFile['temp_path'])) {
            $stream = fopen((string) $preparedFile['temp_path'], 'rb');
            Storage::disk('site')->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink((string) $preparedFile['temp_path']);
        } else {
            $path = $file->storeAs(
                SitePath::attachmentRelative($site, now()->format('Y/m')),
                $storedName,
                'site',
            );
        }

        return DB::table('attachments')->insertGetId([
            'site_id' => $site->id,
            'origin_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'site',
            'path' => $path,
            'url' => SitePath::urlForStoredPath($path),
            'mime_type' => (string) $preparedFile['mime_type'],
            'extension' => $extension,
            'size' => (int) $preparedFile['size'],
            'width' => $preparedFile['width'],
            'height' => $preparedFile['height'],
            'uploaded_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function findAttachmentForSite(int $siteId, int|string $attachmentId): ?object
    {
        return DB::table('attachments')
            ->where('site_id', $siteId)
            ->where('id', $attachmentId)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    protected function attachmentUploadRules(bool $imageOnly = false): array
    {
        $extensions = $imageOnly
            ? $this->systemSettings->imageExtensions()
            : $this->systemSettings->attachmentAllowedExtensions();

        return [
            'required',
            'file',
            'mimes:'.implode(',', $extensions),
        ];
    }

    protected function validateImageDimensionsIfNeeded(UploadedFile $file, string $attributeLabel): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! in_array($extension, $this->systemSettings->imageExtensions(), true)) {
            return;
        }

        if (
            $this->systemSettings->attachmentImageAutoResizeEnabled()
            && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
        ) {
            return;
        }

        Validator::make(
            ['file' => $file],
            ['file' => [
                'image',
                'dimensions:max_width='.$this->systemSettings->attachmentImageMaxWidth().',max_height='.$this->systemSettings->attachmentImageMaxHeight(),
            ]],
            [
                'file.dimensions' => sprintf(
                    '图片尺寸不能超过 %d × %d 像素。',
                    $this->systemSettings->attachmentImageMaxWidth(),
                    $this->systemSettings->attachmentImageMaxHeight(),
                ),
            ],
            ['file' => $attributeLabel],
        )->validate();
    }

    /**
     * @return array{temp_path:string|null,mime_type:string,size:int,extension:string,width:int|null,height:int|null}
     */
    protected function prepareStoredAttachmentFile(UploadedFile $file, bool $imageOnly = false, ?string $forcedExtension = null): array
    {
        $extension = strtolower($forcedExtension ?: ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));
        $sourcePath = $file->getRealPath();
        $imageInfo = null;
        $detectedWidth = null;
        $detectedHeight = null;

        if ($sourcePath && in_array($extension, $this->systemSettings->imageExtensions(), true)) {
            $imageInfo = @getimagesize($sourcePath);

            if (is_array($imageInfo) && ! empty($imageInfo[0]) && ! empty($imageInfo[1])) {
                $detectedWidth = (int) $imageInfo[0];
                $detectedHeight = (int) $imageInfo[1];
            }
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || ! extension_loaded('gd')) {
            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        if (! $sourcePath) {
            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        if (! is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        $sourceImage = $this->createImageResource($sourcePath, $extension);

        if (! $sourceImage) {
            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        $originalWidth = (int) $imageInfo[0];
        $originalHeight = (int) $imageInfo[1];
        $targetWidth = $originalWidth;
        $targetHeight = $originalHeight;
        $originalBytes = (int) ($file->getSize() ?: 0);
        $outputExtension = $extension;
        $autoCompressEnabled = $this->systemSettings->attachmentImageAutoCompressEnabled();
        $requiresResize = false;
        $requiresReencode = false;
        $convertPngToJpeg = $forcedExtension === null
            && $extension === 'png'
            && ! $this->pngHasTransparency($sourcePath);

        if ($convertPngToJpeg) {
            $outputExtension = 'jpg';
        }

        if ($this->systemSettings->attachmentImageAutoResizeEnabled()) {
            [$targetWidth, $targetHeight] = $this->calculateTargetDimensions($originalWidth, $originalHeight);
            $requiresResize = $targetWidth !== $originalWidth || $targetHeight !== $originalHeight;
        }

        if ($requiresResize || $autoCompressEnabled) {
            $requiresReencode = true;
        }

        if (! $requiresResize && ! $requiresReencode) {
            imagedestroy($sourceImage);

            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => $originalBytes,
                'extension' => $outputExtension,
                'width' => $originalWidth,
                'height' => $originalHeight,
            ];
        }

        $canvas = $sourceImage;

        if ($requiresResize || $convertPngToJpeg) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if (($extension === 'png' || $extension === 'webp') && ! $convertPngToJpeg) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            } else {
                $background = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);
            }

            imagecopyresampled(
                $canvas,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $originalWidth,
                $originalHeight,
            );

            imagedestroy($sourceImage);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'attachment_');

        if (! $tempPath) {
            imagedestroy($canvas);

            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        $outputPath = $tempPath.'.'.$outputExtension;
        @unlink($tempPath);

        $saved = $this->writeImageResource($canvas, $outputPath, $outputExtension);
        imagedestroy($canvas);

        if (! $saved || ! is_file($outputPath)) {
            @unlink($outputPath);

            return [
                'temp_path' => null,
                'mime_type' => (string) $file->getClientMimeType(),
                'size' => (int) ($file->getSize() ?: 0),
                'extension' => $extension,
                'width' => $detectedWidth,
                'height' => $detectedHeight,
            ];
        }

        return [
            'temp_path' => $outputPath,
            'mime_type' => $this->mimeTypeForExtension($outputExtension),
            'size' => (int) (filesize($outputPath) ?: 0),
            'extension' => $outputExtension,
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * @param array{temp_path:string|null,mime_type:string,size:int,extension:string,width:int|null,height:int|null} $preparedFile
     */
    protected function overwriteAttachmentFile(string $path, UploadedFile $file, array $preparedFile): void
    {
        if (! empty($preparedFile['temp_path'])) {
            $stream = fopen((string) $preparedFile['temp_path'], 'rb');
            Storage::disk('site')->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink((string) $preparedFile['temp_path']);

            return;
        }

        $sourcePath = $file->getRealPath();
        $stream = $sourcePath ? fopen($sourcePath, 'rb') : null;

        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'file' => '替换文件读取失败，请重新上传。',
            ]);
        }

        Storage::disk('site')->put($path, $stream);
        fclose($stream);
    }

    /**
     * @param array{temp_path:string|null,mime_type:string,size:int,extension:string} $preparedFile
     */
    protected function validatePreparedAttachmentSize(array $preparedFile, string $attributeLabel, bool $imageOnly = false): void
    {
        $maxSizeMb = $imageOnly
            ? $this->systemSettings->attachmentImageMaxSizeMb()
            : $this->systemSettings->attachmentMaxSizeMb();
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;

        if ((int) $preparedFile['size'] <= $maxSizeBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => sprintf(
                '%s不能超过 %s。',
                $attributeLabel,
                $this->formatAttachmentSize($maxSizeBytes),
            ),
        ]);
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function calculateTargetDimensions(int $width, int $height): array
    {
        $maxWidth = $this->systemSettings->attachmentImageMaxWidth();
        $maxHeight = $this->systemSettings->attachmentImageMaxHeight();

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) floor($width * $ratio)),
            max(1, (int) floor($height * $ratio)),
        ];
    }

    protected function createImageResource(string $path, string $extension): mixed
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    protected function writeImageResource(mixed $image, string $path, string $extension): bool
    {
        $quality = $this->systemSettings->attachmentImageQuality();

        return match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $path, $quality),
            'png' => imagepng($image, $path, (int) round((100 - $quality) / 100 * 9)),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $path, $quality) : false,
            default => false,
        };
    }

    protected function pngHasTransparency(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if (! is_resource($handle)) {
            return true;
        }

        $signature = fread($handle, 8);

        if ($signature !== "\x89PNG\r\n\x1a\n") {
            fclose($handle);

            return true;
        }

        while (! feof($handle)) {
            $lengthBytes = fread($handle, 4);
            $type = fread($handle, 4);

            if ($lengthBytes === false || strlen($lengthBytes) !== 4 || $type === false || strlen($type) !== 4) {
                break;
            }

            $length = unpack('N', $lengthBytes)[1] ?? 0;
            $data = $length > 0 ? fread($handle, $length) : '';
            fread($handle, 4);

            if ($type === 'tRNS') {
                fclose($handle);

                return true;
            }

            if ($type === 'IDAT' || $type === 'IEND') {
                break;
            }
        }

        fclose($handle);

        $image = @imagecreatefrompng($path);

        if (! $image) {
            return true;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;

                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
