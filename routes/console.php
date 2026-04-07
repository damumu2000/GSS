<?php

use App\Support\AttachmentUsageTracker;
use App\Support\PromoItemExpiryManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('cms:deactivate-expired-promos')
    ->everyMinute()
    ->withoutOverlapping();
