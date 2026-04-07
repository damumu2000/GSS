<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseInspector
{
    protected ?Collection $tableCache = null;

    protected ?array $overviewCache = null;

    public function __construct(
        protected DatabaseHealth $databaseHealth,
    ) {
    }

    public function overview(): array
    {
        if ($this->overviewCache !== null) {
            return $this->overviewCache;
        }

        $tables = $this->tables();
        $largeTables = $tables->filter(fn (array $table): bool => ($table['row_count'] ?? 0) >= 5000)->count();
        $missingPrimary = $tables->filter(fn (array $table): bool => ! $table['has_primary'])->count();
        $missingTimestamps = $tables->filter(fn (array $table): bool => ! $table['has_created_at'] && ! $table['has_updated_at'])->count();

        $suggestions = [];

        if ($this->databaseHealth->hasPendingMigrations()) {
            $suggestions[] = '当前存在未执行迁移，建议先完成数据库升级，避免结构与代码不一致。';
        }

        if ($missingPrimary > 0) {
            $suggestions[] = sprintf('当前有 %d 张表未识别到主键，建议优先排查业务主表是否缺少主键定义。', $missingPrimary);
        }

        if ($largeTables > 0) {
            $suggestions[] = sprintf('当前有 %d 张数据量较大的表，建议重点关注常用筛选字段是否已建立索引。', $largeTables);
        }

        if ($missingTimestamps > 0) {
            $suggestions[] = sprintf('当前有 %d 张表未包含 created_at / updated_at 字段，如属于业务表可评估是否补齐。', $missingTimestamps);
        }

        if ($suggestions === []) {
            $suggestions[] = '数据库结构整体正常，当前未发现明显的迁移风险或结构异常。';
        }

        return $this->overviewCache = [
            'driver' => (string) DB::getDriverName(),
            'database_version' => $this->databaseVersion(),
            'database_name' => (string) (DB::connection()->getDatabaseName() ?: '未识别'),
            'table_count' => $tables->count(),
            'pending_migrations' => $this->databaseHealth->hasPendingMigrations(),
            'large_table_count' => $largeTables,
            'missing_primary_count' => $missingPrimary,
            'missing_timestamp_count' => $missingTimestamps,
            'suggestions' => $suggestions,
        ];
    }

    public function tables(): Collection
    {
        if ($this->tableCache instanceof Collection) {
            return $this->tableCache;
        }

        $builder = DB::connection()->getSchemaBuilder();
        $rawTables = collect($builder->getTables());
        $rowCounts = $this->estimatedRowCounts($rawTables);

        return $this->tableCache = $rawTables
            ->map(function (array $table) use ($builder, $rowCounts): array {
                $name = (string) ($table['name'] ?? $table['table_name'] ?? '');
                $columns = collect($builder->getColumns($name));
                $indexes = collect($builder->getIndexes($name));
                $primaryIndex = $indexes->first(fn (array $index): bool => (bool) ($index['primary'] ?? false));
                $primaryColumns = collect($primaryIndex['columns'] ?? [])->map(fn ($column) => (string) $column)->values()->all();
                $columnNames = $columns->pluck('name')->map(fn ($column) => (string) $column)->all();

                return [
                    'name' => $name,
                    'label' => $this->tableLabel($name),
                    'description' => $this->tableDescription($name),
                    'schema' => (string) ($table['schema'] ?? ''),
                    'engine' => (string) ($table['engine'] ?? ''),
                    'size' => isset($table['size']) ? (int) $table['size'] : null,
                    'column_count' => $columns->count(),
                    'row_count' => (int) ($rowCounts[$name] ?? 0),
                    'has_primary' => $primaryColumns !== [],
                    'primary_key' => $primaryColumns !== [] ? implode(', ', $primaryColumns) : '未识别',
                    'has_created_at' => in_array('created_at', $columnNames, true),
                    'has_updated_at' => in_array('updated_at', $columnNames, true),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function hasTable(string $table): bool
    {
        return DB::connection()->getSchemaBuilder()->hasTable($table);
    }

    public function detail(string $table, int $page = 1, int $perPage = 10): array
    {
        abort_unless($this->hasTable($table), 404);

        $builder = DB::connection()->getSchemaBuilder();
        $columns = collect($builder->getColumns($table));
        $indexes = collect($builder->getIndexes($table));
        $primaryIndex = $indexes->first(fn (array $index): bool => (bool) ($index['primary'] ?? false));
        $primaryColumns = collect($primaryIndex['columns'] ?? [])->map(fn ($column) => (string) $column)->values()->all();
        $indexByColumn = $this->indexMapByColumn($indexes);
        $columnNames = $columns->pluck('name')->map(fn ($column) => (string) $column)->all();

        $columnDefinitions = $columns->map(function (array $column) use ($primaryColumns, $indexByColumn): array {
            $name = (string) $column['name'];

            return [
                'name' => $name,
                'type' => (string) ($column['type'] ?? $column['type_name'] ?? '未知'),
                'nullable' => (bool) ($column['nullable'] ?? false),
                'default' => $column['default'],
                'primary' => in_array($name, $primaryColumns, true),
                'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                'indexes' => $indexByColumn[$name] ?? [],
            ];
        })->values();

        $baseQuery = DB::table($table);
        $total = (int) $baseQuery->count();
        $rows = $this->previewRows($table, $primaryColumns, $columnNames, $page, $perPage);

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return [
            'table' => $table,
            'columns' => $columnDefinitions,
            'indexes' => $indexes->values(),
            'primary_key' => $primaryColumns !== [] ? implode(', ', $primaryColumns) : '未识别',
            'row_count' => $total,
            'paginator' => $paginator,
            'recommendations' => $this->tableRecommendations($table, $columnNames, $indexes, $primaryColumns, $total),
        ];
    }

    protected function previewRows(string $table, array $primaryColumns, array $columnNames, int $page, int $perPage): Collection
    {
        $query = DB::table($table);

        if (count($primaryColumns) === 1) {
            $query->orderBy($primaryColumns[0], 'desc');
        } elseif (in_array('id', $columnNames, true)) {
            $query->orderBy('id', 'desc');
        } elseif ($columnNames !== []) {
            $query->orderBy($columnNames[0]);
        }

        return $query
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($row): array {
                $values = [];

                foreach ((array) $row as $key => $value) {
                    $values[(string) $key] = $this->normalizeCellValue((string) $key, $value);
                }

                return $values;
            });
    }

    protected function normalizeCellValue(string $key, mixed $value): string
    {
        $lowerKey = strtolower($key);

        if (in_array($lowerKey, ['password', 'remember_token', 'token', 'secret', 'api_key', 'access_token', 'refresh_token'], true)) {
            return '******';
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_resource($value)) {
            return '[binary]';
        }

        if (is_array($value) || is_object($value)) {
            return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[complex]', 120, '...');
        }

        $stringValue = trim((string) $value);

        return Str::limit($stringValue === '' ? '—' : $stringValue, 120, '...');
    }

    protected function tableRecommendations(string $table, array $columnNames, Collection $indexes, array $primaryColumns, int $rowCount): array
    {
        $suggestions = [];

        if ($primaryColumns === []) {
            $suggestions[] = '未识别到主键，建议确认该表是否为业务主表并补充主键定义。';
        }

        if ($rowCount >= 5000 && in_array('site_id', $columnNames, true) && ! $this->hasIndexForColumn($indexes, 'site_id')) {
            $suggestions[] = '当前表记录较多，建议为 site_id 建立索引以优化站点范围查询。';
        }

        if ($rowCount >= 5000 && in_array('status', $columnNames, true) && ! $this->hasIndexForColumn($indexes, 'status')) {
            $suggestions[] = '当前表记录较多，建议评估 status 字段索引，提升状态筛选效率。';
        }

        if ($rowCount >= 5000 && in_array('created_at', $columnNames, true) && ! $this->hasIndexForColumn($indexes, 'created_at')) {
            $suggestions[] = '当前表存在较多数据，建议评估 created_at 字段索引，优化时间倒序查询。';
        }

        if (! in_array('created_at', $columnNames, true) && ! in_array('updated_at', $columnNames, true)) {
            $suggestions[] = '当前表未包含 created_at / updated_at 字段，如属于业务表可考虑补充时间追踪字段。';
        }

        if ($suggestions === []) {
            $suggestions[] = sprintf('当前表结构整体正常，未发现明显的主键或常用查询索引缺失问题。');
        }

        return $suggestions;
    }

    protected function hasIndexForColumn(Collection $indexes, string $column): bool
    {
        return $indexes->contains(function (array $index) use ($column): bool {
            return in_array($column, $index['columns'] ?? [], true);
        });
    }

    protected function indexMapByColumn(Collection $indexes): array
    {
        $map = [];

        foreach ($indexes as $index) {
            foreach (($index['columns'] ?? []) as $column) {
                $map[(string) $column] ??= [];
                $map[(string) $column][] = (string) ($index['name'] ?? 'index');
            }
        }

        return $map;
    }

    protected function estimatedRowCounts(Collection $tables): array
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlEstimatedRowCounts($tables),
            default => $this->fallbackExactRowCounts($tables),
        };
    }

    protected function mysqlEstimatedRowCounts(Collection $tables): array
    {
        $databaseName = (string) DB::connection()->getDatabaseName();

        if ($databaseName === '') {
            return $this->fallbackExactRowCounts($tables);
        }

        return DB::table('information_schema.tables')
            ->selectRaw('TABLE_NAME as table_name, TABLE_ROWS as table_rows')
            ->where('table_schema', $databaseName)
            ->pluck('table_rows', 'table_name')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    protected function fallbackExactRowCounts(Collection $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $name = (string) ($table['name'] ?? $table['table_name'] ?? '');
            $counts[$name] = (int) DB::table($name)->count();
        }

        return $counts;
    }

    protected function databaseVersion(): ?string
    {
        $driver = (string) DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        try {
            $row = DB::selectOne('select version() as version');

            if (! $row || ! isset($row->version)) {
                return null;
            }

            return trim((string) $row->version) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function tableDescription(string $table): string
    {
        return match ($table) {
            'attachments' => '附件表，用于保存站点资源库中的图片与文件记录。',
            'attachment_relations' => '附件引用关系表，用于记录资源被文章、设置或模块引用的详情。',
            'cache' => '缓存数据表，用于数据库驱动缓存内容存储。',
            'cache_locks' => '缓存锁表，用于防止重复执行和竞争写入。',
            'channels' => '栏目主表，用于管理站点栏目结构。',
            'contents' => '文章内容表，用于保存新闻、通知、单页等正文内容。',
            'content_channels' => '文章栏目关联表，用于记录内容所属栏目。',
            'content_review_records' => '文章审核记录表，用于保留审核流程与处理结果。',
            'content_revisions' => '文章修订记录表，用于存档历史版本内容。',
            'failed_jobs' => '失败任务表，用于记录执行失败的队列任务。',
            'jobs' => '队列表，用于保存待执行的异步任务。',
            'job_batches' => '批量任务表，用于记录队列批处理状态。',
            'migrations' => '迁移记录表，用于标记数据库结构升级历史。',
            'modules' => '模块注册表，用于管理平台已安装的功能模块。',
            'module_guestbook_messages' => '留言板消息表，用于保存前台留言、回复与状态信息。',
            'operation_logs' => '操作日志表，用于记录后台关键操作行为。',
            'password_reset_tokens' => '密码重置令牌表，用于账号找回密码流程。',
            'platform_permissions' => '平台权限表，用于定义平台端权限点。',
            'platform_roles' => '平台角色表，用于定义平台端角色。',
            'platform_role_permissions' => '平台角色权限关联表，用于分配平台角色权限。',
            'platform_user_roles' => '平台用户角色关联表，用于绑定平台用户角色。',
            'promo_items' => '图宣内容表，用于管理轮播图和图文宣传项。',
            'promo_positions' => '图宣位置表，用于定义图宣展示位。',
            'sessions' => '会话表，用于数据库驱动的登录会话存储。',
            'sites' => '站点主表，用于保存多站点基础信息。',
            'site_domains' => '站点域名表，用于绑定站点访问域名。',
            'site_module_bindings' => '站点模块绑定表，用于控制站点可使用的功能模块。',
            'site_permissions' => '站点权限表，用于定义站点端权限点。',
            'site_roles' => '站点角色表，用于定义站点后台角色。',
            'site_role_permissions' => '站点角色权限关联表，用于分配站点角色权限。',
            'site_settings' => '站点设置表，用于保存站点级配置项。',
            'site_theme_bindings' => '站点主题绑定表，用于控制站点可用主题范围。',
            'site_theme_template_meta' => '站点主题模板元数据表，用于记录模板说明与标记信息。',
            'site_theme_template_versions' => '站点主题模板版本表，用于保存模板历史版本。',
            'site_user_channels' => '站点用户栏目权限表，用于控制用户可管理栏目范围。',
            'site_user_roles' => '站点用户角色关联表，用于绑定站点用户角色。',
            'system_settings' => '系统设置表，用于保存平台级配置项。',
            'themes' => '主题主表，用于管理系统主题信息。',
            'theme_versions' => '主题版本表，用于记录主题版本与发布信息。',
            'users' => '用户表，用于保存平台与站点后台账号。',
            default => sprintf('%s 数据表，用于保存该模块或系统相关业务数据。', Str::headline(str_replace('_', ' ', $table))),
        };
    }

    protected function tableLabel(string $table): string
    {
        return match ($table) {
            'attachments' => '附件表',
            'attachment_relations' => '附件引用表',
            'cache' => '缓存表',
            'cache_locks' => '缓存锁表',
            'channels' => '栏目表',
            'contents' => '文章内容表',
            'content_channels' => '文章栏目关联表',
            'content_review_records' => '文章审核记录表',
            'content_revisions' => '文章修订表',
            'failed_jobs' => '失败任务表',
            'jobs' => '队列表',
            'job_batches' => '批量任务表',
            'migrations' => '迁移记录表',
            'modules' => '模块表',
            'module_guestbook_messages' => '留言表',
            'operation_logs' => '操作日志表',
            'password_reset_tokens' => '密码重置表',
            'platform_permissions' => '平台权限表',
            'platform_roles' => '平台角色表',
            'platform_role_permissions' => '平台角色权限表',
            'platform_user_roles' => '平台用户角色表',
            'promo_items' => '图宣内容表',
            'promo_positions' => '图宣位置表',
            'sessions' => '会话表',
            'sites' => '站点表',
            'site_domains' => '站点域名表',
            'site_module_bindings' => '站点模块表',
            'site_permissions' => '站点权限表',
            'site_roles' => '站点角色表',
            'site_role_permissions' => '站点角色权限表',
            'site_settings' => '站点设置表',
            'site_theme_bindings' => '站点主题表',
            'site_theme_template_meta' => '模板元数据表',
            'site_theme_template_versions' => '模板版本表',
            'site_user_channels' => '用户栏目权限表',
            'site_user_roles' => '站点用户角色表',
            'system_settings' => '系统设置表',
            'themes' => '主题表',
            'theme_versions' => '主题版本表',
            'users' => '用户表',
            default => sprintf('%s 表', Str::headline(str_replace('_', ' ', $table))),
        };
    }
}
