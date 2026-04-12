<?php

namespace App\Support;

use App\Modules\Guestbook\Support\GuestbookModule;
use App\Modules\Guestbook\Support\GuestbookSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemeTags
{
    protected ?string $currentPageScope = null;

    protected ?int $currentChannelId = null;

    protected ?string $currentTemplateName = null;

    protected ?Collection $siteChannelsCache = null;

    protected ?Collection $siteChannelsByIdCache = null;

    protected ?Collection $siteChannelsBySlugCache = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $guestbookStateCache = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $guestbookStatsCache = null;

    public function __construct(
        protected object $site,
        protected Collection $settings,
        protected Collection $channels,
    ) {
    }

    public function withContext(?string $pageScope = null, ?int $channelId = null, ?string $templateName = null): static
    {
        $this->currentPageScope = $pageScope;
        $this->currentChannelId = $channelId;
        $this->currentTemplateName = $templateName;

        return $this;
    }

    protected function site(string $key, mixed $default = ''): mixed
    {
        $map = [
            'id' => $this->site->id ?? null,
            'name' => $this->site->name ?? '',
            'logo' => $this->site->logo ?? '',
            'favicon' => $this->site->favicon ?? '',
            'contact_phone' => $this->site->contact_phone ?? '',
            'contact_email' => $this->site->contact_email ?? '',
            'address' => $this->site->address ?? '',
            'seo_title' => $this->site->seo_title ?? '',
            'seo_keywords' => $this->site->seo_keywords ?? '',
            'seo_description' => $this->site->seo_description ?? '',
            'remark' => $this->site->remark ?? '',
            'icp_no' => $this->settings->get('site.filing_number', ''),
            'home_url' => route('site.home', ['site' => $this->site->site_key]),
        ];

        return $map[$key] ?? $default;
    }

    public function siteValue(array $options = []): string
    {
        $key = trim((string) ($options['key'] ?? ''));
        $default = (string) ($options['default'] ?? '');

        if ($key === '') {
            return $default;
        }

        return $this->scalarToString($this->site($key, $default), $default);
    }

    public function nav(int $limit = 8): Collection
    {
        return $this->channels
            ->take($limit)
            ->map(fn ($channel) => $this->mapChannel($channel))
            ->values();
    }

    public function current(): array
    {
        $channel = $this->resolveCurrentChannel();

        return [
            'page' => [
                'scope' => $this->currentPageScope ?? '',
                'type' => $this->resolveCurrentPageType(),
                'template_name' => $this->currentTemplateName ?? '',
            ],
            'channel' => $channel ? $this->mapChannel($channel) : $this->emptyChannelPayload(),
        ];
    }

    public function channels(array $options = []): Collection
    {
        $channels = $this->allSiteChannels();

        if (array_key_exists('status', $options)) {
            $channels = $channels->filter(fn (object $channel): bool => (int) ($channel->status ?? 0) === (int) $options['status']);
        }

        if (array_key_exists('parent_id', $options)) {
            $parentId = $options['parent_id'];
            $channels = $channels->filter(function (object $channel) use ($parentId): bool {
                if ($parentId === null || $parentId === '') {
                    return $channel->parent_id === null;
                }

                return (int) ($channel->parent_id ?? 0) === (int) $parentId;
            });
        }

        if (isset($options['is_nav'])) {
            $channels = $channels->filter(fn (object $channel): bool => (int) ($channel->is_nav ?? 0) === (int) $options['is_nav']);
        }

        if (! empty($options['type'])) {
            $channels = $channels->filter(fn (object $channel): bool => (string) ($channel->type ?? '') === (string) $options['type']);
        }

        if (! empty($options['slug'])) {
            $channels = $channels->filter(fn (object $channel): bool => (string) ($channel->slug ?? '') === (string) $options['slug']);
        }

        $includeIds = $this->normalizeIdList($options['include_ids'] ?? null);
        if ($includeIds !== []) {
            $channels = $channels->filter(fn (object $channel): bool => in_array((int) $channel->id, $includeIds, true));
        }

        $excludeIds = $this->normalizeIdList($options['exclude_ids'] ?? null);
        if ($excludeIds !== []) {
            $channels = $channels->reject(fn (object $channel): bool => in_array((int) $channel->id, $excludeIds, true));
        }

        if (! empty($options['keyword'])) {
            $keyword = trim((string) $options['keyword']);
            $channels = $channels->filter(fn (object $channel): bool => str_contains((string) ($channel->name ?? ''), $keyword));
        }

        if (! empty($options['random'])) {
            $channels = $channels->shuffle();
        } else {
            $channels = $channels->sortBy([
                ['sort', 'asc'],
                ['id', 'asc'],
            ]);
        }

        if (! empty($options['limit'])) {
            $channels = $channels->take((int) $options['limit']);
        }

        $channels = $channels
            ->map(fn ($channel) => $this->mapChannel($channel))
            ->values();

        $fields = $this->normalizeFieldList($options['fields'] ?? null);

        if ($fields === []) {
            return $channels;
        }

        $fields = array_values(array_unique(array_merge(['id'], $fields)));

        return $channels
            ->map(function (array $channel) use ($fields): array {
                $payload = [];

                foreach ($fields as $field) {
                    $payload[$field] = $channel[$field] ?? null;
                }

                return $payload;
            })
            ->values();
    }

    public function channel(array $options = []): ?array
    {
        $channelId = $this->extractChannelIdentifier($options, ['id', 'channel_id']);

        if ($channelId !== null) {
            $channel = $this->siteChannelsById()->get($channelId);
        } elseif (! empty($options['slug'])) {
            $channel = $this->siteChannelsBySlug()->get((string) $options['slug']);
        } elseif ($this->currentChannelId !== null) {
            $channel = $this->siteChannelsById()->get($this->currentChannelId);
        } else {
            return null;
        }

        return $channel ? $this->mapChannel($channel) : null;
    }

    public function children(array $options = []): Collection
    {
        $parentId = $this->resolveChannelIdFromOptions($options);

        if ($parentId === null) {
            return collect();
        }

        return $this->allSiteChannels()
            ->filter(fn (object $channel): bool => (int) ($channel->parent_id ?? 0) === $parentId)
            ->sortBy([
                ['sort', 'asc'],
                ['id', 'asc'],
            ])
            ->take(max(1, (int) ($options['limit'] ?? 100)))
            ->map(fn ($channel) => $this->mapChannel($channel))
            ->values();
    }

    public function parent(array $options = []): ?array
    {
        $channel = $this->resolveChannelFromOptions($options);

        if (! $channel || empty($channel->parent_id)) {
            return null;
        }

        $parent = $this->siteChannelsById()->get((int) $channel->parent_id);

        return $parent ? $this->mapChannel($parent) : null;
    }

    public function siblings(array $options = []): Collection
    {
        $channel = $this->resolveChannelFromOptions($options);

        if (! $channel) {
            return collect();
        }

        $siblings = $this->allSiteChannels()
            ->reject(fn (object $sibling): bool => (int) $sibling->id === (int) $channel->id)
            ->filter(function (object $sibling) use ($channel): bool {
                if ($channel->parent_id === null) {
                    return $sibling->parent_id === null;
                }

                return (int) ($sibling->parent_id ?? 0) === (int) $channel->parent_id;
            })
            ->sortBy([
                ['sort', 'asc'],
                ['id', 'asc'],
            ]);

        if (! empty($options['limit'])) {
            $siblings = $siblings->take(max(1, (int) $options['limit']));
        }

        return $siblings
            ->map(fn ($sibling) => $this->mapChannel($sibling))
            ->values();
    }

    public function breadcrumb(array $options = []): Collection
    {
        $channelId = $this->resolveChannelIdFromOptions($options);

        if ($channelId === null) {
            return collect();
        }

        $channels = $this->siteChannelsById();

        $trail = [];
        $cursor = $channels->get($channelId);
        $visited = [];

        while ($cursor && ! in_array((int) $cursor->id, $visited, true)) {
            $trail[] = $this->mapChannel($cursor);
            $visited[] = (int) $cursor->id;
            $parentId = (int) ($cursor->parent_id ?? 0);
            $cursor = $parentId > 0 ? $channels->get($parentId) : null;
        }

        return collect(array_reverse($trail))->values();
    }

    public function content(array $options = []): ?array
    {
        $contentId = isset($options['id']) && $options['id'] !== ''
            ? (int) $options['id']
            : null;

        if ($contentId === null || $contentId <= 0) {
            return null;
        }

        $content = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $this->site->id)
            ->where('contents.id', $contentId)
            ->whereNull('contents.deleted_at')
            ->first([
                'contents.*',
                'channels.name as channel_name',
                'channels.slug as channel_slug',
            ]);

        return $content ? $this->mapContent($content) : null;
    }

    public function guestbookMessages(array $options = []): Collection
    {
        $state = $this->guestbookState();

        if (! ($state['enabled'] ?? false)) {
            return collect();
        }

        $settings = $state['settings'];
        $query = DB::table('module_guestbook_messages')
            ->where('site_id', $this->site->id);

        if (! empty($settings['show_after_reply'])) {
            $query->where('status', 'replied');
        }

        $status = trim((string) ($options['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $this->applyGuestbookOrder($query, (string) ($options['order'] ?? 'created_at_desc'));

        if (! empty($options['offset'])) {
            $query->offset(max(0, (int) $options['offset']));
        }

        $limit = max(1, min(20, (int) ($options['limit'] ?? 6)));
        $query->limit($limit);

        $messages = $query->get([
            'id',
            'display_no',
            'name',
            'content',
            'status',
            'reply_content',
            'created_at',
            'replied_at',
        ])->map(fn (object $message): array => $this->mapGuestbookMessage($message, $settings))
            ->values();

        $fields = $this->normalizeFieldList($options['fields'] ?? null);

        if ($fields === []) {
            return $messages;
        }

        $fields = array_values(array_unique(array_merge(['id'], $fields)));

        return $messages->map(function (array $message) use ($fields): array {
            $payload = [];

            foreach ($fields as $field) {
                $payload[$field] = $message[$field] ?? null;
            }

            return $payload;
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function guestbookStats(): array
    {
        if ($this->guestbookStatsCache !== null) {
            return $this->guestbookStatsCache;
        }

        $state = $this->guestbookState();

        if (! ($state['enabled'] ?? false)) {
            return $this->guestbookStatsCache = [
                'enabled' => 0,
                'message' => (string) ($state['message'] ?? '留言板模块未启用'),
                'total' => 0,
                'replied' => 0,
                'pending' => 0,
                'latest_created_at' => null,
                'latest_created_at_label' => '',
            ];
        }

        $settings = $state['settings'];
        $baseQuery = DB::table('module_guestbook_messages')
            ->where('site_id', $this->site->id);

        if (! empty($settings['show_after_reply'])) {
            $baseQuery->where('status', 'replied');
        }

        $total = (clone $baseQuery)->count();
        $replied = (clone $baseQuery)->where('status', 'replied')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $latestCreatedAt = (clone $baseQuery)->max('created_at');

        return $this->guestbookStatsCache = [
            'enabled' => 1,
            'message' => '',
            'total' => (int) $total,
            'replied' => (int) $replied,
            'pending' => (int) $pending,
            'latest_created_at' => $latestCreatedAt,
            'latest_created_at_label' => $latestCreatedAt
                ? Carbon::parse((string) $latestCreatedAt)->format('Y-m-d')
                : '',
        ];
    }

    public function linkTo(array $options = []): string
    {
        $type = trim((string) ($options['type'] ?? ''));
        $default = (string) ($options['default'] ?? '#');
        $target = $options['target'] ?? null;

        if ($type === '') {
            return $default;
        }

        return match ($type) {
            'home' => $this->url('home'),
            'channel' => $this->resolveChannelLink($options, $target, $default),
            'article', 'page' => $this->resolveContentLink($type, $options, $target, $default),
            default => $default,
        };
    }

    public function valueOr(array $options = []): string
    {
        $value = $options['value'] ?? null;
        $default = (string) ($options['default'] ?? '');

        if ($value === null) {
            return $default;
        }

        if (is_string($value) && trim($value) === '') {
            return $default;
        }

        return (string) $value;
    }

    public function truncate(array $options = []): string
    {
        $value = (string) ($options['value'] ?? '');
        $length = max(0, (int) ($options['length'] ?? 0));
        $ellipsis = array_key_exists('ellipsis', $options)
            ? (string) ($options['ellipsis'] ?? '...')
            : '...';

        if ($length <= 0) {
            return $value;
        }

        return Str::length($value) > $length
            ? Str::substr($value, 0, $length).$ellipsis
            : $value;
    }

    public function plainText(array $options = []): string
    {
        return trim(strip_tags((string) ($options['value'] ?? '')));
    }

    public function formatDate(array $options = []): string
    {
        $value = $options['value'] ?? null;
        $format = (string) ($options['format'] ?? 'Y-m-d');
        $default = (string) ($options['default'] ?? '');

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->format($format);
            }

            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function timeAgo(array $options = []): string
    {
        $value = $options['value'] ?? null;
        $default = (string) ($options['default'] ?? '');

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->diffForHumans();
            }

            return Carbon::parse((string) $value)->diffForHumans();
        } catch (\Throwable) {
            return $default;
        }
    }

    public function textToHtml(array $options = []): string
    {
        $value = (string) ($options['value'] ?? '');

        return nl2br(e($value), false);
    }

    public function promo(string $code, array $options = []): ?array
    {
        return $this->promos(array_merge($options, ['code' => $code, 'limit' => 1]))->first();
    }

    public function promos(array $options = []): Collection
    {
        $resolved = $this->resolvePromoOptions($options);

        if (($resolved['code'] ?? '') === '' && ($resolved['display_mode'] ?? '') === '' && ($resolved['page_scope'] ?? '') === '') {
            return collect();
        }

        $now = now();

        $query = DB::table('promo_items')
            ->join('promo_positions', 'promo_positions.id', '=', 'promo_items.position_id')
            ->join('attachments', 'attachments.id', '=', 'promo_items.attachment_id')
            ->where('promo_positions.site_id', $this->site->id)
            ->where('promo_items.site_id', $this->site->id)
            ->where('promo_positions.status', 1)
            ->where('promo_items.status', 1)
            ->where(function ($windowQuery) use ($now): void {
                $windowQuery->whereNull('promo_items.start_at')
                    ->orWhere('promo_items.start_at', '<=', $now);
            })
            ->where(function ($windowQuery) use ($now): void {
                $windowQuery->whereNull('promo_items.end_at')
                    ->orWhere('promo_items.end_at', '>=', $now);
            });

        if ($resolved['code'] !== '') {
            $resolvedPositionId = $this->resolveBestPromoPositionId($resolved);

            if ($resolvedPositionId === null) {
                return collect();
            }

            $query->where('promo_positions.id', $resolvedPositionId);
        }

        if ($resolved['page_scope'] !== '') {
            $query->where('promo_positions.page_scope', $resolved['page_scope']);
        }

        if ($resolved['display_mode'] !== '') {
            $query->where('promo_positions.display_mode', $resolved['display_mode']);
        }

        if ($resolved['channel_id'] !== null) {
            $query->where(function ($channelQuery) use ($resolved): void {
                $channelQuery->whereNull('promo_positions.channel_id')
                    ->orWhere('promo_positions.channel_id', $resolved['channel_id']);
            });
        } else {
            $query->whereNull('promo_positions.channel_id');
        }

        if ($resolved['template_name'] !== null && $resolved['template_name'] !== '') {
            $query->where(function ($templateQuery) use ($resolved): void {
                $templateQuery->whereNull('promo_positions.template_name')
                    ->orWhere('promo_positions.template_name', $resolved['template_name']);
            });
        } else {
            $query->whereNull('promo_positions.template_name');
        }

        if (! empty($options['random'])) {
            $query->inRandomOrder();
        } else {
            $query = $query
                ->when($resolved['channel_id'] !== null, function ($scopedQuery) use ($resolved): void {
                    $scopedQuery->orderByRaw(
                        'CASE WHEN promo_positions.channel_id = ? THEN 1 ELSE 0 END DESC',
                        [(int) $resolved['channel_id']]
                    );
                })
                ->when($resolved['template_name'] !== null && $resolved['template_name'] !== '', function ($scopedQuery) use ($resolved): void {
                    $scopedQuery->orderByRaw(
                        'CASE WHEN promo_positions.template_name = ? THEN 1 ELSE 0 END DESC',
                        [(string) $resolved['template_name']]
                    );
                })
                ->orderBy('promo_items.sort')
                ->orderByDesc('promo_items.id');
        }

        $query->limit((int) ($resolved['limit'] ?? 10));

        $items = $query->get([
                'promo_positions.id as position_id',
                'promo_positions.code as position_code',
                'promo_positions.name as position_name',
                'promo_positions.page_scope',
                'promo_positions.display_mode',
                'promo_positions.channel_id',
                'promo_positions.template_name',
                'promo_positions.allow_multiple',
                'promo_positions.max_items',
                'promo_items.id',
                'promo_items.attachment_id',
                'promo_items.title',
                'promo_items.subtitle',
                'promo_items.link_url',
                'promo_items.link_target',
                'promo_items.sort',
                'promo_items.start_at',
                'promo_items.end_at',
                'promo_items.display_payload',
                'attachments.origin_name as attachment_name',
                'attachments.extension as attachment_extension',
                'attachments.url as attachment_url',
            ]);

        $items = $items
            ->map(fn (object $item): array => $this->mapPromo($item))
            ->values();

        $fields = $this->normalizeFieldList($options['fields'] ?? null);

        if ($fields === []) {
            return $items;
        }

        $fields = array_values(array_unique(array_merge(['id'], $fields)));

        return $items
            ->map(function (array $item) use ($fields): array {
                $payload = [];

                foreach ($fields as $field) {
                    $payload[$field] = $item[$field] ?? null;
                }

                return $payload;
            })
            ->values();
    }

    protected function resolveBestPromoPositionId(array $resolved): ?int
    {
        $query = DB::table('promo_positions')
            ->where('site_id', $this->site->id)
            ->where('status', 1)
            ->where('code', (string) $resolved['code']);

        if (($resolved['page_scope'] ?? '') !== '') {
            $query->where('page_scope', (string) $resolved['page_scope']);
        }

        if (($resolved['display_mode'] ?? '') !== '') {
            $query->where('display_mode', (string) $resolved['display_mode']);
        }

        if (($resolved['channel_id'] ?? null) !== null) {
            $query->where(function ($channelQuery) use ($resolved): void {
                $channelQuery->whereNull('channel_id')
                    ->orWhere('channel_id', (int) $resolved['channel_id']);
            });
        } else {
            $query->whereNull('channel_id');
        }

        if (($resolved['template_name'] ?? null) !== null && $resolved['template_name'] !== '') {
            $query->where(function ($templateQuery) use ($resolved): void {
                $templateQuery->whereNull('template_name')
                    ->orWhere('template_name', (string) $resolved['template_name']);
            });
        } else {
            $query->whereNull('template_name');
        }

        $query
            ->when(($resolved['channel_id'] ?? null) !== null, function ($scopedQuery) use ($resolved): void {
                $scopedQuery->orderByRaw(
                    'CASE WHEN channel_id = ? THEN 1 ELSE 0 END DESC',
                    [(int) $resolved['channel_id']]
                );
            })
            ->when(($resolved['template_name'] ?? null) !== null && $resolved['template_name'] !== '', function ($scopedQuery) use ($resolved): void {
                $scopedQuery->orderByRaw(
                    'CASE WHEN template_name = ? THEN 1 ELSE 0 END DESC',
                    [(string) $resolved['template_name']]
                );
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $positionId = $query->value('id');

        return $positionId !== null ? (int) $positionId : null;
    }

    public function contentList(array $options = []): Collection
    {
        $type = (string) ($options['type'] ?? 'article');

        $query = DB::table('contents')
            ->leftJoin('channels', 'channels.id', '=', 'contents.channel_id')
            ->where('contents.site_id', $this->site->id)
            ->where('contents.type', $type)
            ->whereNull('contents.deleted_at');

        if (array_key_exists('status', $options)) {
            if ($options['status'] !== null) {
                $query->where('contents.status', (string) $options['status']);
            }
        } elseif ($type === 'article') {
            $query->where('contents.status', 'published');
        }

        $channelId = $this->resolveChannelIdFromOptions([
            'id' => $options['channel_id'] ?? null,
            'slug' => $options['channel_slug'] ?? null,
        ]);

        if ($channelId === null && array_key_exists('channel', $options) && $options['channel'] !== '') {
            $channel = $options['channel'];
            $channelId = is_numeric($channel)
                ? (int) $channel
                : $this->resolveChannelIdFromOptions(['slug' => (string) $channel]);
        }

        if ($channelId !== null) {
            $this->applyChannelMembershipFilter($query, $this->channelAndDescendantLeafIds($channelId));
        } elseif (
            (array_key_exists('channel', $options) && $options['channel'] !== '')
            || (! empty($options['channel_id']))
            || (! empty($options['channel_slug']))
        ) {
            $query->whereRaw('1 = 0');
        }

        if (! empty($options['is_top'])) {
            $query->where('contents.is_top', 1);
        }

        if (! empty($options['is_recommend'])) {
            $query->where('contents.is_recommend', 1);
        }

        if (! empty($options['with_cover']) || ! empty($options['has_image'])) {
            $query->whereNotNull('contents.cover_image')->where('contents.cover_image', '!=', '');
        }

        if (! empty($options['author'])) {
            $query->where('contents.author', (string) $options['author']);
        }

        if (! empty($options['source'])) {
            $query->where('contents.source', (string) $options['source']);
        }

        $includeIds = $this->normalizeIdList($options['include_ids'] ?? null);
        if ($includeIds !== []) {
            $query->whereIn('contents.id', $includeIds);
        }

        $excludeIds = $this->normalizeIdList($options['exclude_ids'] ?? null);
        if ($excludeIds !== []) {
            $query->whereNotIn('contents.id', $excludeIds);
        }

        if (! empty($options['keyword'])) {
            $keyword = trim((string) $options['keyword']);

            $query->where(function ($keywordQuery) use ($keyword): void {
                $keywordQuery->where('contents.title', 'like', '%'.$keyword.'%')
                    ->orWhere('contents.summary', 'like', '%'.$keyword.'%');
            });
        }

        if (! empty($options['published_after'])) {
            $query->where('contents.published_at', '>=', (string) $options['published_after']);
        }

        if (! empty($options['published_before'])) {
            $query->where('contents.published_at', '<=', (string) $options['published_before']);
        }

        if (! empty($options['random'])) {
            $query->inRandomOrder();
        } elseif (! empty($options['order_by']) || ! empty($options['order_dir'])) {
            $this->applyCustomOrder(
                $query,
                (string) ($options['order_by'] ?? 'published_at'),
                (string) ($options['order_dir'] ?? 'desc')
            );
        } else {
            $this->applyOrder($query, (string) ($options['order'] ?? 'published_at_desc'));
        }

        if (! empty($options['offset'])) {
            $query->offset(max(0, (int) $options['offset']));
        }

        $query->limit((int) ($options['limit'] ?? 10));

        $contents = $query->get([
            'contents.*',
            'channels.name as channel_name',
            'channels.slug as channel_slug',
        ])->map(fn ($content) => $this->mapContent($content))
            ->values();

        $fields = $this->normalizeFieldList($options['fields'] ?? null);

        if ($fields === []) {
            return $contents;
        }

        $fields = array_values(array_unique(array_merge(['id'], $fields)));

        return $contents
            ->map(function (array $content) use ($fields): array {
                $payload = [];

                foreach ($fields as $field) {
                    $payload[$field] = $content[$field] ?? null;
                }

                return $payload;
            })
            ->values();
    }

    public function stats(): array
    {
        return [
            'channels' => DB::table('channels')->where('site_id', $this->site->id)->where('status', 1)->count(),
            'articles' => DB::table('contents')
                ->where('site_id', $this->site->id)
                ->where('type', 'article')
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->count(),
            'pages' => DB::table('contents')
                ->where('site_id', $this->site->id)
                ->where('type', 'page')
                ->whereNull('deleted_at')
                ->count(),
            'status' => ($this->site->status ?? 0) ? '正常' : '停用',
        ];
    }

    public function previous(mixed $content): ?object
    {
        $context = $this->resolveLinkedContentContext($content);

        if (! $context) {
            return null;
        }

        $content = DB::table('contents')
            ->where('site_id', $this->site->id)
            ->where('channel_id', $context->channel_id)
            ->where('type', $context->type)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->where('id', '<', $context->id)
            ->orderByDesc('id')
            ->first(['id', 'title', 'title_color', 'title_bold', 'title_italic', 'is_recommend', 'type']);

        return $content ? (object) $this->mapLinkedContent($content) : null;
    }

    public function next(mixed $content): ?object
    {
        $context = $this->resolveLinkedContentContext($content);

        if (! $context) {
            return null;
        }

        $content = DB::table('contents')
            ->where('site_id', $this->site->id)
            ->where('channel_id', $context->channel_id)
            ->where('type', $context->type)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->where('id', '>', $context->id)
            ->orderBy('id')
            ->first(['id', 'title', 'title_color', 'title_bold', 'title_italic', 'is_recommend', 'type']);

        return $content ? (object) $this->mapLinkedContent($content) : null;
    }

    public function related(mixed $content, int $limit = 4): Collection
    {
        $context = $this->resolveLinkedContentContext($content);

        if (! $context) {
            return collect();
        }

        return DB::table('contents')
            ->where('site_id', $this->site->id)
            ->where('channel_id', $context->channel_id)
            ->where('type', $context->type)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->where('id', '!=', $context->id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'title_color', 'title_bold', 'title_italic', 'is_recommend', 'published_at', 'type'])
            ->map(fn ($related) => $this->mapLinkedContent($related))
            ->values();
    }

    protected function url(string $type, mixed $target = null): string
    {
        return match ($type) {
            'home' => route('site.home', ['site' => $this->site->site_key]),
            'channel' => route('site.channel', ['slug' => is_object($target) ? $target->slug : $target, 'site' => $this->site->site_key]),
            'article' => route('site.article', ['id' => is_object($target) ? $target->id : $target, 'site' => $this->site->site_key]),
            'page' => route('site.page', ['id' => is_object($target) ? $target->id : $target, 'site' => $this->site->site_key]),
            default => '#',
        };
    }

    protected function applyOrder($query, string $order): void
    {
        match ($order) {
            'updated_at_desc' => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderByDesc('contents.updated_at')->orderByDesc('contents.id'),
            'updated_at_asc' => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderBy('contents.updated_at')->orderBy('contents.id'),
            'id_desc' => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderByDesc('contents.id'),
            'id_asc' => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderBy('contents.id'),
            'published_at_asc' => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderBy('contents.published_at')->orderBy('contents.id'),
            default => $query->orderByDesc('contents.is_top')->orderByDesc('contents.sort')->orderByDesc('contents.published_at')->orderByDesc('contents.id'),
        };
    }

    protected function applyCustomOrder($query, string $orderBy, string $orderDir): void
    {
        $direction = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';
        $column = match ($orderBy) {
            'id' => 'contents.id',
            'updated_at' => 'contents.updated_at',
            'sort' => 'contents.sort',
            default => 'contents.published_at',
        };

        $query->orderByDesc('contents.is_top')
            ->orderByDesc('contents.sort')
            ->orderBy($column, $direction)
            ->orderBy('contents.id', $direction);
    }

    protected function resolvePromoOptions(array $options): array
    {
        $channelId = isset($options['channel_id']) && $options['channel_id'] !== ''
            ? (int) $options['channel_id']
            : null;

        if ($channelId === null && ! empty($options['channel_slug'])) {
            $channel = $this->siteChannelsBySlug()->get((string) $options['channel_slug']);
            $channelId = $channel ? (int) $channel->id : null;
        }

        return [
            'code' => trim((string) ($options['code'] ?? '')),
            'page_scope' => trim((string) ($options['page_scope'] ?? $this->currentPageScope ?? '')),
            'display_mode' => trim((string) ($options['display_mode'] ?? '')),
            'template_name' => array_key_exists('template_name', $options)
                ? (trim((string) $options['template_name']) ?: null)
                : $this->currentTemplateName,
            'channel_id' => $channelId ?? $this->currentChannelId,
            'limit' => max(1, (int) ($options['limit'] ?? 10)),
        ];
    }

    protected function mapPromo(object $promo): array
    {
        $display = $this->normalizePromoDisplayPayload($promo);

        return [
            'id' => (int) $promo->id,
            'position_id' => (int) $promo->position_id,
            'position_code' => (string) $promo->position_code,
            'position_name' => (string) $promo->position_name,
            'page_scope' => (string) $promo->page_scope,
            'display_mode' => (string) $promo->display_mode,
            'channel_id' => $promo->channel_id !== null ? (int) $promo->channel_id : null,
            'template_name' => $promo->template_name ?: null,
            'attachment_id' => (int) $promo->attachment_id,
            'image_url' => (string) $promo->attachment_url,
            'image_alt' => trim((string) ($promo->title ?: $promo->attachment_name ?: $promo->position_name)),
            'attachment_name' => (string) ($promo->attachment_name ?? ''),
            'attachment_extension' => (string) ($promo->attachment_extension ?? ''),
            'title' => (string) ($promo->title ?? ''),
            'subtitle' => (string) ($promo->subtitle ?? ''),
            'link_url' => (string) ($promo->link_url ?? ''),
            'link_target' => (string) ($promo->link_target ?? '_self'),
            'sort' => (int) ($promo->sort ?? 0),
            'start_at' => $promo->start_at,
            'end_at' => $promo->end_at,
            'display' => $display,
        ];
    }

    protected function normalizePromoDisplayPayload(object $promo): array
    {
        $payload = json_decode((string) ($promo->display_payload ?? ''), true);
        $payload = is_array($payload) ? $payload : [];

        if (($promo->display_mode ?? '') !== 'floating') {
            return $payload;
        }

        $position = (string) ($payload['position'] ?? 'right-bottom');
        $offsetX = (int) ($payload['offset_x'] ?? 24);
        $offsetY = (int) ($payload['offset_y'] ?? 24);
        $width = isset($payload['width']) && $payload['width'] !== '' ? (int) $payload['width'] : 180;
        $height = isset($payload['height']) && $payload['height'] !== '' ? (int) $payload['height'] : null;
        $zIndex = (int) ($payload['z_index'] ?? 120);

        $offsetXToken = $this->normalizePromoToken($offsetX, [0, 8, 12, 16, 20, 24, 28, 32, 40, 48, 56, 64], 24);
        $offsetYToken = $this->normalizePromoToken($offsetY, [0, 8, 12, 16, 20, 24, 28, 32, 40, 48, 56, 64], 24);
        $widthToken = $this->normalizePromoToken($width, [120, 160, 180, 200, 240, 280, 320, 360, 420], 180);
        $heightToken = $height !== null
            ? $this->normalizePromoToken($height, [120, 160, 180, 200, 240, 280, 320, 360, 420], 180)
            : null;
        $zIndexToken = $this->normalizePromoToken($zIndex, [100, 120, 160, 200, 240, 300], 120);

        return [
            'position' => $position,
            'offset_x' => $offsetX,
            'offset_y' => $offsetY,
            'width' => $width,
            'height' => $height,
            'z_index' => $zIndex,
            'offset_x_token' => $offsetXToken,
            'offset_y_token' => $offsetYToken,
            'width_token' => $widthToken,
            'height_token' => $heightToken,
            'z_index_token' => $zIndexToken,
            'animation' => (string) ($payload['animation'] ?? 'float'),
            'show_on' => (string) ($payload['show_on'] ?? 'all'),
            'closable' => (bool) ($payload['closable'] ?? true),
            'remember_close' => (bool) ($payload['remember_close'] ?? true),
            'close_expire_hours' => max(1, (int) ($payload['close_expire_hours'] ?? 24)),
            'close_storage_key' => sprintf('promo-floating-close:%s:%d', $promo->position_code, $promo->id),
        ];
    }

    /**
     * @param  list<int>  $tokens
     */
    protected function normalizePromoToken(int $value, array $tokens, int $fallback): int
    {
        if ($tokens === []) {
            return $fallback;
        }

        $closest = $fallback;
        $closestDistance = PHP_INT_MAX;

        foreach ($tokens as $token) {
            $distance = abs($value - $token);
            if ($distance < $closestDistance) {
                $closest = $token;
                $closestDistance = $distance;
            }
        }

        return $closest;
    }

    /**
     * @param  array<int, int>  $channelIds
     */
    protected function applyChannelMembershipFilter($query, array $channelIds): void
    {
        $channelIds = array_values(array_unique(array_map('intval', $channelIds)));

        if ($channelIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($subQuery) use ($channelIds): void {
            $subQuery->selectRaw('1')
                ->from('content_channels')
                ->whereColumn('content_channels.content_id', 'contents.id')
                ->whereIn('content_channels.channel_id', $channelIds);
        });
    }

    /**
     * @return array<int, int>
     */
    protected function channelAndDescendantLeafIds(int $channelId): array
    {
        $childrenByParent = $this->allSiteChannels()
            ->map(fn (object $channel): object => (object) [
                'id' => (int) $channel->id,
                'parent_id' => $channel->parent_id !== null ? (int) $channel->parent_id : null,
            ])
            ->groupBy(fn (object $channel): int => (int) ($channel->parent_id ?? 0));
        $leafIds = [];

        $walk = function (int $parentId) use (&$walk, $childrenByParent, &$leafIds): void {
            $children = $childrenByParent->get($parentId, collect())->values();

            if ($children->isEmpty()) {
                $leafIds[] = $parentId;
                return;
            }

            foreach ($children as $child) {
                $walk((int) $child->id);
            }
        };

        $walk($channelId);

        return array_values(array_unique(array_map('intval', $leafIds)));
    }

    protected function mapChannel(object $channel): array
    {
        $url = ($channel->type ?? 'list') === 'link' && ! empty($channel->link_url)
            ? (string) $channel->link_url
            : $this->url('channel', $channel);

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'slug' => $channel->slug,
            'type' => $channel->type ?? 'list',
            'parent_id' => $channel->parent_id ?? null,
            'link_url' => $channel->link_url ?? '',
            'link_target' => $channel->link_target ?? '_self',
            'url' => $url,
            'target' => ($channel->type ?? 'list') === 'link'
                ? ($channel->link_target ?: '_self')
                : '_self',
        ];
    }

    protected function mapContent(object $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'title_color' => $content->title_color ?? '',
            'title_bold' => (bool) ($content->title_bold ?? false),
            'title_italic' => (bool) ($content->title_italic ?? false),
            'is_recommend' => (bool) ($content->is_recommend ?? false),
            'summary' => $content->summary,
            'content_html' => EmbeddedContentRenderer::render($content->content ?? ''),
            'cover_image' => $content->cover_image ?? '',
            'author' => $content->author ?: '本站编辑',
            'source' => $content->source ?? '',
            'channel_id' => $content->channel_id !== null ? (int) $content->channel_id : null,
            'published_at' => $content->published_at,
            'updated_at' => $content->updated_at ?? null,
            'channel_name' => $content->channel_name ?? '',
            'channel_slug' => $content->channel_slug ?? '',
            'type' => $content->type,
            'url' => $this->url($content->type === 'page' ? 'page' : 'article', $content),
        ];
    }

    protected function resolveCurrentPageType(): string
    {
        return match ($this->currentPageScope) {
            'detail' => 'article',
            default => $this->currentPageScope ?: 'home',
        };
    }

    protected function resolveCurrentChannel(): ?object
    {
        if ($this->currentChannelId === null) {
            return null;
        }

        return $this->siteChannelsById()->get($this->currentChannelId);
    }

    protected function resolveChannelFromOptions(array $options): ?object
    {
        $channelId = $this->extractChannelIdentifier($options, ['id', 'channel_id']);

        if ($channelId !== null) {
            return $this->siteChannelsById()->get($channelId);
        }

        if (! empty($options['slug'])) {
            return $this->siteChannelsBySlug()->get((string) $options['slug']);
        }

        return $this->resolveCurrentChannel();
    }

    protected function emptyChannelPayload(): array
    {
        return [
            'id' => null,
            'name' => '',
            'slug' => '',
            'type' => '',
            'parent_id' => null,
            'link_url' => '',
            'link_target' => '_self',
            'url' => '',
            'target' => '_self',
        ];
    }

    protected function resolveChannelIdFromOptions(array $options): ?int
    {
        $channelId = $this->extractChannelIdentifier($options, ['id', 'channel_id']);

        if ($channelId !== null) {
            return $channelId;
        }

        if (! empty($options['slug'])) {
            $channel = $this->siteChannelsBySlug()->get((string) $options['slug']);

            return $channel !== null ? (int) $channel->id : null;
        }

        return $this->currentChannelId;
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function extractChannelIdentifier(array $options, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null && $options[$key] !== '') {
                return (int) $options[$key];
            }
        }

        return null;
    }

    protected function allSiteChannels(): Collection
    {
        if ($this->siteChannelsCache === null) {
            $this->siteChannelsCache = DB::table('channels')
                ->where('site_id', $this->site->id)
                ->get();
        }

        return $this->siteChannelsCache;
    }

    protected function siteChannelsById(): Collection
    {
        if ($this->siteChannelsByIdCache === null) {
            $this->siteChannelsByIdCache = $this->allSiteChannels()
                ->keyBy(fn (object $channel): int => (int) $channel->id);
        }

        return $this->siteChannelsByIdCache;
    }

    protected function siteChannelsBySlug(): Collection
    {
        if ($this->siteChannelsBySlugCache === null) {
            $this->siteChannelsBySlugCache = $this->allSiteChannels()
                ->filter(fn (object $channel): bool => trim((string) ($channel->slug ?? '')) !== '')
                ->keyBy(fn (object $channel): string => (string) $channel->slug);
        }

        return $this->siteChannelsBySlugCache;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeIdList(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function ($item): int {
            return (int) $item;
        }, $value), static fn (int $item): bool => $item > 0)));
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeFieldList(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function ($item): string {
            return trim((string) $item);
        }, $value), static fn (string $item): bool => $item !== '')));
    }

    protected function applyGuestbookOrder($query, string $order): void
    {
        match ($order) {
            'created_at_asc' => $query->orderBy('created_at')->orderBy('id'),
            'replied_at_desc' => $query->orderByDesc('replied_at')->orderByDesc('id'),
            'replied_at_asc' => $query->orderBy('replied_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function guestbookState(): array
    {
        if ($this->guestbookStateCache !== null) {
            return $this->guestbookStateCache;
        }

        /** @var GuestbookModule $module */
        $module = app(GuestbookModule::class);
        /** @var GuestbookSettings $settingsService */
        $settingsService = app(GuestbookSettings::class);

        $boundModule = $module->boundForSite((int) $this->site->id);
        $settings = $settingsService->forSite((int) $this->site->id);

        if (! is_array($boundModule)) {
            return $this->guestbookStateCache = [
                'enabled' => false,
                'message' => '留言板模块未绑定',
                'settings' => $settings,
            ];
        }

        if (! $settings['enabled']) {
            return $this->guestbookStateCache = [
                'enabled' => false,
                'message' => '留言板模块已关闭',
                'settings' => $settings,
            ];
        }

        return $this->guestbookStateCache = [
            'enabled' => true,
            'message' => '',
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function mapGuestbookMessage(object $message, array $settings): array
    {
        $displayNo = $this->guestbookDisplayNo((int) $message->display_no);
        $replyContent = (string) ($message->reply_content ?? '');

        return [
            'id' => (int) $message->id,
            'display_no' => $displayNo,
            'name' => ! empty($settings['show_name'])
                ? (string) $message->name
                : $this->maskGuestbookName((string) $message->name),
            'content' => (string) $message->content,
            'summary' => $this->guestbookSummary((string) $message->content, 220),
            'reply_content' => $replyContent,
            'reply_summary' => $this->guestbookSummary($replyContent, 160),
            'status' => (string) $message->status,
            'status_label' => (string) $message->status === 'replied' ? '已办理' : '待办理',
            'created_at' => $message->created_at,
            'created_at_label' => $message->created_at
                ? Carbon::parse((string) $message->created_at)->format('Y-m-d')
                : '',
            'replied_at' => $message->replied_at,
            'replied_at_label' => $message->replied_at
                ? Carbon::parse((string) $message->replied_at)->format('Y-m-d')
                : '',
            'detail_url' => route('site.guestbook.show', [
                'displayNo' => (int) $message->display_no,
                'site' => $this->site->site_key,
            ]),
        ];
    }

    protected function guestbookDisplayNo(int $displayNo): string
    {
        return str_pad((string) $displayNo, 5, '0', STR_PAD_LEFT);
    }

    protected function maskGuestbookName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return mb_substr($name, 0, 1, 'UTF-8').'***';
    }

    protected function guestbookSummary(string $content, int $limit): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        return Str::limit($content, $limit, '...');
    }

    protected function resolveLinkedContentContext(mixed $content): ?object
    {
        $id = null;
        $channelId = null;
        $type = null;

        if (is_object($content)) {
            $id = $content->id ?? null;
            $channelId = $content->channel_id ?? null;
            $type = $content->type ?? null;
        } elseif (is_array($content)) {
            $id = $content['id'] ?? null;
            $channelId = $content['channel_id'] ?? null;
            $type = $content['type'] ?? null;
        }

        $id = $id !== null ? (int) $id : 0;
        $channelId = $channelId !== null ? (int) $channelId : 0;
        $type = trim((string) ($type ?? 'article'));

        if ($id <= 0 || $channelId <= 0 || $type === '') {
            return null;
        }

        return (object) [
            'id' => $id,
            'channel_id' => $channelId,
            'type' => $type,
        ];
    }

    protected function resolveChannelLink(array $options, mixed $target, string $default): string
    {
        $channel = is_array($target) || is_object($target)
            ? $this->resolveChannelTarget($target)
            : $this->resolveChannelFromOptions($options);

        if (! $channel) {
            return $default;
        }

        return $this->url('channel', $channel);
    }

    protected function resolveContentLink(string $type, array $options, mixed $target, string $default): string
    {
        $content = is_array($target) || is_object($target)
            ? $this->resolveContentTarget($target, $type)
            : $this->content($options);

        if (! is_array($content) || empty($content['id'])) {
            return $default;
        }

        return (string) ($content['url'] ?? $this->url($type, (object) ['id' => $content['id']]));
    }

    protected function resolveChannelTarget(mixed $target): ?object
    {
        $id = null;
        $slug = null;

        if (is_object($target)) {
            $id = $target->id ?? null;
            $slug = $target->slug ?? null;
        } elseif (is_array($target)) {
            $id = $target['id'] ?? null;
            $slug = $target['slug'] ?? null;
        }

        if ($id !== null && (int) $id > 0) {
            return $this->siteChannelsById()->get((int) $id);
        }

        if (is_string($slug) && trim($slug) !== '') {
            return $this->siteChannelsBySlug()->get(trim($slug));
        }

        return null;
    }

    protected function resolveContentTarget(mixed $target, string $fallbackType): ?array
    {
        if (is_array($target)) {
            $id = isset($target['id']) ? (int) $target['id'] : 0;

            if ($id > 0) {
                return array_merge(['type' => $fallbackType], $target);
            }

            return null;
        }

        if (is_object($target)) {
            $id = isset($target->id) ? (int) $target->id : 0;

            if ($id <= 0) {
                return null;
            }

            return [
                'id' => $id,
                'url' => $target->url ?? $this->url(($target->type ?? $fallbackType) === 'page' ? 'page' : 'article', $target),
                'type' => $target->type ?? $fallbackType,
            ];
        }

        return null;
    }

    protected function scalarToString(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            $string = (string) $value;

            return $string === '' ? $default : $string;
        }

        return $default;
    }

    protected function mapLinkedContent(object $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'title_color' => $content->title_color ?? '',
            'title_bold' => (bool) ($content->title_bold ?? false),
            'title_italic' => (bool) ($content->title_italic ?? false),
            'is_recommend' => (bool) ($content->is_recommend ?? false),
            'published_at' => $content->published_at ?? null,
            'type' => $content->type ?? 'article',
            'url' => $this->url(($content->type ?? 'article') === 'page' ? 'page' : 'article', $content),
        ];
    }
}
