<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OneTimeSchoolMysqlImporter
{
    protected const LEGACY_ASSET_PREFIX = '/atts/Up/';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $tables = [];

    public function import(string $sqlPath, string $siteKey, bool $execute = false): array
    {
        $site = DB::table('sites')
            ->where('site_key', trim($siteKey))
            ->first(['id', 'name', 'site_key']);

        if (! $site) {
            throw new RuntimeException('目标站点不存在：'.$siteKey);
        }

        $this->tables = $this->loadTables($sqlPath);

        $summary = [
            'site' => [
                'id' => (int) $site->id,
                'name' => (string) $site->name,
                'site_key' => (string) $site->site_key,
            ],
            'source' => $sqlPath,
            'dry_run' => ! $execute,
            'counts' => [
                'article_categories' => count($this->tables['school_article_category'] ?? []),
                'pages' => count($this->tables['school_article'] ?? []),
                'news_categories' => count($this->tables['school_news_category'] ?? []),
                'news' => count($this->tables['school_news'] ?? []),
                'pic_categories' => count($this->tables['school_pic_category'] ?? []),
                'pics' => count($this->tables['school_pic'] ?? []),
            ],
            'assets' => $this->assetSummary(),
            'imported' => [
                'channels_created' => 0,
                'channels_updated' => 0,
                'pages_created' => 0,
                'pages_updated' => 0,
                'articles_created' => 0,
                'articles_updated' => 0,
            ],
            'warnings' => [],
        ];

        if (! $execute) {
            $summary['warnings'][] = '当前为预检查，不写入数据库。旧附件文件需手动放到站点附件 Up 目录。';

            return $summary;
        }

        return DB::transaction(function () use ($site, $summary): array {
            $reservedSlugs = [];
            $newsChannelMap = $this->importChannels(
                (int) $site->id,
                $this->tables['school_news_category'] ?? [],
                'legacy-school-news-cat-',
                'list',
                $reservedSlugs,
                $summary
            );
            $pageChannelMap = $this->importChannels(
                (int) $site->id,
                $this->tables['school_article_category'] ?? [],
                'legacy-school-page-cat-',
                'page',
                $reservedSlugs,
                $summary
            );
            $picChannelMap = $this->importChannels(
                (int) $site->id,
                $this->tables['school_pic_category'] ?? [],
                'legacy-school-pic-cat-',
                'list',
                $reservedSlugs,
                $summary
            );

            foreach (($this->tables['school_article'] ?? []) as $index => $row) {
                $legacyId = $this->legacyId($row['id'] ?? null, $index);
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $channelId = $pageChannelMap[$this->legacyKey($row['classid'] ?? null)]
                    ?? $pageChannelMap[$this->legacyKey($row['parentid'] ?? null)]
                    ?? null;

                if (! $channelId) {
                    $channelId = $this->ensureFallbackChannel((int) $site->id, 'legacy-school-pages', '旧站单页', 'page', $summary);
                }

                $content = $this->normalizeContentHtml((string) ($row['content'] ?? ''));
                [, $created] = $this->upsertContent((int) $site->id, [
                    'channel_id' => $channelId,
                    'type' => 'page',
                    'template_name' => 'page',
                    'title' => $title,
                    'slug' => 'legacy-school-page-'.$legacyId,
                    'summary' => $this->buildSummary($content),
                    'content' => $content,
                    'cover_image' => null,
                    'author' => null,
                    'source' => (string) $site->name,
                    'status' => 'published',
                    'audit_status' => 'approved',
                    'is_top' => 0,
                    'is_recommend' => 0,
                    'sort' => (int) ($row['orderid'] ?? 0),
                    'view_count' => 0,
                    'published_at' => $this->parseTimestamp($row['posttime'] ?? null),
                    'channel_ids' => [$channelId],
                ]);

                $summary['imported'][$created ? 'pages_created' : 'pages_updated']++;
            }

            foreach (($this->tables['school_news'] ?? []) as $index => $row) {
                $legacyId = $this->legacyId($row['id'] ?? null, $index);
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $channelId = $newsChannelMap[$this->legacyKey($row['classid'] ?? null)]
                    ?? $newsChannelMap[$this->legacyKey($row['parentid'] ?? null)]
                    ?? null;

                if (! $channelId) {
                    $channelId = $this->ensureFallbackChannel((int) $site->id, 'legacy-school-news', '旧站新闻', 'list', $summary);
                }

                $content = $this->normalizeContentHtml((string) ($row['content'] ?? ''));
                $coverImage = $this->normalizeLegacyAssetPath((string) ($row['pic'] ?? ''));
                if ($content === '' && $coverImage !== null) {
                    $content = $this->imageOnlyContent($title, $coverImage);
                }

                [, $created] = $this->upsertContent((int) $site->id, [
                    'channel_id' => $channelId,
                    'type' => 'article',
                    'template_name' => 'detail',
                    'title' => $title,
                    'slug' => 'legacy-school-news-'.$legacyId,
                    'summary' => $this->buildSummary($content),
                    'content' => $content,
                    'cover_image' => $coverImage,
                    'author' => trim((string) ($row['editor'] ?? '')) ?: null,
                    'source' => (string) $site->name,
                    'status' => 'published',
                    'audit_status' => 'approved',
                    'is_top' => (int) ($row['istop'] ?? 0) === 1 ? 1 : 0,
                    'is_recommend' => 0,
                    'sort' => (int) ($row['orderid'] ?? 0),
                    'view_count' => (int) ($row['hits'] ?? 0),
                    'published_at' => $this->parseTimestamp($row['posttime'] ?? null),
                    'channel_ids' => [$channelId],
                ]);

                $summary['imported'][$created ? 'articles_created' : 'articles_updated']++;
            }

            foreach (($this->tables['school_pic'] ?? []) as $index => $row) {
                $legacyId = $this->legacyId($row['id'] ?? null, $index);
                $title = trim((string) ($row['name'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $channelId = $picChannelMap[$this->legacyKey($row['classid'] ?? null)]
                    ?? $picChannelMap[$this->legacyKey($row['parentid'] ?? null)]
                    ?? null;

                if (! $channelId) {
                    $channelId = $this->ensureFallbackChannel((int) $site->id, 'legacy-school-pics', '旧站图片视频', 'list', $summary);
                }

                $content = $this->normalizeContentHtml((string) ($row['description'] ?? ''));
                $coverImage = $this->normalizeLegacyAssetPath((string) ($row['pic'] ?? ''));
                if ($content === '' && $coverImage !== null) {
                    $content = $this->imageOnlyContent($title, $coverImage);
                }

                [, $created] = $this->upsertContent((int) $site->id, [
                    'channel_id' => $channelId,
                    'type' => 'article',
                    'template_name' => 'detail',
                    'title' => $title,
                    'slug' => 'legacy-school-pic-'.$legacyId,
                    'summary' => $this->buildSummary($content),
                    'content' => $content,
                    'cover_image' => $coverImage,
                    'author' => null,
                    'source' => (string) $site->name,
                    'status' => (int) ($row['status'] ?? 1) === 1 ? 'published' : 'draft',
                    'audit_status' => (int) ($row['status'] ?? 1) === 1 ? 'approved' : 'draft',
                    'is_top' => (int) ($row['istop'] ?? 0) === 1 ? 1 : 0,
                    'is_recommend' => 0,
                    'sort' => (int) ($row['orderid'] ?? 0),
                    'view_count' => 0,
                    'published_at' => $this->parseTimestamp($row['posttime'] ?? null),
                    'channel_ids' => [$channelId],
                ]);

                $summary['imported'][$created ? 'articles_created' : 'articles_updated']++;
            }

            FrontendPageCache::flushSite((int) $site->id);

            return $summary;
        });
    }

    protected function loadTables(string $sqlPath): array
    {
        if (! is_file($sqlPath)) {
            throw new RuntimeException('SQL 文件不存在：'.$sqlPath);
        }

        $sql = (string) file_get_contents($sqlPath);
        if ($sql === '') {
            throw new RuntimeException('SQL 文件为空：'.$sqlPath);
        }

        $schemas = [
            'school_article_category' => ['id', 'parentid', 'classname', 'issystem'],
            'school_article' => ['id', 'parentid', 'classid', 'title', 'url', 'content', 'orderid', 'posttime', 'issystem', 'seo_title', 'seo_keywords', 'seo_description'],
            'school_news_category' => ['id', 'parentid', 'classname', 'url', 'pic', 'orderid', 'issystem'],
            'school_news' => ['id', 'parentid', 'classid', 'title', 'editor', 'hits', 'content', 'pic', 'orderid', 'istop', 'posttime', 'seo_title', 'seo_keywords', 'seo_description'],
            'school_pic_category' => ['id', 'parentid', 'classname', 'description', 'pic', 'orderid', 'issystem'],
            'school_pic' => ['id', 'parentid', 'classid', 'name', 'type', 'price', 'stock', 'description', 'pic', 'orderid', 'istop', 'status', 'posttime', 'seo_title', 'seo_keywords', 'seo_description'],
        ];

        $tables = [];
        foreach ($schemas as $table => $columns) {
            $tables[$table] = $this->extractRows($sql, $table, $columns);
        }

        return $tables;
    }

    protected function extractRows(string $sql, string $table, array $columns): array
    {
        $rows = [];
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'` VALUES\s*/i';
        if (preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $start = (int) $match[1] + strlen((string) $match[0]);
            $end = $this->findStatementEnd($sql, $start);
            if ($end <= $start) {
                continue;
            }

            foreach ($this->parseTuples(substr($sql, $start, $end - $start)) as $tuple) {
                $values = $this->parseValues($tuple);
                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column] = $values[$index] ?? null;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function findStatementEnd(string $sql, int $start): int
    {
        $length = strlen($sql);
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
            } elseif ($char === ';') {
                return $i;
            }
        }

        return $length;
    }

    protected function parseTuples(string $valuesSql): array
    {
        $tuples = [];
        $length = strlen($valuesSql);
        $inString = false;
        $escaped = false;
        $depth = 0;
        $buffer = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSql[$i];
            if ($inString) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                if ($depth > 0) {
                    $buffer .= $char;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                    continue;
                }
                $buffer .= $char;
                continue;
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    protected function parseValues(string $tuple): array
    {
        $values = [];
        $length = strlen($tuple);
        $inString = false;
        $escaped = false;
        $buffer = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];
            if ($inString) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'") {
                $inString = true;
                $buffer .= $char;
                continue;
            }

            if ($char === ',') {
                $values[] = $this->parseValue($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $values[] = $this->parseValue($buffer);

        return $values;
    }

    protected function parseValue(string $value): mixed
    {
        $value = trim($value);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            return stripcslashes(substr($value, 1, -1));
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    protected function importChannels(int $siteId, array $rows, string $slugPrefix, string $type, array &$reservedSlugs, array &$summary): array
    {
        $map = [];
        $pending = [];

        foreach ($rows as $row) {
            $legacyKey = $this->legacyKey($row['id'] ?? null);
            $name = trim((string) ($row['classname'] ?? ''));
            if ($legacyKey === '' || $name === '') {
                continue;
            }
            $pending[$legacyKey] = $row;
        }

        $guard = 0;
        while ($pending !== [] && $guard < 1000) {
            $guard++;
            $changed = false;
            foreach ($pending as $legacyKey => $row) {
                $parentKey = $this->legacyKey($row['parentid'] ?? null);
                if ($parentKey !== '' && $parentKey !== '0' && ! isset($map[$parentKey])) {
                    continue;
                }

                $slug = $this->reserveSlug($slugPrefix.$legacyKey, $reservedSlugs);
                $parentId = $parentKey !== '' && $parentKey !== '0' ? ($map[$parentKey] ?? null) : null;
                [$channelId, $created] = $this->upsertChannel($siteId, [
                    'parent_id' => $parentId,
                    'name' => trim((string) ($row['classname'] ?? '')),
                    'slug' => $slug,
                    'type' => $type,
                    'path' => '/'.$slug,
                    'depth' => $parentId ? 1 : 0,
                    'sort' => (int) ($row['orderid'] ?? $row['id'] ?? 0),
                    'status' => 1,
                    'is_nav' => (int) ($row['issystem'] ?? 1) === 1 ? 1 : 0,
                    'list_template' => $type === 'page' ? null : 'list',
                    'detail_template' => $type === 'page' ? 'page' : 'detail',
                    'link_url' => null,
                    'link_target' => '_self',
                    'seo_title' => null,
                    'seo_keywords' => null,
                    'seo_description' => null,
                ]);

                $map[$legacyKey] = $channelId;
                $summary['imported'][$created ? 'channels_created' : 'channels_updated']++;
                unset($pending[$legacyKey]);
                $changed = true;
            }

            if (! $changed) {
                foreach ($pending as $legacyKey => $row) {
                    $slug = $this->reserveSlug($slugPrefix.$legacyKey, $reservedSlugs);
                    [$channelId, $created] = $this->upsertChannel($siteId, [
                        'parent_id' => null,
                        'name' => trim((string) ($row['classname'] ?? '')),
                        'slug' => $slug,
                        'type' => $type,
                        'path' => '/'.$slug,
                        'depth' => 0,
                        'sort' => (int) ($row['orderid'] ?? $row['id'] ?? 0),
                        'status' => 1,
                        'is_nav' => (int) ($row['issystem'] ?? 1) === 1 ? 1 : 0,
                        'list_template' => $type === 'page' ? null : 'list',
                        'detail_template' => $type === 'page' ? 'page' : 'detail',
                        'link_url' => null,
                        'link_target' => '_self',
                        'seo_title' => null,
                        'seo_keywords' => null,
                        'seo_description' => null,
                    ]);
                    $map[$legacyKey] = $channelId;
                    $summary['imported'][$created ? 'channels_created' : 'channels_updated']++;
                }
                break;
            }
        }

        return $map;
    }

    protected function upsertChannel(int $siteId, array $payload): array
    {
        $existing = DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', $payload['slug'])
            ->first(['id']);

        $now = now();
        if ($existing) {
            DB::table('channels')->where('id', $existing->id)->update([...$payload, 'updated_at' => $now]);

            return [(int) $existing->id, false];
        }

        return [
            (int) DB::table('channels')->insertGetId([
                'site_id' => $siteId,
                ...$payload,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            true,
        ];
    }

    protected function upsertContent(int $siteId, array $payload): array
    {
        $now = now();
        $contentPayload = [
            'site_id' => $siteId,
            'channel_id' => $payload['channel_id'],
            'type' => $payload['type'],
            'template_name' => $payload['template_name'],
            'title' => $payload['title'],
            'title_color' => null,
            'title_bold' => 0,
            'title_italic' => 0,
            'sub_title' => null,
            'slug' => $payload['slug'],
            'summary' => $payload['summary'],
            'content' => $payload['content'],
            'cover_image' => $payload['cover_image'],
            'author' => $payload['author'],
            'source' => $payload['source'],
            'status' => $payload['status'],
            'audit_status' => $payload['audit_status'],
            'is_top' => $payload['is_top'],
            'is_recommend' => $payload['is_recommend'],
            'sort' => $payload['sort'],
            'view_count' => $payload['view_count'],
            'published_at' => $payload['published_at'],
            'updated_by' => null,
            'updated_at' => $now,
        ];

        $existing = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('type', $payload['type'])
            ->where('slug', $payload['slug'])
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($existing) {
            DB::table('contents')->where('id', $existing->id)->update($contentPayload);
            $this->syncContentChannels((int) $existing->id, $payload['channel_ids'] ?? []);

            return [(int) $existing->id, false];
        }

        $createdAt = $payload['published_at'] ?? $now;
        $contentId = (int) DB::table('contents')->insertGetId([
            ...$contentPayload,
            'created_by' => null,
            'created_at' => $createdAt,
        ]);

        DB::table('content_revisions')->insert([
            'content_id' => $contentId,
            'site_id' => $siteId,
            'version_no' => 1,
            'title' => $payload['title'],
            'title_color' => null,
            'title_bold' => 0,
            'title_italic' => 0,
            'summary' => $payload['summary'],
            'content' => $payload['content'],
            'operator_id' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->syncContentChannels($contentId, $payload['channel_ids'] ?? []);

        return [$contentId, true];
    }

    protected function syncContentChannels(int $contentId, array $channelIds): void
    {
        DB::table('content_channels')->where('content_id', $contentId)->delete();

        $rows = collect($channelIds)
            ->filter(fn ($channelId) => (int) $channelId > 0)
            ->map(fn ($channelId): int => (int) $channelId)
            ->unique()
            ->map(fn (int $channelId): array => [
                'content_id' => $contentId,
                'channel_id' => $channelId,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('content_channels')->insert($rows);
        }
    }

    protected function ensureFallbackChannel(int $siteId, string $slug, string $name, string $type, array &$summary): int
    {
        [$id, $created] = $this->upsertChannel($siteId, [
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'path' => '/'.$slug,
            'depth' => 0,
            'sort' => 999,
            'status' => 1,
            'is_nav' => 0,
            'list_template' => $type === 'page' ? null : 'list',
            'detail_template' => $type === 'page' ? 'page' : 'detail',
            'link_url' => null,
            'link_target' => '_self',
            'seo_title' => null,
            'seo_keywords' => null,
            'seo_description' => null,
        ]);
        $summary['imported'][$created ? 'channels_created' : 'channels_updated']++;

        return $id;
    }

    protected function normalizeContentHtml(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/_x000[dD]_/', "\r", $decoded) ?? $decoded;
        $decoded = $this->rewriteLegacyAssetUrls($decoded);
        $decoded = $this->convertBilibiliIframes($decoded);

        return ContentHtmlSanitizer::sanitize($decoded);
    }

    protected function rewriteLegacyAssetUrls(string $html): string
    {
        return preg_replace_callback(
            '#(?P<prefix>\b(?:src|href)\s*=\s*["\'])(?P<url>[^"\']+)(?P<suffix>["\'])#i',
            function (array $matches): string {
                $url = html_entity_decode((string) $matches['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $rewritten = $this->normalizeLegacyAssetPath($url);

                return $matches['prefix'].($rewritten ?? $url).$matches['suffix'];
            },
            $html
        ) ?? $html;
    }

    protected function convertBilibiliIframes(string $html): string
    {
        return preg_replace_callback(
            '#<iframe\b[^>]*\bsrc=["\'](?P<src>//player\.bilibili\.com/player\.html\?[^"\']+)["\'][^>]*>\s*</iframe>#i',
            function (array $matches): string {
                $src = 'https:'.ltrim((string) $matches['src'], '/');
                $query = [];
                parse_str((string) parse_url($src, PHP_URL_QUERY), $query);

                $aid = preg_replace('/\D+/', '', (string) ($query['aid'] ?? '')) ?? '';
                $cid = preg_replace('/\D+/', '', (string) ($query['cid'] ?? '')) ?? '';
                $page = preg_replace('/\D+/', '', (string) ($query['p'] ?? '1')) ?? '1';
                $bvid = preg_replace('/[^A-Za-z0-9]+/', '', (string) ($query['bvid'] ?? '')) ?? '';

                if ($aid === '' || $cid === '' || $bvid === '') {
                    return '';
                }

                return sprintf(
                    '<div class="bilibili-video-embed bilibili-video-embed--align-center mceNonEditable" data-bilibili-video="1" data-aid="%s" data-bvid="%s" data-cid="%s" data-p="%s" data-width="100%%" data-height="450" data-align="center"></div>',
                    htmlspecialchars($aid, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars($bvid, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars($cid, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars($page !== '' ? $page : '1', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                );
            },
            $html
        ) ?? $html;
    }

    protected function normalizeLegacyAssetPath(string $path): ?string
    {
        $path = trim(html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($path === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $clean = preg_replace('~[?#].*$~', '', $path) ?? $path;
        $basename = basename($clean);

        if ($basename === '' || preg_match('/\.(?:jpe?g|png|gif|webp|bmp|svg|swf|pdf)$/i', $basename) !== 1) {
            return null;
        }

        if (
            preg_match('#(?:^|/)Uploads/Editor/#i', $clean) === 1
            || preg_match('/^[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp|bmp|svg|swf|pdf)$/i', $clean) === 1
        ) {
            return self::LEGACY_ASSET_PREFIX.$basename;
        }

        return null;
    }

    protected function assetSummary(): array
    {
        $htmlRefs = [];
        $coverRefs = [];
        foreach (['school_article', 'school_news', 'school_pic'] as $table) {
            foreach (($this->tables[$table] ?? []) as $row) {
                foreach (['content', 'description'] as $field) {
                    $html = html_entity_decode((string) ($row[$field] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($html === '') {
                        continue;
                    }
                    if (preg_match_all('#\b(?:src|href)\s*=\s*["\']([^"\']+\.(?:jpe?g|png|gif|webp|bmp|svg|swf|pdf))["\']#i', $html, $matches)) {
                        foreach ($matches[1] as $url) {
                            $normalized = $this->normalizeLegacyAssetPath((string) $url);
                            if ($normalized !== null) {
                                $htmlRefs[$normalized] = true;
                            }
                        }
                    }
                }

                $cover = $this->normalizeLegacyAssetPath((string) ($row['pic'] ?? ''));
                if ($cover !== null) {
                    $coverRefs[$cover] = true;
                }
            }
        }

        return [
            'html_refs' => count($htmlRefs),
            'cover_refs' => count($coverRefs),
            'total_unique_refs' => count($htmlRefs + $coverRefs),
            'target_prefix' => self::LEGACY_ASSET_PREFIX,
        ];
    }

    protected function reserveSlug(string $slug, array &$reserved): string
    {
        $slug = substr($slug, 0, 100);
        $candidate = $slug;
        $index = 2;

        while (isset($reserved[mb_strtolower($candidate)])) {
            $suffix = '-'.$index;
            $candidate = substr($slug, 0, max(1, 100 - strlen($suffix))).$suffix;
            $index++;
        }

        $reserved[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    protected function legacyId(mixed $value, int $index): string
    {
        $key = $this->legacyKey($value);

        return $key !== '' && $key !== '0' ? $key : 'row-'.($index + 1);
    }

    protected function legacyKey(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return preg_match('/^\d+(?:\.0+)?$/', $text) === 1 ? (string) (int) $text : $text;
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        $timestamp = (int) ($value ?? 0);
        if ($timestamp <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    protected function buildSummary(string $html): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_substr($plain, 0, 140);
    }

    protected function imageOnlyContent(string $title, string $coverImage): string
    {
        return sprintf(
            '<p><img src="%s" alt="%s"></p>',
            htmlspecialchars($coverImage, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
