<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ArticleReviewController extends Controller
{
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.audit');

        $keyword = trim((string) $request->query('keyword', ''));
        $channelId = (string) $request->query('channel_id', '');
        $status = (string) $request->query('status', 'pending');
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $reviewableStatuses = ['pending', 'rejected'];
        if (! in_array($status, $reviewableStatuses, true)) {
            $status = 'pending';
        }

        $contents = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->leftJoin('users as submitters', 'submitters.id', '=', 'contents.updated_by')
            ->where('contents.site_id', $currentSite->id)
            ->where('contents.type', 'article')
            ->whereNull('contents.deleted_at')
            ->when($manageableChannelIds !== [], function ($query) use ($manageableChannelIds): void {
                $query->where(function ($subQuery) use ($manageableChannelIds): void {
                    $subQuery->whereNull('contents.channel_id')
                        ->orWhereIn('contents.channel_id', $manageableChannelIds);
                });
            })
            ->where('contents.status', $status)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('contents.title', 'like', '%'.$keyword.'%')
                        ->orWhere('contents.summary', 'like', '%'.$keyword.'%');
                });
            })
            ->when($channelId !== '', function ($query) use ($channelId): void {
                $query->where('contents.channel_id', $channelId);
            })
            ->orderByDesc('contents.updated_at')
            ->paginate(10, [
                'contents.id',
                'contents.title',
                'contents.summary',
                'contents.status',
                'contents.updated_at',
                'channels.name as channel_name',
                'submitters.name as submitter_name',
                DB::raw("(select count(*) from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected') as reject_count"),
                DB::raw("(select reason from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected' order by content_review_records.created_at desc limit 1) as latest_reject_reason"),
                DB::raw("(select reviewer_name from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected' order by content_review_records.created_at desc limit 1) as latest_reviewer_name"),
                DB::raw("(select reviewer_phone from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected' order by content_review_records.created_at desc limit 1) as latest_reviewer_phone"),
                DB::raw("(select created_at from content_review_records where content_review_records.content_id = contents.id and content_review_records.action = 'rejected' order by content_review_records.created_at desc limit 1) as latest_rejected_at"),
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

        $allChannels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'type', 'parent_id']);

        $articleChannels = $allChannels
            ->filter(fn (object $channel): bool => $channel->type === 'list')
            ->values();

        $selectableIds = $this->typedSelectableChannelIds($articleChannels, $manageableChannelIds, ['list']);

        $channels = $selectableIds === []
            ? collect()
            : $this->flattenSelectableChannels(
                $this->visibleSelectableChannels($articleChannels, $selectableIds),
                $selectableIds
            );

        $reviewSummary = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->when($manageableChannelIds !== [], function ($query) use ($manageableChannelIds): void {
                $query->where(function ($subQuery) use ($manageableChannelIds): void {
                    $subQuery->whereNull('channel_id')
                        ->orWhereIn('channel_id', $manageableChannelIds);
                });
            })
            ->whereIn('status', ['pending', 'rejected'])
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->first();

        return view('admin.site.article-reviews.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'contents' => $contents,
            'channels' => $channels,
            'statuses' => [
                'pending' => '待审核',
                'rejected' => '已驳回',
            ],
            'selectedStatus' => $status,
            'selectedChannelId' => $channelId,
            'keyword' => $keyword,
            'reviewSummary' => (object) [
                'pending_count' => (int) ($reviewSummary->pending_count ?? 0),
                'rejected_count' => (int) ($reviewSummary->rejected_count ?? 0),
            ],
        ]);
    }

    protected function visibleSelectableChannels($channels, array $selectableIds)
    {
        if ($selectableIds === []) {
            return collect();
        }

        $channelMap = $channels->keyBy(fn (object $channel) => (int) $channel->id);
        $visibleIds = collect($selectableIds)->values();

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

    protected function flattenSelectableChannels($channels, array $selectableIds)
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

    protected function typedSelectableChannelIds($channels, array $manageableChannelIds, array $allowedTypes): array
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

    public function approve(Request $request, string $contentId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.audit');

        $content = $this->resolveReviewableArticle($request, $currentSite->id, $contentId, ['pending']);

        DB::transaction(function () use ($currentSite, $request, $content): void {
            DB::table('contents')
                ->where('id', $content->id)
                ->update([
                    'status' => 'published',
                    'audit_status' => 'approved',
                    'published_at' => $content->published_at ?: now(),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            $this->insertReviewRecord($currentSite->id, (int) $content->id, $request, 'approved');
        });

        $this->logOperation(
            'site',
            'content',
            'approve_content',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $content->id,
            ['title' => $content->title],
            $request,
        );

        return $this->redirectToReviewList($request, '文章已审核通过并发布。');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.audit');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [], [
            'ids' => '待审核文章',
            'ids.*' => '待审核文章',
        ]);

        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $currentSite->id);

        $contents = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('type', 'article')
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->whereIn('id', $validated['ids'])
            ->when($manageableChannelIds !== [], function ($query) use ($manageableChannelIds): void {
                $query->where(function ($subQuery) use ($manageableChannelIds): void {
                    $subQuery->whereNull('channel_id')
                        ->orWhereIn('channel_id', $manageableChannelIds);
                });
            })
            ->get(['id', 'title', 'published_at']);

        if ($contents->isEmpty()) {
            return $this->redirectToReviewList($request, '未找到可批量审核通过的待审核文章。');
        }

        DB::transaction(function () use ($currentSite, $request, $contents): void {
            DB::table('contents')
                ->whereIn('id', $contents->pluck('id')->all())
                ->update([
                    'status' => 'published',
                    'audit_status' => 'approved',
                    'published_at' => now(),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            foreach ($contents as $content) {
                $this->insertReviewRecord($currentSite->id, (int) $content->id, $request, 'approved');
            }
        });

        $this->logOperation(
            'site',
            'content',
            'bulk_approve_content',
            $currentSite->id,
            $request->user()->id,
            'content',
            null,
            ['ids' => $contents->pluck('id')->all(), 'count' => $contents->count()],
            $request,
        );

        return $this->redirectToReviewList($request, '批量审核通过已完成。');
    }

    public function reject(Request $request, string $contentId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.audit');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [], [
            'reason' => '驳回原因',
        ]);

        $content = $this->resolveReviewableArticle($request, $currentSite->id, $contentId, ['pending']);

        DB::transaction(function () use ($currentSite, $request, $content, $validated): void {
            DB::table('contents')
                ->where('id', $content->id)
                ->update([
                    'status' => 'rejected',
                    'audit_status' => 'rejected',
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            $this->insertReviewRecord($currentSite->id, (int) $content->id, $request, 'rejected', $validated['reason']);
        });

        $this->logOperation(
            'site',
            'content',
            'reject_content',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $content->id,
            ['title' => $content->title, 'reason' => $validated['reason']],
            $request,
        );

        return $this->redirectToReviewList($request, '文章已驳回。', ['status' => 'pending']);
    }

    protected function resolveReviewableArticle(Request $request, int $siteId, string $contentId, array $statuses): object
    {
        $manageableChannelIds = $this->manageableChannelIds($request->user()->id, $siteId);

        $content = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('type', 'article')
            ->where('id', $contentId)
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses)
            ->when($manageableChannelIds !== [], function ($query) use ($manageableChannelIds): void {
                $query->where(function ($subQuery) use ($manageableChannelIds): void {
                    $subQuery->whereNull('channel_id')
                        ->orWhereIn('channel_id', $manageableChannelIds);
                });
            })
            ->first(['id', 'title', 'published_at']);

        abort_unless($content, 404);

        return $content;
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

    protected function redirectToReviewList(Request $request, string $message, array $fallbackQuery = []): RedirectResponse
    {
        $fallback = route('admin.article-reviews.index', $fallbackQuery);
        $returnUrl = (string) $request->input('return_url', '');

        if ($returnUrl !== '' && $this->isSafeReviewReturnUrl($returnUrl, $fallback)) {
            return redirect()->to($returnUrl)->with('status', $message);
        }

        return redirect()->to($fallback)->with('status', $message);
    }

    protected function isSafeReviewReturnUrl(string $returnUrl, string $fallback): bool
    {
        if (Str::startsWith($returnUrl, '//')) {
            return false;
        }

        $fallbackPath = parse_url($fallback, PHP_URL_PATH) ?: '';
        $returnPath = parse_url($returnUrl, PHP_URL_PATH) ?: '';

        if ($returnPath === ''
            || ($returnPath !== $fallbackPath && ! Str::startsWith($returnPath, $fallbackPath.'/'))) {
            return false;
        }

        $returnHost = parse_url($returnUrl, PHP_URL_HOST);

        return $returnHost === null || $returnHost === parse_url($fallback, PHP_URL_HOST);
    }
}
