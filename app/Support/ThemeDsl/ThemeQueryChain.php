<?php

namespace App\Support\ThemeDsl;

use App\Support\ThemeTemplateException;
use App\Support\ThemeTags;
use Illuminate\Support\Collection;

class ThemeQueryChain
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    protected ?string $linkMode = null;

    protected mixed $linkSubject = null;

    protected bool $firstOnly = false;

    public function __construct(
        protected ThemeTags $tags,
        protected string $subject = 'article',
    ) {
        $normalized = strtolower(trim($this->subject));
        $this->subject = $normalized !== '' ? $normalized : 'article';

        if ($this->subject === 'page') {
            $this->options['type'] = 'page';
        } elseif ($this->subject === 'article') {
            $this->options['type'] = 'article';
        }
    }

    /**
     * @param  array<int, mixed>  $positional
     * @param  array<string, mixed>  $named
     */
    public function apply(string $method, array $positional = [], array $named = []): mixed
    {
        $method = ThemeDslSpec::canonicalName($method);

        return match ($method) {
            'channel' => $this->setChannel($positional[0] ?? $named['value'] ?? $named['slug'] ?? null),
            'channelId' => $this->option('channel_id', $positional[0] ?? $named['value'] ?? null),
            'type' => $this->option('type', $positional[0] ?? $named['value'] ?? null),
            'status' => $this->option('status', $positional[0] ?? $named['value'] ?? null),
            'top' => $this->option('is_top', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'featured' => $this->option('is_recommend', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'hasImage' => $this->option('has_image', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'author' => $this->option('author', $positional[0] ?? $named['value'] ?? null),
            'source' => $this->option('source', $positional[0] ?? $named['value'] ?? null),
            'includeIds' => $this->option('include_ids', $this->normalizeIdExpression($positional[0] ?? $named['value'] ?? null)),
            'excludeIds' => $this->option('exclude_ids', $this->normalizeIdExpression($positional[0] ?? $named['value'] ?? null)),
            'keyword' => $this->option('keyword', $positional[0] ?? $named['value'] ?? null),
            'publishedAfter' => $this->option('published_after', $positional[0] ?? $named['value'] ?? null),
            'publishedBefore' => $this->option('published_before', $positional[0] ?? $named['value'] ?? null),
            'publishedBetween' => $this->setPublishedBetween($positional, $named),
            'orderBy' => $this->setOrderBy($positional, $named),
            'random' => $this->option('random', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'fields' => $this->option('fields', $this->normalizeFields($positional[0] ?? $named['value'] ?? null)),
            'offset' => $this->option('offset', max(0, (int) ($positional[0] ?? $named['value'] ?? 0))),
            'limit' => $this->option('limit', max(1, min(ThemeDslSpec::maxLimit(), (int) ($positional[0] ?? $named['value'] ?? 10)))),
            'perPage' => $this->option('per_page', max(1, min(ThemeDslSpec::maxPerPage(), (int) ($positional[0] ?? $named['value'] ?? 10)))),
            'pageName' => $this->option('page_name', (string) ($positional[0] ?? $named['value'] ?? 'page')),
            'window' => $this->option('window', max(1, min(ThemeDslSpec::maxWindow(), (int) ($positional[0] ?? $named['value'] ?? 2)))),
            'siteWide' => $this->option('site_wide', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'tree' => $this->option('tree', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'parentId' => $this->option('parent_id', (int) ($positional[0] ?? $named['value'] ?? 0)),
            'nav' => $this->option('is_nav', $this->toBool($positional[0] ?? $named['value'] ?? true)),
            'scope' => $this->option('page_scope', $positional[0] ?? $named['value'] ?? null),
            'template' => $this->option('template_name', $positional[0] ?? $named['value'] ?? null),
            'displayMode' => $this->option('display_mode', $positional[0] ?? $named['value'] ?? null),
            'previousOf' => $this->link('previous', $positional[0] ?? $named['value'] ?? null),
            'nextOf' => $this->link('next', $positional[0] ?? $named['value'] ?? null),
            'relatedTo' => $this->link('related', $positional[0] ?? $named['value'] ?? null),
            'first' => $this->setFirstOnly(),
            'get' => $this->get(),
            'paginate' => $this->paginate($named, $positional),
            default => $this,
        };
    }

    public function get(): mixed
    {
        $result = match ($this->subject) {
            'channel', 'channels' => $this->resolveChannelsGet(),
            'promo', 'promos' => $this->tags->promos($this->options),
            default => $this->resolveContentGet(),
        };

        if (! $this->firstOnly) {
            return $result;
        }

        if ($result instanceof Collection) {
            return $result->first();
        }

        if (is_array($result)) {
            return reset($result) ?: null;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $override
     * @param  array<int, mixed>  $positional
     */
    public function paginate(array $override = [], array $positional = []): array
    {
        if (! in_array($this->subject, ['article', 'page'], true)) {
            throw ThemeTemplateException::syntax('paginate 仅支持 article/page 查询');
        }

        $options = array_merge($this->options, $override);

        if (isset($positional[0]) && ! array_key_exists('per_page', $options)) {
            $options['per_page'] = (int) $positional[0];
        }
        if (isset($positional[1]) && ! array_key_exists('page_name', $options)) {
            $options['page_name'] = (string) $positional[1];
        }
        if (isset($positional[2]) && ! array_key_exists('window', $options)) {
            $options['window'] = (int) $positional[2];
        }

        if (! array_key_exists('per_page', $options)) {
            $options['per_page'] = 10;
        }
        if (! array_key_exists('page_name', $options)) {
            $options['page_name'] = 'page';
        }
        if (! array_key_exists('window', $options)) {
            $options['window'] = 2;
        }

        $options['per_page'] = max(1, min(ThemeDslSpec::maxPerPage(), (int) $options['per_page']));
        $options['window'] = max(1, min(ThemeDslSpec::maxWindow(), (int) $options['window']));

        return $this->tags->contentPage($options);
    }

    protected function resolveContentGet(): mixed
    {
        if ($this->linkMode === 'previous') {
            return $this->tags->previous($this->linkSubject);
        }

        if ($this->linkMode === 'next') {
            return $this->tags->next($this->linkSubject);
        }

        if ($this->linkMode === 'related') {
            return $this->tags->related($this->linkSubject, (int) ($this->options['limit'] ?? 4));
        }

        return $this->tags->contentList($this->options);
    }

    protected function resolveChannelsGet(): Collection
    {
        $channels = $this->tags->channels($this->options);

        if (empty($this->options['tree'])) {
            return $channels;
        }

        return $this->toChannelTree($channels);
    }

    protected function toChannelTree(Collection $channels): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = [];
        foreach ($channels->all() as $item) {
            if (! is_array($item)) {
                continue;
            }
            $item['children'] = [];
            $items[(int) ($item['id'] ?? 0)] = $item;
        }

        /** @var array<int, array<string, mixed>> $roots */
        $roots = [];

        foreach ($items as $id => $item) {
            $parentId = (int) ($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$items[$id];
                continue;
            }
            $roots[] = &$items[$id];
        }

        return collect($roots)->values();
    }

    protected function setOrderBy(array $positional, array $named): static
    {
        $orderBy = (string) ($named['field'] ?? $named['value'] ?? ($positional[0] ?? 'published_at'));
        $orderDir = (string) ($named['dir'] ?? ($positional[1] ?? 'desc'));

        $this->options['order_by'] = $orderBy;
        $this->options['order_dir'] = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    protected function setPublishedBetween(array $positional, array $named): static
    {
        $from = $named['from'] ?? ($positional[0] ?? null);
        $to = $named['to'] ?? ($positional[1] ?? null);

        if ($from !== null && $from !== '') {
            $this->options['published_after'] = (string) $from;
        }

        if ($to !== null && $to !== '') {
            $this->options['published_before'] = (string) $to;
        }

        return $this;
    }

    protected function setChannel(mixed $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        if (is_numeric($value)) {
            $this->options['channel_id'] = (int) $value;

            return $this;
        }

        $this->options['channel'] = (string) $value;

        return $this;
    }

    protected function normalizeIdExpression(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map('intval', $value), fn (int $item): bool => $item > 0));

            return implode(',', $items);
        }

        return trim((string) $value);
    }

    protected function normalizeFields(mixed $value): string
    {
        if (is_array($value)) {
            $items = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $value)));

            return implode(',', $items);
        }

        return trim((string) $value);
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    protected function link(string $mode, mixed $subject): static
    {
        $this->linkMode = $mode;
        $this->linkSubject = $subject;

        return $this;
    }

    protected function setFirstOnly(): static
    {
        $this->firstOnly = true;

        return $this;
    }

    protected function option(string $key, mixed $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $this->options[$key] = $value;

        return $this;
    }
}
