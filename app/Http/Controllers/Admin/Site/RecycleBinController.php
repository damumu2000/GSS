<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\AttachmentUsageTracker;
use App\Support\ContentAttachmentRelationSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RecycleBinController extends Controller
{
    /**
     * Display deleted content entries.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');
        $keyword = trim((string) $request->query('keyword', ''));
        $type = trim((string) $request->query('type', ''));

        $deletedContents = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $currentSite->id)
            ->whereNotNull('contents.deleted_at')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where('contents.title', 'like', '%'.$keyword.'%');
            })
            ->when($type !== '', function ($query) use ($type): void {
                $query->where('contents.type', $type);
            })
            ->orderByDesc('contents.deleted_at')
            ->paginate(10, [
                'contents.id',
                'contents.type',
                'contents.title',
                'contents.deleted_at',
                'channels.name as channel_name',
            ])
            ->withQueryString();

        return view('admin.site.recycle-bin.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'deletedContents' => $deletedContents,
            'keyword' => $keyword,
            'selectedType' => $type,
        ]);
    }

    /**
     * Restore a deleted content entry.
     */
    public function restore(Request $request, string $contentId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $content = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('id', $contentId)
            ->whereNotNull('deleted_at')
            ->first();

        abort_unless($content, 404);

        DB::table('contents')
            ->where('id', $contentId)
            ->update([
                'deleted_at' => null,
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        (new ContentAttachmentRelationSync())->syncForContent($currentSite->id, (int) $contentId);

        $this->logOperation(
            'site',
            'recycle_bin',
            'restore',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $contentId,
            ['title' => $content->title, 'type' => $content->type],
            $request,
        );

        return redirect()
            ->route('admin.recycle-bin.index')
            ->with('status', '内容已恢复。');
    }

    /**
     * Permanently delete a recycled content entry.
     */
    public function destroy(Request $request, string $contentId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $content = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->where('id', $contentId)
            ->whereNotNull('deleted_at')
            ->first();

        abort_unless($content, 404);

        $affectedAttachmentIds = $this->attachmentIdsForContentIds($currentSite->id, [(int) $contentId]);

        DB::transaction(function () use ($contentId): void {
            DB::table('attachment_relations')
                ->where('relation_type', 'content')
                ->where('relation_id', $contentId)
                ->delete();
            DB::table('content_revisions')->where('content_id', $contentId)->delete();
            DB::table('contents')->where('id', $contentId)->delete();
        });

        if ($affectedAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($affectedAttachmentIds, $currentSite->id);
        }

        $this->logOperation(
            'site',
            'recycle_bin',
            'force_delete',
            $currentSite->id,
            $request->user()->id,
            'content',
            (int) $contentId,
            ['title' => $content->title, 'type' => $content->type],
            $request,
        );

        return redirect()
            ->route('admin.recycle-bin.index')
            ->with('status', '内容已彻底删除。');
    }

    /**
     * Batch process recycle bin entries.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:restore,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $contents = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->whereNotNull('deleted_at')
            ->whereIn('id', $validated['ids'])
            ->get(['id', 'title', 'type']);

        if ($validated['action'] === 'restore') {
            DB::table('contents')
                ->whereIn('id', $contents->pluck('id'))
                ->update([
                    'deleted_at' => null,
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            foreach ($contents->pluck('id') as $restoredContentId) {
                (new ContentAttachmentRelationSync())->syncForContent($currentSite->id, (int) $restoredContentId);
            }

            $message = '批量恢复已完成。';
        } else {
            $affectedAttachmentIds = $this->attachmentIdsForContentIds(
                $currentSite->id,
                $contents->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );

            DB::transaction(function () use ($contents): void {
                DB::table('attachment_relations')
                    ->where('relation_type', 'content')
                    ->whereIn('relation_id', $contents->pluck('id'))
                    ->delete();
                DB::table('content_revisions')->whereIn('content_id', $contents->pluck('id'))->delete();
                DB::table('contents')->whereIn('id', $contents->pluck('id'))->delete();
            });

            if ($affectedAttachmentIds !== []) {
                (new AttachmentUsageTracker())->rebuildForAttachmentIds($affectedAttachmentIds, $currentSite->id);
            }

            $message = '批量彻底删除已完成。';
        }

        $this->logOperation(
            'site',
            'recycle_bin',
            'bulk_'.$validated['action'],
            $currentSite->id,
            $request->user()->id,
            'content',
            null,
            ['ids' => $contents->pluck('id')->all()],
            $request,
        );

        return redirect()
            ->route('admin.recycle-bin.index')
            ->with('status', $message);
    }

    /**
     * Permanently clear the current site's recycle bin.
     */
    public function empty(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'content.manage');

        $contents = DB::table('contents')
            ->where('site_id', $currentSite->id)
            ->whereNotNull('deleted_at')
            ->get(['id', 'title', 'type']);

        if ($contents->isEmpty()) {
            return redirect()
                ->route('admin.recycle-bin.index')
                ->with('status', '回收站已为空。');
        }

        $contentIds = $contents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $affectedAttachmentIds = $this->attachmentIdsForContentIds($currentSite->id, $contentIds);

        DB::transaction(function () use ($contentIds): void {
            DB::table('attachment_relations')
                ->where('relation_type', 'content')
                ->whereIn('relation_id', $contentIds)
                ->delete();
            DB::table('content_revisions')
                ->whereIn('content_id', $contentIds)
                ->delete();
            DB::table('contents')
                ->whereIn('id', $contentIds)
                ->delete();
        });

        if ($affectedAttachmentIds !== []) {
            (new AttachmentUsageTracker())->rebuildForAttachmentIds($affectedAttachmentIds, $currentSite->id);
        }

        $this->logOperation(
            'site',
            'recycle_bin',
            'empty',
            $currentSite->id,
            $request->user()->id,
            'content',
            null,
            ['ids' => $contentIds],
            $request,
        );

        return redirect()
            ->route('admin.recycle-bin.index')
            ->with('status', '回收站已清空。');
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
}
