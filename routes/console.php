<?php

use App\Support\AttachmentUsageTracker;
use App\Support\FrontendPageCache;
use App\Support\LegacyAspAccessSiteImporter;
use App\Support\LegacyPayrollAttachmentBatchImporter;
use App\Support\PromoItemExpiryManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cms:repair-attachment-usage {--site= : 仅修复指定站点ID}', function () {
    $siteId = $this->option('site');
    $resolvedSiteId = is_numeric($siteId) ? (int) $siteId : null;

    $this->info('开始修复附件引用统计...');

    $tracker = new AttachmentUsageTracker();
    $deletedDuplicates = $tracker->deduplicateRelations();
    $result = $tracker->rebuildAll($resolvedSiteId);

    $scopeText = $resolvedSiteId !== null
        ? "站点 {$resolvedSiteId}"
        : '全部站点';

    $this->line("范围：{$scopeText}");
    $this->line("去重关系：{$deletedDuplicates} 条");
    $this->line("附件总数：{$result['attachments']} 个");
    $this->line("有效引用附件：{$result['referenced']} 个");
    $this->info('附件引用统计修复完成。');
})->purpose('重建附件 usage_count 与 last_used_at 统计');

Artisan::command('cms:deactivate-expired-promos', function () {
    $affected = (new PromoItemExpiryManager())->deactivateExpiredItems();

    if ($affected === 0) {
        $this->info('当前没有需要自动停用的过期图宣项。');

        return;
    }

    $this->info("已自动停用 {$affected} 条过期图宣项。");
})->purpose('自动停用到期后的图宣项');

Artisan::command('cms:clear-frontend-page-cache {siteKey? : 站点标识，不填则刷新全部站点缓存版本}', function () {
    $siteKey = trim((string) ($this->argument('siteKey') ?? ''));

    $query = DB::table('sites')->orderBy('id');
    if ($siteKey !== '') {
        $query->where('site_key', $siteKey);
    }

    $sites = $query->get(['id', 'name', 'site_key']);

    if ($sites->isEmpty()) {
        $this->error($siteKey !== '' ? "未找到站点：{$siteKey}" : '未找到任何站点。');

        return 1;
    }

    foreach ($sites as $site) {
        FrontendPageCache::flushSite((int) $site->id);
        $this->line(sprintf('已刷新：%s (%s)', $site->name, $site->site_key));
    }

    $this->info('前台整页缓存版本已刷新。');

    return 0;
})->purpose('刷新前台整页缓存版本');

Artisan::command('cms:import-legacy-asp {sourceDir : 旧站导出目录} {siteKey : 新站点标识} {siteName : 新站点名称} {--execute : 实际创建站点并写入数据}', function () {
    $sourceDir = (string) $this->argument('sourceDir');
    $siteKey = trim((string) $this->argument('siteKey'));
    $siteName = trim((string) $this->argument('siteName'));
    $execute = (bool) $this->option('execute');

    $this->info($execute ? '开始执行旧站导入...' : '开始执行旧站导入预检查...');

    $result = app(LegacyAspAccessSiteImporter::class)->import(
        $siteKey,
        $siteName,
        $sourceDir,
        $execute,
    );

    $site = $result['site'] ?? [];
    $counts = $result['counts'] ?? [];
    $imported = $result['imported'] ?? [];
    $warnings = $result['warnings'] ?? [];

    $this->line('目标站点：'.(($site['name'] ?? '').' ('.($site['site_key'] ?? '').')'));
    $this->line('站点ID：'.(($site['id'] ?? null) !== null ? (string) $site['id'] : '待创建'));
    $this->line('数据来源：'.$sourceDir);
    $this->line('模式：'.($execute ? '写入执行' : '仅预检查'));
    $this->newLine();

    $this->line('源数据统计：');
    $this->line('  一级栏目：'.(int) ($counts['type_d'] ?? 0));
    $this->line('  二级栏目：'.(int) ($counts['type'] ?? 0));
    $this->line('  单页面：'.(int) ($counts['about'] ?? 0));
    $this->line('  文章主表：'.(int) ($counts['news'] ?? 0));
    $this->line('  文章正文：'.(int) ($counts['news_content'] ?? 0));
    $this->newLine();

    if ($execute) {
        $this->line('导入结果：');
        $this->line('  栏目新增：'.(int) ($imported['channels_created'] ?? 0));
        $this->line('  栏目更新：'.(int) ($imported['channels_updated'] ?? 0));
        $this->line('  单页新增：'.(int) ($imported['pages_created'] ?? 0));
        $this->line('  单页更新：'.(int) ($imported['pages_updated'] ?? 0));
        $this->line('  文章新增：'.(int) ($imported['articles_created'] ?? 0));
        $this->line('  文章更新：'.(int) ($imported['articles_updated'] ?? 0));
        $this->line('  文章跳过：'.(int) ($imported['articles_skipped'] ?? 0));
        $this->newLine();
    }

    foreach ($warnings as $warning) {
        $this->warn((string) $warning);
    }

    $this->info($execute ? '旧站导入完成。' : '旧站导入预检查完成。');
})->purpose('将 ASP + Access 老站导出文件导入为当前系统的新站点');

Artisan::command('cms:import-legacy-payroll-attachments {siteKey=psygz : 目标站点标识} {rootPath=/www/wwwroot/gss/storage/app/web/psygz/media/attachments/Up/file : 旧工资附件目录} {--execute : 实际解析并写入工资明细}', function () {
    $siteKey = trim((string) $this->argument('siteKey'));
    $rootPath = (string) $this->argument('rootPath');
    $execute = (bool) $this->option('execute');

    $this->info($execute ? '开始执行旧工资附件批量导入...' : '开始执行旧工资附件导入预检查...');

    $result = app(LegacyPayrollAttachmentBatchImporter::class)->import(
        $siteKey,
        $rootPath,
        $execute,
    );

    $site = $result['site'] ?? [];
    $counts = $result['counts'] ?? [];
    $imported = $result['imported'] ?? [];
    $warnings = $result['warnings'] ?? [];
    $errors = $result['errors'] ?? [];

    $this->line('目标站点：'.(($site['name'] ?? '').' ('.($site['site_key'] ?? '').')'));
    $this->line('站点ID：'.(($site['id'] ?? null) !== null ? (string) $site['id'] : '未知'));
    $this->line('附件目录：'.$rootPath);
    $this->line('模式：'.($execute ? '写入执行' : '仅预检查'));
    $this->newLine();

    $this->line('批次统计：');
    $this->line('  批次数量：'.(int) ($counts['batches'] ?? 0));
    $this->line('  找到工资表：'.(int) ($counts['salary_files_found'] ?? 0));
    $this->line('  找到绩效表：'.(int) ($counts['performance_files_found'] ?? 0));
    $this->line('  缺失工资表：'.(int) ($counts['salary_files_missing'] ?? 0));
    $this->line('  缺失绩效表：'.(int) ($counts['performance_files_missing'] ?? 0));
    $this->newLine();

    if ($execute) {
        $this->line('导入结果：');
        $this->line('  工资批次已导入：'.(int) ($imported['salary_batches_imported'] ?? 0));
        $this->line('  绩效批次已导入：'.(int) ($imported['performance_batches_imported'] ?? 0));
        $this->line('  工资明细行数：'.(int) ($imported['salary_records_imported'] ?? 0));
        $this->line('  绩效明细行数：'.(int) ($imported['performance_records_imported'] ?? 0));
        $this->newLine();
    }

    foreach ($warnings as $warning) {
        $this->warn((string) $warning);
    }

    foreach ($errors as $error) {
        $this->error((string) $error);
    }

    $this->info($execute ? '旧工资附件批量导入完成。' : '旧工资附件导入预检查完成。');
})->purpose('根据工资批次中已有文件名，批量解析旧工资附件目录中的 xls/xlsx 明细');

Schedule::command('cms:deactivate-expired-promos')
    ->everyMinute()
    ->withoutOverlapping();
