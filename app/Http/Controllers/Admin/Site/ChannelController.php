<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\ThemeTemplateLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChannelController extends Controller
{
    /**
     * Display a listing of the channels for the current site.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');
        $keyword = trim((string) $request->query('keyword', ''));

        $allChannels = DB::table('channels')
            ->leftJoin('channels as parents', 'parents.id', '=', 'channels.parent_id')
            ->where('channels.site_id', $currentSite->id)
            ->orderBy('channels.sort')
            ->orderBy('channels.id')
            ->get([
                'channels.id',
                'channels.name',
                'channels.slug',
                'channels.type',
                'channels.sort',
                'channels.status',
                'channels.is_nav',
                'channels.parent_id',
                'channels.depth',
                'parents.name as parent_name',
            ]);

        $channels = $this->flattenChannels($allChannels)
            ->when($keyword !== '', function (Collection $items) use ($keyword) {
                return $items->filter(function (object $channel) use ($keyword): bool {
                    return str_contains(mb_strtolower($channel->name), mb_strtolower($keyword))
                        || str_contains(mb_strtolower($channel->slug), mb_strtolower($keyword));
                })->values();
            });

        return view('admin.site.channels.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'channels' => $channels,
            'channelTypes' => config('cms.channel_types'),
            'keyword' => $keyword,
        ]);
    }

    /**
     * Flatten channels into a tree-ordered collection with hierarchy metadata.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function flattenChannels(Collection $channels): Collection
    {
        $childrenByParent = $channels->groupBy(function (object $channel): int {
            return (int) ($channel->parent_id ?? 0);
        });

        $channelMap = $channels->keyBy(fn (object $channel) => (int) $channel->id);

        $walk = function (int $parentId, int $depth = 0, array $ancestorLines = [], array $ancestorNames = []) use (&$walk, $childrenByParent, $channelMap): array {
            $items = $childrenByParent->get($parentId, collect())->values();
            $flattened = [];

            foreach ($items as $index => $channel) {
                $isLast = $index === $items->count() - 1;
                $channel->tree_depth = $depth;
                $channel->tree_is_last = $isLast;
                $channel->tree_ancestors = $ancestorLines;
                $channel->tree_ancestor_names = $ancestorNames;
                $channel->tree_path = empty($ancestorNames) ? $channel->name : implode(' / ', array_merge($ancestorNames, [$channel->name]));
                $channel->tree_has_children = $childrenByParent->has((int) $channel->id);
                $flattened[] = $channel;

                $nextAncestorLines = $ancestorLines;
                $nextAncestorLines[] = ! $isLast;
                $nextAncestorNames = $ancestorNames;
                $nextAncestorNames[] = $channel->name;

                foreach ($walk((int) $channel->id, $depth + 1, $nextAncestorLines, $nextAncestorNames) as $child) {
                    $flattened[] = $child;
                }
            }

            return $flattened;
        };

        return collect($walk(0));
    }

    /**
     * Display the create form for a channel.
     */
    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $parentChannels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id', 'depth']);

        $channel = (object) [
            'id' => null,
            'name' => '',
            'slug' => '',
            'type' => 'list',
            'parent_id' => null,
            'list_template' => null,
            'detail_template' => null,
            'link_url' => null,
            'link_target' => '_self',
            'is_nav' => 1,
        ];

        return view('admin.site.channels.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'channel' => $channel,
            'parentChannels' => $this->parentChannelOptions($parentChannels),
            'channelTypes' => config('cms.channel_types'),
            'templateOptions' => $this->templateOptions($currentSite->id),
            'isCreate' => true,
        ]);
    }

    public function slugify(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $name = (string) $request->query('name', '');

        return response()->json([
            'slug' => $this->generateAvailableChannelSlug((int) $currentSite->id, $name),
        ]);
    }

    /**
     * Display the edit form for a channel.
     */
    public function edit(Request $request, string $channelId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $channel = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->where('id', $channelId)
            ->first();

        abort_unless($channel, 404);

        $parentChannels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id', 'depth']);

        return view('admin.site.channels.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'channel' => $channel,
            'parentChannels' => $this->parentChannelOptions($parentChannels, (int) $channelId),
            'channelTypes' => config('cms.channel_types'),
            'templateOptions' => $this->templateOptions($currentSite->id),
            'isCreate' => false,
        ]);
    }

    /**
     * Store a newly created channel.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $validated = $this->validateChannel($request);

        $channelId = DB::table('channels')->insertGetId([
            'site_id' => $currentSite->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'type' => $validated['type'],
            'path' => $validated['type'] === 'link' ? null : '/'.$validated['slug'],
            'depth' => $this->resolveDepth((int) $currentSite->id, $validated['parent_id'] ?? null),
            'sort' => $this->nextSortValue((int) $currentSite->id, $validated['parent_id'] ?? null),
            'status' => 1,
            'is_nav' => $request->boolean('is_nav'),
            'list_template' => $validated['type'] === 'list' ? ($validated['list_template'] ?? null) : null,
            'detail_template' => in_array($validated['type'], ['list', 'page'], true) ? ($validated['detail_template'] ?? null) : null,
            'link_url' => $validated['type'] === 'link' ? ($validated['link_url'] ?? null) : null,
            'link_target' => $validated['type'] === 'link' ? ($validated['link_target'] ?? '_self') : '_self',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logOperation(
            'site',
            'channel',
            'create',
            $currentSite->id,
            $request->user()->id,
            'channel',
            $channelId,
            ['name' => $validated['name'], 'slug' => $validated['slug']],
            $request,
        );

        return redirect()
            ->route('admin.channels.index')
            ->with('status', '栏目已创建。');
    }

    /**
     * Update an existing channel.
     */
    public function update(Request $request, string $channelId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $channel = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->where('id', $channelId)
            ->first();

        abort_unless($channel, 404);

        $validated = $this->validateChannel($request);
        $validated['slug'] = $channel->slug;
        $validated['type'] = $channel->type;

        DB::table('channels')
            ->where('id', $channelId)
            ->update([
                'parent_id' => $validated['parent_id'] ?? null,
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'type' => $validated['type'],
                'path' => $validated['type'] === 'link' ? null : '/'.$validated['slug'],
                'depth' => $this->resolveDepth((int) $currentSite->id, $validated['parent_id'] ?? null),
                'is_nav' => $request->boolean('is_nav'),
                'list_template' => $validated['type'] === 'list' ? ($validated['list_template'] ?? null) : null,
                'detail_template' => in_array($validated['type'], ['list', 'page'], true) ? ($validated['detail_template'] ?? null) : null,
                'link_url' => $validated['type'] === 'link' ? ($validated['link_url'] ?? null) : null,
                'link_target' => $validated['type'] === 'link' ? ($validated['link_target'] ?? '_self') : '_self',
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'channel',
            'update',
            $currentSite->id,
            $request->user()->id,
            'channel',
            (int) $channelId,
            ['name' => $validated['name'], 'slug' => $validated['slug']],
            $request,
        );

        return redirect()
            ->route('admin.channels.index')
            ->with('status', '栏目已更新。');
    }

    /**
     * Delete an existing channel.
     */
    public function destroy(Request $request, string $channelId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $channel = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->where('id', $channelId)
            ->first();

        abort_unless($channel, 404);

        $hasChildren = DB::table('channels')
            ->where('parent_id', $channelId)
            ->exists();

        $hasContents = DB::table('contents')
            ->where('channel_id', $channelId)
            ->exists();

        if ($hasChildren || $hasContents) {
            return redirect()
                ->route('admin.channels.index')
                ->with('status', '该栏目下还有子栏目或内容，暂不能删除。');
        }

        DB::table('channels')->where('id', $channelId)->delete();

        $this->logOperation(
            'site',
            'channel',
            'delete',
            $currentSite->id,
            $request->user()->id,
            'channel',
            (int) $channelId,
            ['name' => $channel->name],
            $request,
        );

        return redirect()
            ->route('admin.channels.index')
            ->with('status', '栏目已删除。');
    }

    /**
     * Batch process channels.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $channels = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->whereIn('id', $validated['ids'])
            ->get(['id', 'name']);

        $deleted = 0;
        $skipped = 0;

        foreach ($channels as $channel) {
            $hasChildren = DB::table('channels')->where('parent_id', $channel->id)->exists();
            $hasContents = DB::table('contents')
                ->where('channel_id', $channel->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasChildren || $hasContents) {
                $skipped++;
                continue;
            }

            DB::table('channels')->where('id', $channel->id)->delete();
            $deleted++;
        }

        $this->logOperation(
            'site',
            'channel',
            'bulk_delete',
            $currentSite->id,
            $request->user()->id,
            'channel',
            null,
            ['ids' => $validated['ids'], 'deleted' => $deleted, 'skipped' => $skipped],
            $request,
        );

        return redirect()
            ->route('admin.channels.index')
            ->with('status', "批量处理完成，删除 {$deleted} 个栏目，跳过 {$skipped} 个。");
    }

    /**
     * Persist a sibling reorder operation.
     */
    public function reorder(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'channel.manage');

        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')->where(fn ($query) => $query->where('site_id', $currentSite->id)),
            ],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $orderedIds = array_map('intval', $validated['ordered_ids']);

        $query = DB::table('channels')
            ->where('site_id', $currentSite->id)
            ->whereIn('id', $orderedIds);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $existingIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($existingIds);
        $normalizedOrderedIds = $orderedIds;
        sort($normalizedOrderedIds);

        if ($existingIds !== $normalizedOrderedIds) {
            return response()->json([
                'message' => '排序保存失败，栏目分组已发生变化，请刷新页面后重试。',
            ], 422);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $channelId) {
                DB::table('channels')
                    ->where('id', $channelId)
                    ->update([
                        'sort' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->logOperation(
            'site',
            'channel',
            'reorder',
            $currentSite->id,
            $request->user()->id,
            'channel',
            $parentId,
            [
                'parent_id' => $parentId,
                'ordered_ids' => $orderedIds,
            ],
            $request,
        );

        return response()->json([
            'message' => '栏目排序已保存。',
        ]);
    }

    /**
     * Validate a channel payload.
     *
     * @return array<string, mixed>
     */
    protected function validateChannel(Request $request): array
    {
        $currentSite = $this->currentSite($request);
        $channelId = (int) $request->route('channel', 0);
        $templateOptions = $this->templateOptions($currentSite->id);

        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'slug' => trim((string) $request->input('slug', '')),
            'link_url' => trim((string) $request->input('link_url', '')),
        ]);

        $descendantIds = $channelId > 0 ? $this->descendantChannelIds((int) $currentSite->id, $channelId) : [];

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{Han}A-Za-z0-9_\-\s·()（）]+$/u'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('channels', 'slug')
                    ->where(fn ($query) => $query->where('site_id', $currentSite->id))
                    ->ignore($channelId),
            ],
            'type' => ['required', 'string', 'in:list,page,link'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('channels', 'id')->where(fn ($query) => $query->where('site_id', $currentSite->id)),
                function (string $attribute, mixed $value, \Closure $fail) use ($currentSite, $channelId, $descendantIds) {
                    if (empty($value)) {
                        return;
                    }

                    $parentId = (int) $value;

                    if ($channelId > 0 && $parentId === $channelId) {
                        $fail('上级栏目不能选择当前栏目本身。');

                        return;
                    }

                    if ($channelId > 0 && in_array($parentId, $descendantIds, true)) {
                        $fail('上级栏目不能选择当前栏目的下级栏目。');

                        return;
                    }

                    $parentDepth = $this->resolveActualDepth((int) $currentSite->id, $parentId);

                    if ($parentDepth >= 2) {
                        $fail('最多只支持三级栏目，不能继续向下创建子栏目。');
                    }
                },
            ],
            'is_nav' => ['nullable', 'boolean'],
            'list_template' => [
                'nullable',
                'string',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $templateOptions) {
                    $template = trim((string) $value);
                    if ($template === '') {
                        return;
                    }

                    if ((string) $request->input('type') !== 'list') {
                        $fail('当前栏目类型不支持列表模板。');

                        return;
                    }

                    if (! array_key_exists($template, $templateOptions['list'])) {
                        $fail('请选择当前主题可用的列表模板。');
                    }
                },
            ],
            'detail_template' => [
                'nullable',
                'string',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $templateOptions) {
                    $template = trim((string) $value);
                    if ($template === '') {
                        return;
                    }

                    $type = (string) $request->input('type');

                    if ($type === 'list') {
                        if (! array_key_exists($template, $templateOptions['detail'])) {
                            $fail('列表栏目只能选择当前主题可用的详情模板。');
                        }

                        return;
                    }

                    if ($type === 'page') {
                        if (! array_key_exists($template, $templateOptions['page'])) {
                            $fail('单页栏目只能选择当前主题可用的单页模板。');
                        }

                        return;
                    }

                    $fail('当前栏目类型不支持详情模板。');
                },
            ],
            'link_url' => ['nullable', 'url:http,https', 'max:500', 'required_if:type,link'],
            'link_target' => ['nullable', 'string', 'in:_self,_blank'],
        ], [
            'name.min' => '栏目名称不能少于2个字符。',
            'name.max' => '栏目名称不能超过100个字符。',
            'name.regex' => '栏目名称只能使用中文、英文、数字、空格、下划线、中划线、圆括号或间隔点。',
            'slug.min' => '栏目别名不能少于3个字符。',
            'slug.regex' => '栏目别名只能由英文、数字、下划线和短横线组成。',
            'slug.max' => '栏目别名不能超过20个字符。',
            'slug.unique' => '当前站点已存在相同的栏目别名，请调整后再提交。',
            'parent_id.exists' => '请选择当前站点内有效的上级栏目。',
            'link_url.required_if' => '外链栏目必须填写外链地址。',
            'link_url.url' => '外链地址格式不正确，请输入完整的 http:// 或 https:// 地址。',
        ], [
            'name' => '栏目名称',
            'slug' => '栏目别名',
            'type' => '栏目类型',
            'parent_id' => '上级栏目',
            'is_nav' => '导航显示',
            'list_template' => '列表模板',
            'detail_template' => '详情模板',
            'link_url' => '外链地址',
            'link_target' => '打开方式',
        ]);
    }

    /**
     * @return array<int>
     */
    protected function descendantChannelIds(int $siteId, int $channelId): array
    {
        $childrenByParent = DB::table('channels')
            ->where('site_id', $siteId)
            ->get(['id', 'parent_id'])
            ->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));

        $descendants = [];
        $stack = [$channelId];

        while ($stack !== []) {
            $currentId = array_pop($stack);

            foreach ($childrenByParent->get((int) $currentId, collect()) as $child) {
                $childId = (int) $child->id;
                $descendants[] = $childId;
                $stack[] = $childId;
            }
        }

        return array_values(array_unique($descendants));
    }

    protected function generateChannelSlug(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return '';
        }

        $transliterated = function_exists('transliterator_transliterate')
            ? transliterator_transliterate('Han-Latin; Latin-ASCII; Lower()', $normalized)
            : null;

        $slug = Str::slug((string) ($transliterated ?: $normalized), '-');
        $slug = str_replace('-', '', $slug);

        return substr($slug, 0, 20);
    }

    protected function generateAvailableChannelSlug(int $siteId, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = $this->generateChannelSlug($name);

        if ($baseSlug === '') {
            $baseSlug = 'channel';
        }

        if (mb_strlen($baseSlug) < 3) {
            $baseSlug = str_pad($baseSlug, 3, 'a');
        }

        if (!$this->channelSlugExists($siteId, $baseSlug, $ignoreId)) {
            return $baseSlug;
        }

        do {
            $suffix = substr(strtolower(bin2hex(random_bytes(3))), 0, 4);
            $candidate = substr($baseSlug, 0, 20 - strlen($suffix) - 1) . '_' . $suffix;
        } while ($this->channelSlugExists($siteId, $candidate, $ignoreId));

        return $candidate;
    }

    protected function channelSlugExists(int $siteId, string $slug, ?int $ignoreId = null): bool
    {
        $query = DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Build parent channel options in tree order.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $channels
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function parentChannelOptions(Collection $channels, ?int $excludeId = null): Collection
    {
        $descendantIds = collect();

        if ($excludeId !== null) {
            $childrenByParent = $channels->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));
            $collectDescendants = function (int $parentId) use (&$collectDescendants, $childrenByParent, &$descendantIds): void {
                foreach ($childrenByParent->get($parentId, collect()) as $child) {
                    $descendantIds->push((int) $child->id);
                    $collectDescendants((int) $child->id);
                }
            };

            $collectDescendants($excludeId);
        }

        return $this->flattenChannels(
            $channels->reject(function (object $channel) use ($excludeId, $descendantIds): bool {
                return (int) $channel->id === (int) $excludeId
                    || $descendantIds->contains((int) $channel->id);
            })->values()
        )->filter(function (object $channel): bool {
            return (int) ($channel->tree_depth ?? 0) < 2;
        })->map(function (object $channel) {
            $channel->option_label = $channel->name;
            return $channel;
        })->values();
    }

    protected function resolveDepth(int $siteId, mixed $parentId): int
    {
        if (empty($parentId)) {
            return 0;
        }

        return $this->resolveActualDepth($siteId, (int) $parentId) + 1;
    }

    protected function resolveActualDepth(int $siteId, int $channelId): int
    {
        $depth = 0;
        $visited = [];
        $currentId = $channelId;

        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;

            $parentId = DB::table('channels')
                ->where('site_id', $siteId)
                ->where('id', $currentId)
                ->value('parent_id');

            if (empty($parentId)) {
                break;
            }

            $depth++;
            $currentId = (int) $parentId;
        }

        return $depth;
    }

    protected function nextSortValue(int $siteId, mixed $parentId): int
    {
        $query = DB::table('channels')
            ->where('site_id', $siteId);

        if (empty($parentId)) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $maxSort = $query->max('sort');

        return ((int) $maxSort) + 1;
    }

    /**
     * Available theme template bindings for the current site.
     *
     * @return array<int, string>
     */
    protected function templateOptions(int $siteId): array
    {
        $themeCode = $this->siteThemeCode($siteId);

        $options = [
            'list' => [],
            'detail' => [],
            'page' => [],
        ];

        if ($themeCode === '') {
            return $options;
        }

        foreach (ThemeTemplateLocator::availableTemplatesForSite($siteId, $themeCode) as $templateItem) {
            $template = $templateItem['file'];
            $label = trim(($templateItem['label'] ?? '').' '.$template.'.tpl');

            if ($template === 'list' || str_starts_with($template, 'list-')) {
                $options['list'][$template] = $label;
            }

            if ($template === 'detail' || str_starts_with($template, 'detail-')) {
                $options['detail'][$template] = $label;
            }

            if ($template === 'page' || str_starts_with($template, 'page-')) {
                $options['page'][$template] = $label;
            }
        }

        foreach ($options as &$group) {
            ksort($group);
        }

        return $options;
    }
}
