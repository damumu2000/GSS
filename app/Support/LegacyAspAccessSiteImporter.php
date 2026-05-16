<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class LegacyAspAccessSiteImporter
{
    public function import(string $siteKey, string $siteName, string $sourceDir, bool $execute = false): array
    {
        $site = DB::table('sites')
            ->where('site_key', trim($siteKey))
            ->first(['id', 'name', 'site_key']);

        $source = $this->loadSource($sourceDir);
        $summary = [
            'site' => $site
                ? [
                    'id' => (int) $site->id,
                    'name' => (string) $site->name,
                    'site_key' => (string) $site->site_key,
                    'created' => false,
                ]
                : [
                    'id' => null,
                    'name' => trim($siteName),
                    'site_key' => trim($siteKey),
                    'created' => false,
                ],
            'source' => $sourceDir,
            'dry_run' => ! $execute,
            'counts' => [
                'type_d' => count($source['type_d']),
                'type' => count($source['type']),
                'about' => count($source['about']),
                'news' => $source['news_count'],
                'news_content' => $source['news_content_count'],
            ],
            'imported' => [
                'channels_created' => 0,
                'channels_updated' => 0,
                'pages_created' => 0,
                'pages_updated' => 0,
                'articles_created' => 0,
                'articles_updated' => 0,
                'articles_skipped' => 0,
            ],
            'warnings' => [],
        ];

        if (! $execute) {
            if (! $site) {
                $summary['warnings'][] = '目标站点当前不存在，正式执行时会自动创建新站点并初始化默认模板。';
            }

            if ($summary['counts']['news_content'] === 0) {
                $summary['warnings'][] = '当前数据目录未提供 News_Content.xml，导入时将仅使用 News.xml 中自带正文。';
            } elseif ($summary['counts']['news'] !== $summary['counts']['news_content']) {
                $summary['warnings'][] = sprintf(
                    'News 主表 %d 条，News_Content 正文 %d 条，导入时将按 ID 合并并跳过缺正文记录。',
                    $summary['counts']['news'],
                    $summary['counts']['news_content'],
                );
            }

            return $summary;
        }

        return DB::transaction(function () use ($site, $siteKey, $siteName, $source, $sourceDir, $summary): array {
            if (! $site) {
                $site = $this->createSite(trim($siteKey), trim($siteName));
                $summary['site'] = [
                    'id' => (int) $site->id,
                    'name' => (string) $site->name,
                    'site_key' => (string) $site->site_key,
                    'created' => true,
                ];
            }

            $parentChannelMap = [];
            $reservedChannelSlugs = [];

            foreach ($source['type_d'] as $row) {
                $legacyId = $this->legacyKey($this->rowValue($row, ['T_ID', 'Type_ID']));
                $name = trim((string) $this->rowValue($row, ['T_Name', 'Type_Name'], ''));

                if ($this->legacyKeyIsEmpty($legacyId) || $name === '' || ! $this->isLegacyArticleChannelRow($row)) {
                    continue;
                }

                $slug = $this->reserveLegacyChannelSlug(
                    $this->legacyChannelSlug($row, 'legacy-group-'.$legacyId),
                    'legacy-group-'.$legacyId,
                    $reservedChannelSlugs
                );

                [$channelId, $created] = $this->upsertChannel(
                    (int) $site->id,
                    [
                        'parent_id' => null,
                        'name' => $name,
                        'slug' => $slug,
                        'type' => 'list',
                        'path' => '/'.$slug,
                        'depth' => 0,
                        'sort' => $this->legacySortValue($row, $legacyId),
                        'status' => 1,
                        'is_nav' => 1,
                        'list_template' => 'list',
                        'detail_template' => 'detail',
                        'link_url' => null,
                        'link_target' => '_self',
                    ]
                );

                $parentChannelMap[$legacyId] = $channelId;
                $summary['imported'][$created ? 'channels_created' : 'channels_updated']++;
            }

            $articleChannelMap = [];

            foreach ($source['type'] as $row) {
                $legacyId = $this->legacyKey($this->rowValue($row, ['T_ID', 'Type_ID']));
                $parentLegacyId = $this->legacyKey($this->rowValue($row, ['T_dlei', 'T_Dlei', 'dalei', 'Type_dlei']));
                $name = trim((string) $this->rowValue($row, ['T_Name', 'Type_Name'], ''));

                if ($this->legacyKeyIsEmpty($legacyId) || $name === '' || ! $this->isLegacyArticleChannelRow($row)) {
                    continue;
                }

                $parentChannelId = $parentChannelMap[$parentLegacyId] ?? null;
                $slug = $this->reserveLegacyChannelSlug(
                    $this->legacyChannelSlug($row, 'legacy-list-'.$legacyId),
                    'legacy-list-'.$legacyId,
                    $reservedChannelSlugs
                );

                [$channelId, $created] = $this->upsertChannel(
                    (int) $site->id,
                    [
                        'parent_id' => $parentChannelId,
                        'name' => $name,
                        'slug' => $slug,
                        'type' => 'list',
                        'path' => '/'.$slug,
                        'depth' => $parentChannelId ? 1 : 0,
                        'sort' => $this->legacySortValue($row, $legacyId),
                        'status' => 1,
                        'is_nav' => 1,
                        'list_template' => 'list',
                        'detail_template' => 'detail',
                        'link_url' => null,
                        'link_target' => '_self',
                    ]
                );

                $articleChannelMap[$legacyId] = $channelId;
                $summary['imported'][$created ? 'channels_created' : 'channels_updated']++;
            }

            $pageParentChannelId = null;

            foreach ($source['about'] as $row) {
                $legacyId = $this->intValue($row['About_ID'] ?? null);
                $name = trim((string) ($row['About_Name'] ?? ''));
                $content = $this->extractImportedHtml((string) ($row['About_content'] ?? $row['About_Content'] ?? ''));

                if ($legacyId <= 0 || $name === '') {
                    continue;
                }

                if ($pageParentChannelId === null) {
                    [$pageParentChannelId, $pageParentCreated] = $this->upsertChannel(
                        (int) $site->id,
                        [
                            'parent_id' => null,
                            'name' => '单页内容',
                            'slug' => 'legacy-pages',
                            'type' => 'page',
                            'path' => '/legacy-pages',
                            'depth' => 0,
                            'sort' => 0,
                            'status' => 1,
                            'is_nav' => 1,
                            'list_template' => null,
                            'detail_template' => 'page',
                            'link_url' => null,
                            'link_target' => '_self',
                        ]
                    );
                    $summary['imported'][$pageParentCreated ? 'channels_created' : 'channels_updated']++;
                }

                [, $contentCreated] = $this->upsertContent(
                    (int) $site->id,
                    [
                        'channel_id' => $pageParentChannelId,
                        'type' => 'page',
                        'template_name' => 'page',
                        'title' => $name,
                        'slug' => 'legacy-page-content-'.$legacyId,
                        'summary' => $this->buildSummary($content),
                        'content' => $content,
                        'cover_image' => null,
                        'author' => null,
                        'source' => (string) $site->name,
                        'status' => 'published',
                        'audit_status' => 'approved',
                        'is_top' => 0,
                        'is_recommend' => 0,
                        'sort' => $legacyId,
                        'view_count' => 0,
                        'published_at' => null,
                        'channel_ids' => [$pageParentChannelId],
                    ]
                );

                $summary['imported'][$contentCreated ? 'pages_created' : 'pages_updated']++;
            }

            $fallbackArticleChannelId = null;
            $newsContentLookupTable = $this->prepareNewsContentLookup($source['news_content_path']);

            foreach ($this->readXmlRecordRows($source['news_path'], 'News') as $row) {
                $legacyId = $this->intValue($row['News_ID'] ?? null);
                $title = trim((string) ($row['News_Title'] ?? ''));
                $legacyChannelIds = $this->parseLegacyChannelIds($row['News_Type'] ?? null);

                if ($legacyId <= 0 || $title === '') {
                    continue;
                }

                $channelIds = collect($legacyChannelIds)
                    ->map(fn (string $legacyChannelId): ?int => $articleChannelMap[$legacyChannelId] ?? null)
                    ->filter(fn (?int $channelId): bool => (int) $channelId > 0)
                    ->unique()
                    ->values()
                    ->all();

                $channelId = $channelIds[0] ?? null;
                if (! $channelId) {
                    if ($fallbackArticleChannelId === null) {
                        [$fallbackArticleChannelId, $fallbackCreated] = $this->upsertChannel(
                            (int) $site->id,
                            [
                                'parent_id' => null,
                                'name' => '异常内容',
                                'slug' => 'legacy-exception-content',
                                'type' => 'list',
                                'path' => '/legacy-exception-content',
                                'depth' => 0,
                                'sort' => 0,
                                'status' => 1,
                                'is_nav' => 1,
                                'list_template' => 'list',
                                'detail_template' => 'detail',
                                'link_url' => null,
                                'link_target' => '_self',
                            ]
                        );
                        $summary['imported'][$fallbackCreated ? 'channels_created' : 'channels_updated']++;
                    }

                    $channelId = $fallbackArticleChannelId;
                    $channelIds = [$fallbackArticleChannelId];
                    $summary['warnings'][] = sprintf(
                        '文章 %d《%s》未找到对应栏目 ID %s，已导入到“异常内容”。',
                        $legacyId,
                        $title,
                        trim((string) ($row['News_Type'] ?? ''))
                    );
                }

                $rawContent = (string) ($row['News_Content'] ?? '');
                if (trim($rawContent) === '') {
                    $rawContent = $this->newsContentFromLookup($newsContentLookupTable, $legacyId);
                }

                if (trim($rawContent) === '') {
                    $summary['imported']['articles_skipped']++;
                    $summary['warnings'][] = sprintf('文章 %d《%s》缺少正文，已跳过。', $legacyId, $title);
                    continue;
                }

                $content = $this->extractImportedHtml($rawContent);
                $publishedAt = $this->parseLegacyDate($row['News_Date'] ?? null);

                [, $created] = $this->upsertContent(
                    (int) $site->id,
                    [
                        'channel_id' => $channelId,
                        'type' => 'article',
                        'template_name' => 'detail',
                        'title' => $title,
                        'slug' => 'legacy-news-'.$legacyId,
                        'summary' => $this->buildSummary($content),
                        'content' => $content,
                        'cover_image' => $this->normalizeLegacyAssetPath((string) ($row['News_Pic'] ?? '')),
                        'author' => null,
                        'source' => (string) $site->name,
                        'status' => 'published',
                        'audit_status' => 'approved',
                        'is_top' => 0,
                        'is_recommend' => $this->intValue($row['tuijian'] ?? null) === 1 ? 1 : 0,
                        'sort' => $legacyId,
                        'view_count' => $this->intValue($row['News_count'] ?? null),
                        'published_at' => $publishedAt,
                        'channel_ids' => $channelIds,
                    ]
                );

                $summary['imported'][$created ? 'articles_created' : 'articles_updated']++;
            }

            $summary['warnings'][] = '当前版本未迁移旧站图片与附件文件，仅保留原始路径字符串。';
            $summary['source'] = $sourceDir;

            return $summary;
        });
    }

    protected function loadSource(string $sourceDir): array
    {
        $dir = rtrim($sourceDir, DIRECTORY_SEPARATOR);

        $typeDPath = $dir.DIRECTORY_SEPARATOR.'Type_D.xlsx';
        $typePath = $dir.DIRECTORY_SEPARATOR.'Type.xlsx';
        $aboutPath = $dir.DIRECTORY_SEPARATOR.'About.xlsx';
        $newsPath = $dir.DIRECTORY_SEPARATOR.'News.xml';
        $newsContentPath = $dir.DIRECTORY_SEPARATOR.'News_Content.xml';

        foreach ([$typeDPath, $typePath, $aboutPath, $newsPath] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException('缺少导入文件：'.$path);
            }
        }

        return [
            'type_d' => $this->readSpreadsheetRows($typeDPath),
            'type' => $this->readSpreadsheetRows($typePath),
            'about' => $this->readSpreadsheetRows($aboutPath),
            'news_path' => $newsPath,
            'news_content_path' => is_file($newsContentPath) ? $newsContentPath : null,
            'news_count' => $this->countXmlRecords($newsPath, 'News'),
            'news_content_count' => is_file($newsContentPath) ? $this->countXmlRecords($newsContentPath, 'News_Content') : 0,
        ];
    }

    protected function readSpreadsheetRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);

            if ($index === 0) {
                $headers = array_map(fn ($value) => trim((string) $value), $values);
                continue;
            }

            if ($headers === [] || $this->rowIsEmpty($values)) {
                continue;
            }

            $item = [];
            foreach ($headers as $offset => $header) {
                if ($header === '') {
                    continue;
                }

                $item[$header] = $values[$offset] ?? null;
            }

            if ($item !== []) {
                $items[] = $item;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $items;
    }

    protected function readXmlRecordRows(string $path, string $recordTag): iterable
    {
        foreach ($this->readXmlRecordSegments($path, $recordTag) as $recordXml) {
            $row = $this->parseXmlRecordFields($recordXml, $recordTag);
            if ($row !== []) {
                yield $row;
            }
        }
    }

    protected function countXmlRecords(string $path, string $recordTag): int
    {
        $count = 0;

        foreach ($this->readXmlRecordSegments($path, $recordTag) as $_) {
            $count++;
        }

        return $count;
    }

    protected function readXmlRecordSegments(string $path, string $recordTag): iterable
    {
        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException('无法读取 XML 文件：'.$path);
        }

        $endTag = '</'.$recordTag.'>';
        $endLength = strlen($endTag);
        $startPattern = '#<'.preg_quote($recordTag, '#').'(?:\s[^>]*)?>#i';
        $buffer = '';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);
                if ($chunk === false || $chunk === '') {
                    continue;
                }

                $buffer .= $chunk;

                while (($endPos = stripos($buffer, $endTag)) !== false) {
                    $beforeEnd = substr($buffer, 0, $endPos);
                    if (preg_match_all($startPattern, $beforeEnd, $matches, PREG_OFFSET_CAPTURE) !== false && $matches[0] !== []) {
                        $lastMatch = $matches[0][array_key_last($matches[0])];
                        $startPos = (int) $lastMatch[1];

                        yield substr($buffer, $startPos, $endPos + $endLength - $startPos);
                    }

                    $buffer = substr($buffer, $endPos + $endLength);
                }

                if (strlen($buffer) > 8 * 1024 * 1024 && preg_match_all($startPattern, $buffer, $matches, PREG_OFFSET_CAPTURE) !== false && $matches[0] !== []) {
                    $lastMatch = $matches[0][array_key_last($matches[0])];
                    $buffer = substr($buffer, (int) $lastMatch[1]);
                }
            }
        } finally {
            fclose($handle);
        }

    }

    protected function parseXmlRecordFields(string $recordXml, string $recordTag): array
    {
        $innerXml = preg_replace('#^<'.preg_quote($recordTag, '#').'\b[^>]*>|</'.preg_quote($recordTag, '#').'>\s*$#s', '', trim($recordXml)) ?? $recordXml;
        $row = [];

        preg_match_all('#<([A-Za-z0-9_]+)(?:\s[^>]*)?>(.*?)</\\1>#s', $innerXml, $fieldMatches, PREG_SET_ORDER);
        foreach ($fieldMatches as $fieldMatch) {
            $row[$fieldMatch[1]] = $this->decodeXmlFieldValue((string) $fieldMatch[2]);
        }

        return $row;
    }

    protected function decodeXmlFieldValue(string $value): string
    {
        $value = trim($value);

        if (preg_match('#^<!\[CDATA\[(.*)]]>$#s', $value, $match) === 1) {
            $value = (string) $match[1];
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function prepareNewsContentLookup(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $table = 'legacy_news_content_import';

        DB::statement("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
        DB::statement("CREATE TEMPORARY TABLE `{$table}` (`legacy_id` INT NOT NULL PRIMARY KEY, `content` LONGTEXT NULL)");

        $batch = [];
        foreach ($this->readXmlRecordRows($path, 'News_Content') as $row) {
            $legacyId = $this->intValue($row['ID'] ?? null);
            if ($legacyId <= 0) {
                continue;
            }

            $batch[] = [
                'legacy_id' => $legacyId,
                'content' => (string) ($row['Content'] ?? ''),
            ];

            if (count($batch) >= 500) {
                DB::table($table)->upsert($batch, ['legacy_id'], ['content']);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table($table)->upsert($batch, ['legacy_id'], ['content']);
        }

        return $table;
    }

    protected function newsContentFromLookup(?string $table, int $legacyId): string
    {
        if ($table === null || $legacyId <= 0) {
            return '';
        }

        return (string) (DB::table($table)->where('legacy_id', $legacyId)->value('content') ?? '');
    }

    protected function extractImportedHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/_x000[dD]_/', "\r", $decoded) ?? $decoded;
    }

    protected function upsertChannel(int $siteId, array $payload): array
    {
        $existing = DB::table('channels')
            ->where('site_id', $siteId)
            ->where('slug', $payload['slug'])
            ->first(['id']);

        $now = now();

        if ($existing) {
            DB::table('channels')
                ->where('id', $existing->id)
                ->update([
                    ...$payload,
                    'updated_at' => $now,
                ]);

            return [(int) $existing->id, false];
        }

        $channelId = (int) DB::table('channels')->insertGetId([
            'site_id' => $siteId,
            ...$payload,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$channelId, true];
    }

    protected function upsertContent(int $siteId, array $payload): array
    {
        $existing = DB::table('contents')
            ->where('site_id', $siteId)
            ->where('type', $payload['type'])
            ->where('slug', $payload['slug'])
            ->whereNull('deleted_at')
            ->first(['id']);

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

        if ($existing) {
            DB::table('contents')
                ->where('id', $existing->id)
                ->update($contentPayload);

            $contentId = (int) $existing->id;
            $this->syncContentChannels($contentId, $payload['channel_ids'] ?? []);

            return [$contentId, false];
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

    protected function createSite(string $siteKey, string $siteName): object
    {
        if ($siteKey === '' || $siteName === '') {
            throw new RuntimeException('新站点标识和名称不能为空。');
        }

        $now = now();
        $siteId = (int) DB::table('sites')->insertGetId([
            'name' => $siteName,
            'site_key' => $siteKey,
            'status' => 1,
            'template_limit' => 1,
            'active_site_template_id' => null,
            'seo_title' => $siteName,
            'opened_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $siteAdminUserId = (int) config('cms.super_admin_user_id', 1);
        $templateId = (int) DB::table('site_templates')->insertGetId([
            'site_id' => $siteId,
            'name' => '默认模板',
            'template_key' => 'default',
            'status' => 1,
            'created_by' => $siteAdminUserId > 0 ? $siteAdminUserId : null,
            'updated_by' => $siteAdminUserId > 0 ? $siteAdminUserId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sites')->where('id', $siteId)->update([
            'active_site_template_id' => $templateId,
            'updated_at' => $now,
        ]);

        ThemeTemplateScaffold::copyDefaultFiles(Site::siteTemplateRoot($siteKey, 'default'));

        $siteAdminRoleId = (int) DB::table('site_roles')
            ->where('code', 'site_admin')
            ->whereNull('site_id')
            ->value('id');

        if ($siteAdminRoleId > 0) {
            $permissionIds = DB::table('site_permissions')->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('site_role_permissions')->updateOrInsert(
                    [
                        'site_id' => $siteId,
                        'role_id' => $siteAdminRoleId,
                        'permission_id' => (int) $permissionId,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            if ($siteAdminUserId > 0) {
                DB::table('site_user_roles')->updateOrInsert(
                    [
                        'site_id' => $siteId,
                        'user_id' => $siteAdminUserId,
                    ],
                    [
                        'role_id' => $siteAdminRoleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        return DB::table('sites')
            ->where('id', $siteId)
            ->first(['id', 'name', 'site_key']);
    }

    protected function syncContentChannels(int $contentId, array $channelIds): void
    {
        DB::table('content_channels')->where('content_id', $contentId)->delete();

        if ($channelIds === []) {
            return;
        }

        $now = now();
        DB::table('content_channels')->insert(
            collect($channelIds)
                ->filter(fn ($channelId) => (int) $channelId > 0)
                ->unique()
                ->values()
                ->map(fn ($channelId) => [
                    'content_id' => $contentId,
                    'channel_id' => (int) $channelId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    protected function buildSummary(string $html): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_substr($plain, 0, 140);
    }

    protected function parseLegacyDate(mixed $value): ?Carbon
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeLegacyAssetPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * @return array<int, string>
     */
    protected function parseLegacyChannelIds(mixed $value): array
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/[,\x{FF0C}\s]+/u', $raw) ?: [])
            ->map(fn (string $item): string => $this->legacyKey($item))
            ->filter(fn (string $id): bool => ! $this->legacyKeyIsEmpty($id))
            ->unique()
            ->values()
            ->all();
    }

    protected function isLegacyArticleChannelRow(array $row): bool
    {
        $type = $this->intValue($this->rowValue($row, ['T_type', 'Type_type']));

        return $type === 0 || $type === 2;
    }

    protected function legacyChannelSlug(array $row, string $fallback): string
    {
        $slug = $this->normalizeLegacyChannelEnSlug((string) $this->rowValue($row, ['en'], ''));

        return $slug !== '' ? $slug : $fallback;
    }

    protected function normalizeLegacyChannelEnSlug(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', '-', $value) ?? $value;
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? $value;
        $value = trim($value, '-_');

        if ($value === '') {
            return '';
        }

        return trim(substr($value, 0, 20), '-_');
    }

    /**
     * @param  array<string, bool>  $reserved
     */
    protected function reserveLegacyChannelSlug(string $slug, string $fallback, array &$reserved): string
    {
        $reservedKey = mb_strtolower($slug);

        if (! isset($reserved[$reservedKey])) {
            $reserved[$reservedKey] = true;

            return $slug;
        }

        $candidate = $fallback;
        $index = 2;
        while (isset($reserved[mb_strtolower($candidate)])) {
            $suffix = '-'.$index;
            $candidate = substr($fallback, 0, max(1, 20 - strlen($suffix))).$suffix;
            $index++;
        }

        $reserved[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    protected function legacyKey(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        $text = trim((string) $value);

        if ($text !== '' && preg_match('/^\d+(?:\.0+)?$/', $text) === 1) {
            return (string) (int) $text;
        }

        return $text;
    }

    protected function legacyKeyIsEmpty(string $value): bool
    {
        return $value === '' || $value === '0';
    }

    protected function legacySortValue(array $row, string $legacyId): int
    {
        $sort = $this->intValue($this->rowValue($row, ['shunxu', 'sort', 'Sort']));

        if ($sort !== 0) {
            return $sort;
        }

        return $this->intValue($legacyId);
    }

    protected function intValue(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function rowValue(array $row, array|string $keys, mixed $default = null): mixed
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        $normalized = [];
        foreach ($row as $rowKey => $value) {
            $normalized[mb_strtolower(trim((string) $rowKey))] = $value;
        }

        foreach ($keys as $key) {
            $lookupKey = mb_strtolower(trim((string) $key));
            if (array_key_exists($lookupKey, $normalized)) {
                return $normalized[$lookupKey];
            }
        }

        return $default;
    }

    protected function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
