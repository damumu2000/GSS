<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WechatOfficialMaterialService
{
    public function paginatedImageAttachments(int $siteId, string $keyword = '', string $syncStatus = ''): LengthAwarePaginator
    {
        $syncStatus = in_array($syncStatus, ['synced', 'not_synced'], true) ? $syncStatus : '';

        $latestMaterialIds = DB::table('module_wechat_official_materials')
            ->selectRaw('MAX(id) as id, attachment_id')
            ->where('site_id', $siteId)
            ->whereNotNull('attachment_id')
            ->groupBy('attachment_id');

        return DB::table('attachments')
            ->leftJoinSub($latestMaterialIds, 'latest_material_ids', function ($join): void {
                $join->on('latest_material_ids.attachment_id', '=', 'attachments.id');
            })
            ->leftJoin('module_wechat_official_materials as materials', 'materials.id', '=', 'latest_material_ids.id')
            ->where('attachments.site_id', $siteId)
            ->where('attachments.mime_type', 'like', 'image/%')
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where('attachments.origin_name', 'like', '%'.$keyword.'%');
            })
            ->when($syncStatus !== '', function ($query) use ($syncStatus): void {
                if ($syncStatus === 'not_synced') {
                    $query->whereNull('materials.id');

                    return;
                }

                $query->whereNotNull('materials.id');
            })
            ->orderByDesc('attachments.updated_at')
            ->orderByDesc('attachments.id')
            ->paginate(12, [
                'attachments.id',
                'attachments.origin_name',
                'attachments.url',
                'attachments.path',
                'attachments.disk',
                'attachments.mime_type',
                'attachments.extension',
                'attachments.size',
                'attachments.width',
                'attachments.height',
                'attachments.updated_at',
                'materials.id as material_record_id',
                'materials.wechat_media_id',
                'materials.wechat_url',
                'materials.synced_at',
            ])
            ->withQueryString();
    }

    /**
     * @return array<int, object>
     */
    public function recentMaterials(int $siteId, int $limit = 8): array
    {
        return DB::table('module_wechat_official_materials')
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'attachment_id',
                'title',
                'wechat_media_id',
                'wechat_url',
                'synced_at',
                'updated_at',
            ])
            ->all();
    }

    /**
     * @return object{attachment: object, absolute_path: string}
     */
    public function resolveImageAttachment(int $siteId, int $attachmentId): object
    {
        $attachment = DB::table('attachments')
            ->where('site_id', $siteId)
            ->where('id', $attachmentId)
            ->where('mime_type', 'like', 'image/%')
            ->first([
                'id',
                'origin_name',
                'path',
                'disk',
                'mime_type',
                'extension',
                'size',
            ]);

        if (! $attachment) {
            throw new RuntimeException('图片附件不存在，无法同步到公众号素材库。');
        }

        $disk = trim((string) ($attachment->disk ?? 'public'));
        $path = trim((string) ($attachment->path ?? ''));

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('图片附件文件不存在，无法同步到公众号素材库。');
        }

        return (object) [
            'attachment' => $attachment,
            'absolute_path' => Storage::disk($disk)->path($path),
        ];
    }
}
