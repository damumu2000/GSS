<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\AttachmentUsageTracker;
use App\Support\ContentHtmlSanitizer;
use App\Support\ContentAttachmentRelationSync;
use App\Support\RichContentImportService;
use App\Support\SystemSettings;
use App\Support\ThemeTemplateLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use function defined;

class ContentController extends Controller
{
    /**
     * Display a listing of the content entries for the current site.
     */
    public function index(Request $request, string $type): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);
        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', '');
        $channelId = (string) $request->query('channel_id', '');
        $mine = (string) $request->query('mine', '');

        $contentsQuery = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $currentSite->id)
            ->where('contents.type', $type)
            ->whereNull('contents.deleted_at')
            ->when($mine === 'drafts', function ($query) use ($request): void {
                $query->where('contents.created_by', $request->user()->id)
                    ->where('contents.status', 'draft');
            })
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('contents.title', 'like', '%'.$keyword.'%')
                        ->orWhere('contents.summary', 'like', '%'.$keyword.'%');
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('contents.status', $status);
            })
            ->when($channelId !== '', function ($query) use ($channelId): void {
                $query->whereExists(function ($subQuery) use ($channelId): void {
                    $subQuery->selectRaw('1')
                        ->from('content_channels')
                        ->whereColumn('content_channels.content_id', 'contents.id')
                        ->where('content_channels.channel_id', (int) $channelId);
                });
            });

        $this->applySiteContentVisibilityScope($contentsQuery, $request->user()->id, $currentSite->id);

        $contents = $contentsQuery
            ->orderByDesc('contents.sort')
            ->orderByDesc('contents.updated_at')
            ->paginate(10, [
                'contents.id',
                'contents.sort',
                'contents.title',
                'contents.title_color',
                'contents.title_bold',
                'contents.title_italic',
                'contents.is_top',
                'contents.is_recommend',
                'contents.status',
                'contents.audit_status',
                'contents.published_at',
                'contents.updated_at',
                'channels.name as channel_name',
                DB::raw("(select reason from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected' order by content_review_records.created_at desc limit 1) as latest_reject_reason"),
                DB::raw("(select count(*) from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected') as reject_count"),
            ])
            ->withQueryString();

        $channelNamesByContentId = DB::table('content_channels')
            ->join('channels', 'channels.id', '=', 'content_channels.channel_id')
            ->whereIn('content_channels.content_id', $contents->pluck('id')->all())
            ->orderBy('content_channels.id')
            ->get([
                'content_channels.content_id',
                'channels.name',
            ])
            ->groupBy('content_id')
            ->map(fn ($items) => $items->pluck('name')->filter()->values()->all());

        $contents->getCollection()->transform(function (object $content) use ($channelNamesByContentId): object {
            $channelNames = $channelNamesByContentId->get($content->id, []);

            if ($channelNames === [] && !empty($content->channel_name)) {
                $channelNames = [$content->channel_name];
            }

            $content->channel_names = $channelNames;

            return $content;
        });

        $channels = $this->contentChannelOptions($currentSite->id, $manageableChannelIds, $type);

        $articleRequiresReview = $type === 'article' && $this->siteRequiresArticleReview($currentSite->id);
        $statusOptions = $this->contentStatusOptionsForIndex($type, $articleRequiresReview);

        return view('admin.site.contents.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'contents' => $contents,
            'channels' => $channels,
            'type' => $type,
            'typeLabel' => $type === 'page' ? '单页面' : '文章',
            'statuses' => $statusOptions,
            'keyword' => $keyword,
            'selectedStatus' => $status,
            'selectedChannelId' => $channelId,
            'canPublish' => in_array('content.publish', $this->sitePermissionCodes($request->user()->id, $currentSite->id), true),
            'canAudit' => $this->canAuditContent($request->user()->id, $currentSite->id),
            'articleRequiresReview' => $articleRequiresReview,
            'statusFilterLabel' => $this->contentStatusFilterLabel($type, $articleRequiresReview),
        ]);
    }

    /**
     * Display the create form for a content entry.
     */
    public function create(Request $request, string $type): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $channels = $this->contentChannelOptions($currentSite->id, $manageableChannelIds, $type);

        $canPublish = in_array('content.publish', $this->sitePermissionCodes($request->user()->id, $currentSite->id), true);

        $content = (object) [
            'id' => null,
            'channel_id' => $this->resolveDefaultChannelId(
                $request,
                ($type === 'article' ? $channels->where('is_selectable', true) : $channels)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ),
            'template_name' => '',
            'cover_image' => '',
            'title' => '',
            'title_color' => '',
            'title_bold' => 0,
            'title_italic' => 0,
            'is_top' => 0,
            'is_recommend' => 0,
            'author' => $request->user()->name,
            'source' => $currentSite->name,
            'summary' => '',
            'content' => '',
            'status' => $canPublish ? 'published' : 'draft',
            'published_at' => now(),
        ];

        $publisherName = trim((string) ($request->user()->name ?? '')) ?: trim((string) ($request->user()->username ?? '')) ?: '未记录';

        return view('admin.site.contents.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'content' => $content,
            'publisherName' => $publisherName,
            'channels' => $channels,
            'type' => $type,
            'typeLabel' => $type === 'page' ? '单页面' : '文章',
            'statuses' => config('cms.content_statuses'),
            'canPublish' => $canPublish,
            'canAudit' => $this->canAuditContent($request->user()->id, $currentSite->id),
            'articleRequiresReview' => $type === 'article' && $this->siteRequiresArticleReview($currentSite->id),
            'attachmentLibraryWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'templateOptions' => $this->contentTemplateOptions((int) $currentSite->id, $type),
            'selectedChannelIds' => $this->defaultSelectedChannelIds(
                $type,
                $request,
                (int) $content->channel_id,
                $channels,
            ),
            'lockedSelectedChannels' => collect(),
            'reviewHistory' => collect(),
            'isCreate' => true,
        ]);
    }

    /**
     * Display the edit form for a content entry.
     */
    public function edit(Request $request, string $contentId, string $type): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $contentQuery = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', $type)
            ->where('id', $contentId);

        $this->applySiteContentVisibilityScope($contentQuery, $request->user()->id, $currentSite->id);

        $content = $contentQuery->first();

        abort_unless($content, 404);
        abort_if($content->deleted_at !== null, 403, '该内容已在回收站中，请先恢复后再编辑。');

        $publisherName = DB::table('users')
            ->where('id', (int) ($content->created_by ?? 0))
            ->value(DB::raw("COALESCE(NULLIF(name, ''), NULLIF(username, ''), '未记录')"));

        $publisherName = is_string($publisherName) && trim($publisherName) !== ''
            ? trim($publisherName)
            : (trim((string) ($request->user()->name ?? '')) ?: trim((string) ($request->user()->username ?? '')) ?: '未记录');

        $channels = $this->contentChannelOptions($currentSite->id, $manageableChannelIds, $type);

        $selectedChannelIds = $this->selectedContentChannelIds((int) $content->id, (int) ($content->channel_id ?? 0));
        $selectableChannelIds = ($type === 'article'
            ? $channels->where('is_selectable', true)
            : $channels)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $lockedSelectedChannels = $this->lockedSelectedChannels($currentSite->id, $selectedChannelIds, $selectableChannelIds);

        $latestRejectedReview = null;
        $latestReviewRecord = null;
        $rejectCount = 0;
        $reviewHistory = collect();

        if ($type === 'article') {
            $latestRejectedReview = DB::table('content_review_records')
                ->where('content_id', $content->id)
                ->where('site_id', $currentSite->id)
                ->where('action', 'rejected')
                ->orderByDesc('created_at')
                ->first();

            $rejectCount = (int) DB::table('content_review_records')
                ->where('content_id', $content->id)
                ->where('site_id', $currentSite->id)
                ->where('action', 'rejected')
                ->count();

            $reviewHistory = DB::table('content_review_records')
                ->where('content_id', $content->id)
                ->where('site_id', $currentSite->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get([
                    'action',
                    'reason',
                    'reviewer_name',
                    'reviewer_phone',
                    'created_at',
                ]);

            $latestReviewRecord = $reviewHistory->first();
        }

        return view('admin.site.contents.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'content' => $content,
            'publisherName' => $publisherName,
            'channels' => $channels,
            'type' => $type,
            'typeLabel' => $type === 'page' ? '单页面' : '文章',
            'statuses' => config('cms.content_statuses'),
            'canPublish' => in_array('content.publish', $this->sitePermissionCodes($request->user()->id, $currentSite->id), true),
            'canAudit' => $this->canAuditContent($request->user()->id, $currentSite->id),
            'articleRequiresReview' => $type === 'article' && $this->siteRequiresArticleReview($currentSite->id),
            'attachmentLibraryWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'templateOptions' => $this->contentTemplateOptions((int) $currentSite->id, $type),
            'selectedChannelIds' => $selectedChannelIds,
            'lockedSelectedChannels' => $lockedSelectedChannels,
            'latestRejectedReview' => $latestRejectedReview,
            'latestReviewRecord' => $latestReviewRecord,
            'rejectCount' => $rejectCount,
            'reviewHistory' => $reviewHistory,
            'isCreate' => false,
        ]);
    }

    /**
     * Store a newly created content entry.
     */
    public function store(Request $request, string $type): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $validated = $this->validateContent($request, $type);
        $this->authorizePublishIfNeeded($request, $currentSite->id, $validated['status']);
        $selectedChannelIds = $this->resolvedSubmittedChannelIds($validated, $type);
        $this->authorizeChannelSelection($selectedChannelIds, $manageableChannelIds, $currentSite->id, $type === 'article');
        $requestedStatus = $validated['status'];
        $resolvedStatus = $this->resolveContentStatus($request, $currentSite->id, $type, $requestedStatus);
        $publishedAt = $this->resolvePublishedAt($validated['published_at'] ?? null, $resolvedStatus);

        $contentId = DB::transaction(function () use ($currentSite, $request, $validated, $type, $requestedStatus, $resolvedStatus, $publishedAt, $selectedChannelIds): int {
            $contentId = (int) DB::table('contents')->insertGetId([
                'site_id' => $currentSite->id,
                'channel_id' => $selectedChannelIds[0] ?? null,
                'type' => $type,
                'template_name' => in_array($type, ['article', 'page'], true) ? $this->normalizeTemplateName($validated['template_name'] ?? null) : null,
                'title' => $validated['title'],
                'title_color' => $validated['title_color'] ?? null,
                'title_bold' => ! empty($validated['title_bold']),
                'title_italic' => ! empty($validated['title_italic']),
                'is_top' => ! empty($validated['is_top']),
                'is_recommend' => ! empty($validated['is_recommend']),
                'slug' => Str::slug($validated['title']).'-'.Str::random(6),
                'cover_image' => $validated['cover_image'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'content' => $validated['content'] ?? null,
                'author' => $request->user()->name,
                'source' => trim((string) ($validated['source'] ?? '')) ?: $currentSite->name,
                'status' => $resolvedStatus,
                'audit_status' => $this->mapAuditStatus($resolvedStatus),
                'sort' => $this->nextContentSortValue((int) $currentSite->id, $type),
                'published_at' => $publishedAt,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('content_revisions')->insert([
                'content_id' => $contentId,
                'site_id' => $currentSite->id,
                'version_no' => 1,
                'title' => $validated['title'],
                'title_color' => $validated['title_color'] ?? null,
                'title_bold' => ! empty($validated['title_bold']),
                'title_italic' => ! empty($validated['title_italic']),
                'summary' => $validated['summary'] ?? null,
                'content' => $validated['content'] ?? null,
                'operator_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordReviewActionIfNeeded(
                $currentSite->id,
                $contentId,
                $request,
                $type,
                $requestedStatus,
                $resolvedStatus,
            );

            $this->syncContentChannels($contentId, $selectedChannelIds);

            return $contentId;
        });

        $this->syncAttachments($currentSite->id, $contentId, []);

        $this->logOperation(
            'site',
            'content',
            'create',
            $currentSite->id,
            $request->user()->id,
            'content',
            $contentId,
            ['title' => $validated['title'], 'type' => $type],
            $request,
        );

        $route = $type === 'page'
            ? 'admin.pages.index'
            : 'admin.articles.index';

        return redirect()
            ->route($route)
            ->with('status', $type === 'page' ? '单页面已创建。' : '文章已创建。');
    }

    /**
     * Update an existing content entry.
     */
    public function update(Request $request, string $contentId, string $type): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);
        $validated = $this->validateContent($request, $type);
        $this->authorizePublishIfNeeded($request, $currentSite->id, $validated['status']);
        $selectedChannelIds = $this->resolvedSubmittedChannelIds($validated, $type);
        $this->authorizeChannelSelection($selectedChannelIds, $manageableChannelIds, $currentSite->id, $type === 'article');

        $contentQuery = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', $type)
            ->where('id', $contentId);

        $this->applySiteContentVisibilityScope($contentQuery, $request->user()->id, $currentSite->id);

        $content = $contentQuery->first();

        abort_unless($content, 404);
        abort_if($content->deleted_at !== null, 403, '该内容已在回收站中，请先恢复后再编辑。');

        $requestedStatus = $validated['status'];
        $resolvedStatus = $this->resolveContentStatus($request, $currentSite->id, $type, $requestedStatus);
        $publishedAt = $this->resolvePublishedAt(
            $validated['published_at'] ?? null,
            $resolvedStatus,
            $content->published_at,
        );

        DB::transaction(function () use ($contentId, $currentSite, $request, $validated, $content, $type, $requestedStatus, $resolvedStatus, $publishedAt, $selectedChannelIds, $manageableChannelIds): void {
            $finalChannelIds = $this->mergedContentChannelIdsForUpdate(
                (int) $contentId,
                $selectedChannelIds,
                $manageableChannelIds,
                $currentSite->id,
                $type,
                (int) $request->user()->id,
                (int) ($content->channel_id ?? 0),
            );

            DB::table('contents')
                ->where('id', $contentId)
                ->update([
                    'channel_id' => $finalChannelIds['primary_channel_id'],
                    'template_name' => in_array($type, ['article', 'page'], true) ? $this->normalizeTemplateName($validated['template_name'] ?? null) : null,
                    'title' => $validated['title'],
                    'title_color' => $validated['title_color'] ?? null,
                    'title_bold' => ! empty($validated['title_bold']),
                    'title_italic' => ! empty($validated['title_italic']),
                    'is_top' => ! empty($validated['is_top']),
                    'is_recommend' => ! empty($validated['is_recommend']),
                    'cover_image' => $validated['cover_image'] ?? null,
                    'summary' => $validated['summary'] ?? null,
                    'content' => $validated['content'] ?? null,
                    'author' => $request->user()->name,
                    'source' => trim((string) ($validated['source'] ?? '')) ?: $currentSite->name,
                    'status' => $resolvedStatus,
                    'audit_status' => $this->mapAuditStatus($resolvedStatus),
                    'published_at' => $publishedAt,
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            $versionNo = DB::table('content_revisions')
                ->where('content_id', $contentId)
                ->max('version_no') ?? 0;

            DB::table('content_revisions')->insert([
                'content_id' => $contentId,
                'site_id' => $currentSite->id,
                'version_no' => $versionNo + 1,
                'title' => $validated['title'],
                'title_color' => $validated['title_color'] ?? null,
                'title_bold' => ! empty($validated['title_bold']),
                'title_italic' => ! empty($validated['title_italic']),
                'summary' => $validated['summary'] ?? null,
                'content' => $validated['content'] ?? null,
                'operator_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordReviewActionIfNeeded(
                $currentSite->id,
                (int) $contentId,
                $request,
                $type,
                $requestedStatus,
                $resolvedStatus,
                $content,
            );

            $this->syncContentChannels((int) $contentId, $finalChannelIds['channel_ids']);
        });

        $this->syncAttachments($currentSite->id, (int) $contentId, []);

        $this->logOperation(
            'site',
            'content',
            'update',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $contentId,
            ['title' => $validated['title'], 'type' => $type],
            $request,
        );

        return redirect()
            ->route($type === 'page' ? 'admin.pages.edit' : 'admin.articles.edit', array_filter([
                'content' => (int) $contentId,
                'return_to' => ($returnTo = (string) $request->input('return_to', '')) !== '' && str_starts_with($returnTo, url('/'))
                    ? $returnTo
                    : null,
            ], static fn ($value): bool => $value !== null))
            ->with('status', $type === 'page' ? '单页面已更新。' : '文章已更新。');
    }

    /**
     * Soft delete an existing content entry.
     */
    public function destroy(Request $request, string $contentId, string $type): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);

        $contentQuery = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', $type)
            ->where('id', $contentId);

        $this->applySiteContentVisibilityScope($contentQuery, $request->user()->id, $currentSite->id);

        $content = $contentQuery->first();

        abort_unless($content, 404);

        DB::table('contents')
            ->where('id', $contentId)
            ->update([
                'deleted_at' => now(),
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        $this->refreshAttachmentUsageForContentIds($currentSite->id, [(int) $contentId]);

        $this->logOperation(
            'site',
            'content',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $contentId,
            ['title' => $content->title, 'type' => $type],
            $request,
        );

        $fallbackRoute = $type === 'page' ? 'admin.pages.index' : 'admin.articles.index';
        $returnTo = (string) $request->input('return_to', '');
        $fallbackUrl = route($fallbackRoute);

        if ($returnTo !== '' && str_starts_with($returnTo, url('/'))) {
            return redirect($returnTo)
                ->with('status', $type === 'page' ? '单页面已删除。' : '文章已删除。');
        }

        return redirect($fallbackUrl)
            ->with('status', $type === 'page' ? '单页面已删除。' : '文章已删除。');
    }

    /**
     * Batch process content entries.
     */
    public function bulk(Request $request, string $type): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete,publish,offline'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'return_to' => ['nullable', 'string'],
        ]);

        $contents = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->whereIn('id', $validated['ids'])
            ->get(['id']);

        $contentIds = $contents->pluck('id')->all();

        if ($contentIds !== []) {
            $contentsQuery = DB::table('contents')
                ->where('site_id', $currentSite->id)
                ->where('type', $type)
                ->whereNull('deleted_at')
                ->whereIn('id', $contentIds);

            $this->applySiteContentVisibilityScope($contentsQuery, $request->user()->id, $currentSite->id);

            $contents = $contentsQuery->get(['id']);
        }

        if ($validated['action'] === 'publish') {
            $this->authorizeSite($request, $currentSite->id, 'content.publish');

            $resolvedStatus = ($type === 'article' && $this->siteRequiresArticleReview($currentSite->id))
                ? 'pending'
                : 'published';

            DB::table('contents')
                ->whereIn('id', $contents->pluck('id'))
                ->update([
                    'status' => $resolvedStatus,
                    'audit_status' => $this->mapAuditStatus($resolvedStatus),
                    'published_at' => $resolvedStatus === 'published' ? now() : DB::raw('published_at'),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            foreach ($contents->pluck('id') as $resolvedContentId) {
                $this->recordReviewActionIfNeeded(
                    $currentSite->id,
                    (int) $resolvedContentId,
                    $request,
                    $type,
                    'published',
                    $resolvedStatus,
                );
            }

            $message = $resolvedStatus === 'pending'
                ? '批量提交审核已完成。'
                : '批量发布已完成。';
        } elseif ($validated['action'] === 'offline') {
            DB::table('contents')
                ->whereIn('id', $contents->pluck('id'))
                ->update([
                    'status' => 'offline',
                    'audit_status' => 'draft',
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            $message = '批量下线已完成。';
        } else {
            $affectedAttachmentIds = $this->attachmentIdsForContentIds(
                $currentSite->id,
                $contents->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );

            DB::table('contents')
                ->whereIn('id', $contents->pluck('id'))
                ->update([
                    'deleted_at' => now(),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            if ($affectedAttachmentIds !== []) {
                (new AttachmentUsageTracker())->rebuildForAttachmentIds($affectedAttachmentIds, $currentSite->id);
            }

            $message = $type === 'page' ? '批量删除单页面已完成。' : '批量删除文章已完成。';
        }

        $this->logOperation(
            'site',
            'content',
            'bulk_'.$validated['action'],
            $currentSite->id,
            $request->user()->id,
            'content',
            null,
            ['ids' => $contents->pluck('id')->all(), 'type' => $type],
            $request,
        );

        $fallbackUrl = route($type === 'page' ? 'admin.pages.index' : 'admin.articles.index');
        $returnTo = (string) ($validated['return_to'] ?? '');

        if ($returnTo !== '' && str_starts_with($returnTo, url('/'))) {
            return redirect($returnTo)->with('status', $message);
        }

        return redirect($fallbackUrl)->with('status', $message);
    }

    /**
     * Persist a visible-list reorder operation for content.
     */
    public function reorder(Request $request, string $type): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $this->assertType($type);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $orderedIds = array_map('intval', $validated['ordered_ids']);
        $itemsQuery = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->whereIn('id', $orderedIds);

        $this->applySiteContentVisibilityScope($itemsQuery, $request->user()->id, $currentSite->id);

        $items = $itemsQuery->get(['id', 'sort']);

        if ($items->count() !== count($orderedIds)) {
            return response()->json([
                'message' => '排序保存失败，部分内容已不可用，请刷新页面后重试。',
            ], 422);
        }

        $sortValues = $items
            ->pluck('sort')
            ->map(fn ($sort) => (int) $sort)
            ->sortDesc()
            ->values()
            ->all();

        DB::transaction(function () use ($orderedIds, $sortValues, $request): void {
            foreach ($orderedIds as $index => $contentId) {
                DB::table('contents')
                    ->where('id', $contentId)
                    ->update([
                        'sort' => $sortValues[$index] ?? 0,
                        'updated_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->logOperation(
            'site',
            'content',
            'reorder',
            $currentSite->id,
            $request->user()->id,
            'content',
            null,
            [
                'type' => $type,
                'ordered_ids' => $orderedIds,
            ],
            $request,
        );

        return response()->json([
            'message' => ($type === 'page' ? '单页面' : '文章').'排序已保存。',
        ]);
    }

    /**
     * Ensure the requested content type is supported.
     */
    protected function assertType(string $type): void
    {
        abort_unless(in_array($type, ['page', 'article'], true), 404);
    }

    /**
     * Validate a content payload.
     *
     * @return array<string, mixed>
     */
    protected function validateContent(Request $request, string $type): array
    {
        $validator = Validator::make($request->all(), [
            'channel_id' => [$type === 'page' ? 'nullable' : 'sometimes', 'integer', 'exists:channels,id'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['integer', 'distinct', 'exists:channels,id'],
            'template_name' => [
                'nullable',
                'string',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $type): void {
                    $template = $this->normalizeTemplateName($value);

                    if ($template === null) {
                        return;
                    }

                    $options = $this->contentTemplateOptions((int) $this->currentSite($request)->id, $type);

                    if ($options === []) {
                        $fail($type === 'page' ? '当前内容类型不支持单页模板。' : '当前内容类型不支持详情模板。');
                        return;
                    }

                    if (! array_key_exists($template, $options)) {
                        $fail($type === 'page' ? '请选择当前主题可用的单页模板。' : '请选择当前主题可用的详情模板。');
                    }
                },
            ],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'title' => ['required', 'string', 'max:255'],
            'title_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{6})$/'],
            'title_bold' => ['nullable', 'boolean'],
            'title_italic' => ['nullable', 'boolean'],
            'is_top' => ['nullable', 'boolean'],
            'is_recommend' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string'],
            'content' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->hasMeaningfulEditorContent((string) $value)) {
                        $fail('正文不能为空。');
                    }
                },
            ],
            'author' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:20'],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], [
            'published_at.date_format' => '发布时间格式不正确，请使用 4 位年份日期时间。',
        ], [
            'channel_id' => '所属栏目',
            'channel_ids' => '所属栏目',
            'channel_ids.*' => '所属栏目',
            'template_name' => $type === 'page' ? '单页模板' : '详情模板',
            'cover_image' => '封面图',
            'title' => '标题',
            'title_color' => '标题颜色',
            'title_bold' => '标题加粗',
            'title_italic' => '标题斜体',
            'is_top' => '置顶',
            'is_recommend' => '精华',
            'summary' => '摘要',
            'content' => '正文',
            'author' => '作者',
            'source' => '来源',
            'status' => '状态',
            'published_at' => '发布时间',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $data = $validator->getData();
            $sanitizedContent = ContentHtmlSanitizer::sanitize((string) ($data['content'] ?? ''));
            $data['content'] = $sanitizedContent;
            $validator->setData($data);
            $request->merge(['content' => $sanitizedContent]);
        });

        $validator->after(function ($validator) use ($request): void {
            $currentSite = $this->currentSite($request);
            $userId = (int) $request->user()->id;
            $data = $validator->getData();
            $coverImage = trim((string) ($data['cover_image'] ?? ''));

            if ($coverImage !== '' && ! $this->canAccessVisibleAttachmentUrl((int) $currentSite->id, $userId, [$coverImage], true)) {
                $validator->errors()->add('cover_image', '封面图不可访问，请重新从可用资源中选择。');
            }
        });

        return $validator->validate();
    }

    protected function contentTemplateOptions(int $siteId, string $type): array
    {
        $themeCode = $this->siteThemeCode($siteId);

        if ($themeCode === '') {
            return [];
        }

        $options = [];

        foreach (ThemeTemplateLocator::availableTemplatesForSite($siteId, $themeCode) as $templateItem) {
            $template = $templateItem['file'];

            if ($type === 'page') {
                if ($template !== 'page' && ! str_starts_with($template, 'page-')) {
                    continue;
                }
            } elseif ($type === 'article') {
                if ($template !== 'detail' && ! str_starts_with($template, 'detail-')) {
                    continue;
                }
            } else {
                continue;
            }

            $options[$template] = trim(($templateItem['label'] ?? '').' '.$template.'.tpl');
        }

        ksort($options);

        return $options;
    }

    protected function normalizeTemplateName(mixed $value): ?string
    {
        $template = trim((string) $value);

        return $template === '' ? null : $template;
    }

    protected function hasMeaningfulEditorContent(string $content): bool
    {
        if (trim($content) === '') {
            return false;
        }

        if (preg_match('/<(img|video|iframe|embed|object|audio|table|blockquote|pre|ul|ol)\b/i', $content) === 1) {
            return true;
        }

        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}\s]+/u', '', $text) ?? '';

        return $text !== '';
    }

    protected function authorizePublishIfNeeded(Request $request, int $siteId, string $status): void
    {
        if ($status === 'published' && ! $this->siteRequiresArticleReview($siteId)) {
            $this->authorizeSite($request, $siteId, 'content.publish');
        }
    }

    protected function authorizeChannelSelection(array $channelIds, array $manageableChannelIds, int $siteId, bool $leafOnly = false): void
    {
        if ($channelIds === []) {
            return;
        }

        $selectableChannelIds = $leafOnly
            ? $this->selectableContentChannelIds($siteId, $manageableChannelIds)
            : ($manageableChannelIds === []
                ? DB::table('channels')->where('site_id', $siteId)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : array_values(array_unique(array_map('intval', $manageableChannelIds))));

        abort_unless(
            collect($channelIds)->every(fn (int $channelId): bool => in_array($channelId, $selectableChannelIds, true)),
            403,
            '当前账号不能操作该栏目下的内容。'
        );
    }

    protected function resolveDefaultChannelId(Request $request, array $availableChannelIds): ?int
    {
        $channelId = $request->query('channel_id');

        if ($channelId === null || $channelId === '') {
            return null;
        }

        $channelId = (int) $channelId;

        return in_array($channelId, $availableChannelIds, true) ? $channelId : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    protected function resolvedSubmittedChannelIds(array $validated, string $type): array
    {
        $channelIds = $this->parseContentChannelIds($validated['channel_ids'] ?? []);

        if ($channelIds !== []) {
            return $channelIds;
        }

        return $this->parseContentChannelIds($validated['channel_id'] ?? null);
    }

    /**
     * @param  array<int, int>  $channelIds
     */
    protected function syncContentChannels(int $contentId, array $channelIds): void
    {
        DB::table('content_channels')->where('content_id', $contentId)->delete();

        if ($channelIds === []) {
            return;
        }

        $now = now();

        DB::table('content_channels')->insert(
            collect($channelIds)
                ->values()
                ->map(fn (int $channelId): array => [
                    'content_id' => $contentId,
                    'channel_id' => $channelId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    /**
     * @param  array<int, int>  $submittedChannelIds
     * @param  array<int, int>  $manageableChannelIds
     * @return array{channel_ids: array<int, int>, primary_channel_id: ?int}
     */
    protected function mergedContentChannelIdsForUpdate(
        int $contentId,
        array $submittedChannelIds,
        array $manageableChannelIds,
        int $siteId,
        string $type,
        int $userId,
        int $currentPrimaryChannelId = 0
    ): array {
        if ($this->canViewAllSiteContent($userId, $siteId)) {
            return [
                'channel_ids' => $submittedChannelIds,
                'primary_channel_id' => $submittedChannelIds[0] ?? null,
            ];
        }

        $allChannels = DB::table('channels')
            ->where('site_id', $siteId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'type', 'parent_id']);

        $selectableChannelIds = $this->typedSelectableContentChannelIds(
            $allChannels->filter(fn (object $channel): bool => $type === 'article'
                ? $channel->type === 'list'
                : $channel->type === 'page'
            )->values(),
            $manageableChannelIds,
            $type === 'article' ? ['list'] : ['page']
        );

        $existingChannelIds = $this->selectedContentChannelIds($contentId, $currentPrimaryChannelId);
        $preservedLockedChannelIds = array_values(array_diff($existingChannelIds, $selectableChannelIds));
        $finalChannelIds = array_values(array_unique(array_merge($submittedChannelIds, $preservedLockedChannelIds)));

        $primaryChannelId = null;
        if ($currentPrimaryChannelId > 0 && in_array($currentPrimaryChannelId, $finalChannelIds, true)) {
            $primaryChannelId = $currentPrimaryChannelId;
        } elseif ($finalChannelIds !== []) {
            $primaryChannelId = $finalChannelIds[0];
        }

        return [
            'channel_ids' => $finalChannelIds,
            'primary_channel_id' => $primaryChannelId,
        ];
    }

    /**
     * @param  array<int, int>  $selectedChannelIds
     * @param  array<int, int>  $selectableChannelIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function lockedSelectedChannels(int $siteId, array $selectedChannelIds, array $selectableChannelIds)
    {
        $lockedIds = array_values(array_diff($selectedChannelIds, $selectableChannelIds));

        if ($lockedIds === []) {
            return collect();
        }

        return DB::table('channels')
            ->where('site_id', $siteId)
            ->whereIn('id', $lockedIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<int, int>  $manageableChannelIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function contentChannelOptions(int $siteId, array $manageableChannelIds, string $contentType)
    {
        $allChannels = DB::table('channels')
            ->where('site_id', $siteId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'type', 'parent_id']);

        if ($contentType === 'page') {
            return $this->pageChannelOptions($allChannels, $manageableChannelIds);
        }

        $articleChannels = $allChannels
            ->filter(fn (object $channel): bool => $channel->type === 'list')
            ->values();

        $selectableIds = $this->typedSelectableContentChannelIds(
            $articleChannels,
            $manageableChannelIds,
            ['list']
        );

        if ($selectableIds === []) {
            return collect();
        }

        return $this->flattenContentChannels(
            $this->visibleContentChannels($articleChannels, $selectableIds),
            $selectableIds
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @param  array<int, int>  $manageableChannelIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function pageChannelOptions($channels, array $manageableChannelIds)
    {
        $pageChannels = $channels
            ->filter(fn (object $channel): bool => $channel->type === 'page')
            ->values();

        $selectableIds = $this->typedSelectableContentChannelIds(
            $pageChannels,
            $manageableChannelIds,
            ['page']
        );

        if ($selectableIds === []) {
            return collect();
        }

        return $this->flattenContentChannels(
            $this->visibleContentChannels($channels, $selectableIds),
            $selectableIds
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @param  array<int, int>  $manageableChannelIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function visibleContentChannels($channels, array $manageableChannelIds)
    {
        if ($manageableChannelIds === []) {
            return $channels->values();
        }

        $channelMap = $channels->keyBy(fn (object $channel) => (int) $channel->id);
        $visibleIds = collect($manageableChannelIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        foreach ($visibleIds->all() as $channelId) {
            $parentId = (int) ($channelMap->get($channelId)?->parent_id ?? 0);

            while ($parentId > 0 && ! $visibleIds->contains($parentId)) {
                $visibleIds->push($parentId);
                $parentId = (int) ($channelMap->get($parentId)?->parent_id ?? 0);
            }
        }

        return $channels
            ->filter(fn (object $channel): bool => $visibleIds->contains((int) $channel->id))
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @param  array<int, int>  $selectableIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function flattenContentChannels($channels, array $selectableIds)
    {
        $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));

        $walk = function (int $parentId, int $depth = 0, array $ancestorLines = []) use (&$walk, $childrenByParent, $selectableIds): array {
            $items = $childrenByParent->get($parentId, collect())->values();
            $flattened = [];

            foreach ($items as $index => $channel) {
                $isLast = $index === $items->count() - 1;
                $channel->tree_depth = $depth;
                $channel->tree_is_last = $isLast;
                $channel->tree_ancestors = $ancestorLines;
                $channel->tree_has_children = $childrenByParent->has((int) $channel->id);
                $channel->is_selectable = in_array((int) $channel->id, $selectableIds, true);
                $flattened[] = $channel;

                $nextAncestorLines = $ancestorLines;
                $nextAncestorLines[] = ! $isLast;

                foreach ($walk((int) $channel->id, $depth + 1, $nextAncestorLines) as $child) {
                    $flattened[] = $child;
                }
            }

            return $flattened;
        };

        return collect($walk(0));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @param  array<int, int>  $manageableChannelIds
     * @return array<int, int>
     */
    protected function selectableContentChannelIdsFromCollection($channels, array $manageableChannelIds): array
    {
        $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));
        $allowedIds = $manageableChannelIds === []
            ? $channels->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_unique(array_map('intval', $manageableChannelIds)));

        return $channels
            ->filter(function (object $channel) use ($childrenByParent, $allowedIds): bool {
                return ! $childrenByParent->has((int) $channel->id)
                    && in_array((int) $channel->id, $allowedIds, true);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @param  array<int, int>  $manageableChannelIds
     * @param  array<int, string>  $allowedTypes
     * @return array<int, int>
     */
    protected function typedSelectableContentChannelIds($channels, array $manageableChannelIds, array $allowedTypes): array
    {
        $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));
        $allowedIds = $manageableChannelIds === []
            ? $channels->pluck('id')->map(fn ($id) => (int) $id)->all()
            : array_values(array_unique(array_map('intval', $manageableChannelIds)));

        return $channels
            ->filter(function (object $channel) use ($childrenByParent, $allowedIds, $allowedTypes): bool {
                return ! $childrenByParent->has((int) $channel->id)
                    && in_array((int) $channel->id, $allowedIds, true)
                    && in_array((string) $channel->type, $allowedTypes, true);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $manageableChannelIds
     * @return array<int, int>
     */
    protected function selectableContentChannelIds(int $siteId, array $manageableChannelIds): array
    {
        $channels = DB::table('channels')
            ->where('site_id', $siteId)
            ->get(['id', 'parent_id']);

        return $this->selectableContentChannelIdsFromCollection($channels, $manageableChannelIds);
    }

    /**
     * @return array<int, int>
     */
    protected function selectedContentChannelIds(int $contentId, int $fallbackChannelId = 0): array
    {
        $selected = DB::table('content_channels')
            ->where('content_id', $contentId)
            ->orderBy('id')
            ->pluck('channel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($selected === [] && $fallbackChannelId > 0) {
            $selected = [$fallbackChannelId];
        }

        if ($fallbackChannelId > 0 && in_array($fallbackChannelId, $selected, true)) {
            $selected = array_values(array_unique(array_merge([$fallbackChannelId], $selected)));
        }

        return $selected;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @return array<int, int>
     */
    protected function defaultSelectedChannelIds(string $type, Request $request, int $defaultChannelId, $channels): array
    {
        $submitted = $this->parseContentChannelIds($request->old('channel_ids'));
        if ($submitted !== []) {
            return $submitted;
        }

        $allowedIds = ($type === 'article'
            ? $channels->where('is_selectable', true)
            : $channels)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $defaultChannelId > 0 && in_array($defaultChannelId, $allowedIds, true)
            ? [$defaultChannelId]
            : [];
    }

    /**
     * @return array<string, string>
     */
    protected function contentStatusOptionsForIndex(string $type, bool $articleRequiresReview): array
    {
        if ($type === 'page') {
            return [
                'draft' => '草稿',
                'published' => '已发布',
                'offline' => '已下线',
            ];
        }

        if ($articleRequiresReview) {
            return [
                'draft' => '草稿',
                'pending' => '待审核',
                'published' => '已发布',
                'offline' => '已下线',
                'rejected' => '已驳回',
            ];
        }

        return [
            'draft' => '草稿',
            'published' => '已发布',
            'offline' => '已下线',
        ];
    }

    protected function contentStatusFilterLabel(string $type, bool $articleRequiresReview): string
    {
        return $type === 'article' && $articleRequiresReview ? '文章状态' : '发布状态';
    }

    public function resolveBilibiliVideo(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
        ], [], [
            'url' => '哔哩哔哩地址',
        ]);

        $parsed = $this->parseBilibiliVideoPageUrl($validated['url']);
        [$aid, $bvid, $pages] = $this->fetchBilibiliVideoMeta($parsed['id_type'], $parsed['id_value']);

        $page = $parsed['page'];
        $pageInfo = collect($pages)->first(fn (array $item): bool => (int) ($item['page'] ?? 0) === $page);
        $pageInfo ??= $pages[0] ?? null;

        abort_unless($pageInfo !== null, 422, '未能解析到该视频的分 P 信息。');

        $resolvedPage = max((int) ($pageInfo['page'] ?? $page), 1);
        $cid = (int) ($pageInfo['cid'] ?? 0);

        abort_unless($cid > 0, 422, '未能解析到该视频的播放器参数。');

        $embedUrl = 'https://player.bilibili.com/player.html?'.http_build_query([
            'isOutside' => 'true',
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'p' => $resolvedPage,
        ]);

        return response()->json([
            'embed_url' => $embedUrl,
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'page' => $resolvedPage,
        ]);
    }

    public function importRichContent(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:30720'],
            'html' => ['nullable', 'string', 'max:2000000'],
        ], [
            'file.max' => '导入文件不能超过 30MB。',
            'html.max' => '导入内容过大，请分段导入。',
        ], [
            'file' => '导入文件',
            'html' => '导入内容',
        ]);

        $hasFile = $request->hasFile('file');
        $htmlInput = trim((string) ($validated['html'] ?? ''));

        if (! $hasFile && $htmlInput === '') {
            return response()->json([
                'message' => '请先选择 Word 文件或粘贴内容后再导入。',
            ], 422);
        }

        if ($hasFile) {
            $extension = strtolower((string) ($validated['file']->getClientOriginalExtension() ?: $validated['file']->extension() ?: ''));
            if (! in_array($extension, ['docx', 'doc', 'wps'], true)) {
                return response()->json([
                    'message' => '仅支持导入 docx、doc、wps 文件。',
                ], 422);
            }
        }

        try {
            $imageMaxBytes = max(1, app(SystemSettings::class)->attachmentImageMaxSizeMb()) * 1024 * 1024;
            $service = new RichContentImportService($imageMaxBytes, $imageMaxBytes * 20);
            $result = $hasFile
                ? $service->importFromOfficeFile($validated['file'])
                : $service->importFromHtml($htmlInput);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception instanceof \RuntimeException && $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : '导入失败，请稍后重试。',
            ], 422);
        }

        return response()->json($result);
    }

    public function importImageFetch(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
        ], [], [
            'url' => '图片地址',
        ]);

        $url = trim((string) ($validated['url'] ?? ''));
        if (! $this->isImportImageUrlAllowed($url)) {
            return response()->json([
                'message' => '图片地址不安全或不可访问，请改用上传图片方式。',
            ], 422);
        }

        try {
            $requestOptions = [
                'allow_redirects' => false,
                'verify' => true,
            ];
            if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
                $requestOptions['curl'] = [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ];
            }

            $response = Http::withOptions($requestOptions)
                ->timeout(10)
                ->accept('*/*')
                ->get($url);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => '图片下载失败，请稍后重试。',
            ], 422);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => '图片下载失败，请稍后重试。',
            ], 422);
        }

        $contentType = strtolower(trim((string) $response->header('Content-Type', '')));
        $contentType = explode(';', $contentType)[0] ?? '';
        if (! in_array($contentType, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return response()->json([
                'message' => '仅支持 jpeg、png、gif、webp 图片资源。',
            ], 422);
        }

        $contentLength = (int) $response->header('Content-Length', 0);
        $maxImageBytes = max(1, app(SystemSettings::class)->attachmentImageMaxSizeMb()) * 1024 * 1024;
        $maxImageMb = (int) ceil($maxImageBytes / 1024 / 1024);
        if ($contentLength > $maxImageBytes) {
            return response()->json([
                'message' => sprintf('图片体积超限（最大 %dMB）。', $maxImageMb),
            ], 422);
        }

        $body = $response->body();
        $bytes = strlen($body);
        if ($bytes <= 0 || $bytes > $maxImageBytes) {
            return response()->json([
                'message' => sprintf('图片体积超限（最大 %dMB）。', $maxImageMb),
            ], 422);
        }

        $imageInfo = @getimagesizefromstring($body);
        $detectedMime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if ($imageInfo === false || ! in_array($detectedMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return response()->json([
                'message' => '图片内容校验失败，请上传有效的 jpeg、png、gif、webp 图片。',
            ], 422);
        }

        return response()->json([
            'data_url' => 'data:'.$detectedMime.';base64,'.base64_encode($body),
        ]);
    }

    /**
     * Sync attachment relations for a content entry.
     *
     * @param array<int, int|string> $attachmentIds
     */
    protected function syncAttachments(int $siteId, int $contentId, array $attachmentIds): void
    {
        (new ContentAttachmentRelationSync())->syncForContent($siteId, $contentId);
    }

    /**
     * @param  array<int, int>  $contentIds
     * @return array<int, int>
     */
    protected function attachmentIdsForContentIds(int $siteId, array $contentIds): array
    {
        if ($contentIds === []) {
            return [];
        }

        return DB::table('attachment_relations')
            ->join('attachments', 'attachments.id', '=', 'attachment_relations.attachment_id')
            ->where('attachments.site_id', $siteId)
            ->where('attachment_relations.relation_type', 'content')
            ->whereIn('attachment_relations.relation_id', $contentIds)
            ->distinct()
            ->pluck('attachment_relations.attachment_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $contentIds
     */
    protected function refreshAttachmentUsageForContentIds(int $siteId, array $contentIds): void
    {
        $attachmentIds = $this->attachmentIdsForContentIds($siteId, $contentIds);

        if ($attachmentIds === []) {
            return;
        }

        (new AttachmentUsageTracker())->rebuildForAttachmentIds($attachmentIds, $siteId);
    }

    /**
     * @return array{id_type:string,id_value:string,page:int}
     */
    protected function parseBilibiliVideoPageUrl(string $rawUrl): array
    {
        $url = parse_url(trim($rawUrl));

        abort_unless(is_array($url), 422, '请输入完整的哔哩哔哩视频网页地址。');

        $host = strtolower((string) ($url['host'] ?? ''));
        abort_unless((bool) preg_match('/(^|\.)(bilibili\.com)$/', $host), 422, '请使用完整的哔哩哔哩视频网页地址，不支持短链。');

        $path = (string) ($url['path'] ?? '');
        preg_match('/\/video\/(BV[0-9A-Za-z]+|av\d+)/i', $path, $matches);
        abort_unless(! empty($matches[1]), 422, '暂时只能解析哔哩哔哩视频详情页地址。');

        parse_str((string) ($url['query'] ?? ''), $query);
        $page = max((int) ($query['p'] ?? 1), 1);
        $id = (string) $matches[1];

        return [
            'id_type' => str_starts_with(strtoupper($id), 'BV') ? 'bvid' : 'aid',
            'id_value' => str_starts_with(strtoupper($id), 'BV') ? $id : preg_replace('/^av/i', '', $id),
            'page' => $page,
        ];
    }

    /**
     * @return array{0:int,1:string,2:array<int,array<string,mixed>>}
     */
    protected function fetchBilibiliVideoMeta(string $idType, string $idValue): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->get('https://api.bilibili.com/x/web-interface/view', [
                $idType => $idValue,
            ]);

        abort_unless($response->successful(), 422, '未能访问哔哩哔哩视频信息，请稍后重试。');

        $json = $response->json();
        $data = is_array($json['data'] ?? null) ? $json['data'] : null;

        abort_unless(($json['code'] ?? -1) === 0 && $data !== null, 422, '未能解析该哔哩哔哩视频，请确认链接可公开访问。');

        $aid = (int) ($data['aid'] ?? 0);
        $bvid = (string) ($data['bvid'] ?? '');
        $pages = array_values(array_filter(
            is_array($data['pages'] ?? null) ? $data['pages'] : [],
            fn ($item): bool => is_array($item) && ! empty($item['cid'])
        ));

        abort_unless($aid > 0 && $bvid !== '' && $pages !== [], 422, '未能提取哔哩哔哩播放器参数。');

        return [$aid, $bvid, $pages];
    }

    protected function siteRequiresArticleReview(int $siteId): bool
    {
        return DB::table('site_settings')
            ->where('site_id', $siteId)
            ->where('setting_key', 'content.article_requires_review')
            ->value('setting_value') === '1';
    }

    protected function canAuditContent(int $userId, int $siteId): bool
    {
        return in_array('content.audit', $this->sitePermissionCodes($userId, $siteId), true);
    }

    protected function resolveContentStatus(Request $request, int $siteId, string $type, string $requestedStatus): string
    {
        if ($type !== 'article' || $requestedStatus !== 'published') {
            return $requestedStatus;
        }

        if (! $this->siteRequiresArticleReview($siteId)) {
            return 'published';
        }

        return 'pending';
    }

    protected function isImportImageUrlAllowed(string $url): bool
    {
        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateOrReservedIp($host);
        }

        $hasResolvableAddress = false;
        $resolvedIps = gethostbynamel($host);
        if (is_array($resolvedIps) && $resolvedIps !== []) {
            $hasResolvableAddress = true;
            foreach ($resolvedIps as $ip) {
                if ($this->isPrivateOrReservedIp($ip)) {
                    return false;
                }
            }
        }

        return $hasResolvableAddress;
    }

    protected function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function resolvePublishedAt(mixed $submittedPublishedAt, string $resolvedStatus, mixed $currentPublishedAt = null): mixed
    {
        if (! empty($submittedPublishedAt)) {
            return $submittedPublishedAt;
        }

        if ($resolvedStatus === 'published') {
            return $currentPublishedAt ?: now();
        }

        return $currentPublishedAt;
    }

    protected function mapAuditStatus(string $status): string
    {
        return match ($status) {
            'published' => 'approved',
            'pending' => 'pending',
            'rejected' => 'rejected',
            default => 'draft',
        };
    }

    protected function recordReviewActionIfNeeded(
        int $siteId,
        int $contentId,
        Request $request,
        string $type,
        string $requestedStatus,
        string $resolvedStatus,
        ?object $existingContent = null,
    ): void {
        if ($type !== 'article' || $requestedStatus !== 'published') {
            return;
        }

        if ($resolvedStatus === 'pending') {
            $this->insertReviewRecord($siteId, $contentId, $request, 'submitted');

            return;
        }

    }

    protected function nextContentSortValue(int $siteId, string $type): int
    {
        $maxSort = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('type', $type)
            ->max('sort');

        return ((int) $maxSort) + 1;
    }

    protected function insertReviewRecord(
        int $siteId,
        int $contentId,
        Request $request,
        string $action,
        ?string $reason = null,
    ): void {
        DB::table('content_review_records')->insert([
            'content_id' => $contentId,
            'site_id' => $siteId,
            'reviewer_user_id' => $request->user()->id,
            'reviewer_name' => $request->user()->name,
            'reviewer_phone' => $request->user()->phone,
            'action' => $action,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
