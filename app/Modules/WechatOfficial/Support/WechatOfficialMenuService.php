<?php

namespace App\Modules\WechatOfficial\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WechatOfficialMenuService
{
    public const TYPES = [
        'view' => '访问链接',
        'click' => '点击事件',
        'media_id' => '下发素材',
    ];

    /**
     * @return Collection<int, object>
     */
    public function allForSite(int $siteId): Collection
    {
        return DB::table('module_wechat_official_menus')
            ->where('site_id', $siteId)
            ->orderBy('level')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function topLevelForSite(int $siteId): Collection
    {
        return $this->allForSite($siteId)
            ->where('level', 1)
            ->values();
    }

    /**
     * @return array<int, array{parent: object, children: Collection<int, object>}>
     */
    public function groupedForSite(int $siteId): array
    {
        $menus = $this->allForSite($siteId);
        $children = $menus->where('level', 2)->groupBy('parent_id');

        return $menus->where('level', 1)->map(function ($parent) use ($children): array {
            return [
                'parent' => $parent,
                'children' => $children->get($parent->id, collect())->values(),
            ];
        })->values()->all();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validateBusinessRules(int $siteId, array $data, int $ignoreMenuId = 0): void
    {
        $level = (int) ($data['level'] ?? 1);
        $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;
        $name = trim((string) ($data['name'] ?? ''));
        $nameWidth = mb_strwidth($name, 'UTF-8');

        if ($name === '') {
            throw ValidationException::withMessages(['name' => '请填写菜单名称。']);
        }

        if (preg_match('/[\r\n\t]/u', $name)) {
            throw ValidationException::withMessages(['name' => '菜单名称不能包含换行或制表符。']);
        }

        if ($level === 1 && $parentId !== null) {
            throw ValidationException::withMessages(['parent_id' => '一级菜单不能选择上级菜单。']);
        }

        if ($level === 2 && $parentId === null) {
            throw ValidationException::withMessages(['parent_id' => '二级菜单必须选择所属一级菜单。']);
        }

        if ($level === 2 && $parentId !== null) {
            $parent = DB::table('module_wechat_official_menus')
                ->where('site_id', $siteId)
                ->where('id', $parentId)
                ->first();

            if (! $parent || (int) ($parent->level ?? 0) !== 1) {
                throw ValidationException::withMessages(['parent_id' => '所选上级菜单不存在或不是一级菜单。']);
            }
        }

        if ($level === 1 && $nameWidth > 8) {
            throw ValidationException::withMessages(['name' => '一级菜单名称不合法，最多支持 4 个汉字或 8 个字母数字。']);
        }

        if ($level === 2 && $nameWidth > 14) {
            throw ValidationException::withMessages(['name' => '二级菜单名称不合法，最多支持 7 个汉字或 14 个字母数字。']);
        }

        if ($level === 1) {
            $topCount = DB::table('module_wechat_official_menus')
                ->where('site_id', $siteId)
                ->where('level', 1)
                ->when($ignoreMenuId > 0, fn ($query) => $query->where('id', '!=', $ignoreMenuId))
                ->count();

            if ($topCount >= 3) {
                throw ValidationException::withMessages(['level' => '一级菜单最多只能创建 3 个。']);
            }
        }

        if ($level === 2 && $parentId !== null) {
            $childCount = DB::table('module_wechat_official_menus')
                ->where('site_id', $siteId)
                ->where('parent_id', $parentId)
                ->when($ignoreMenuId > 0, fn ($query) => $query->where('id', '!=', $ignoreMenuId))
                ->count();

            if ($childCount >= 5) {
                throw ValidationException::withMessages(['parent_id' => '每个一级菜单下最多只能创建 5 个二级菜单。']);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildWechatButtons(int $siteId): array
    {
        return collect($this->groupedForSite($siteId))
            ->map(function (array $group): array {
                $parent = $group['parent'];
                $children = $group['children'];

                if ($children instanceof Collection && $children->isNotEmpty()) {
                    return [
                        'name' => (string) $parent->name,
                        'sub_button' => $children->map(fn ($child): array => $this->toWechatButton($child))->values()->all(),
                    ];
                }

                return $this->toWechatButton($parent);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toWechatButton(object $menu): array
    {
        $type = (string) ($menu->type ?? 'view');

        return match ($type) {
            'click' => [
                'type' => 'click',
                'name' => (string) $menu->name,
                'key' => (string) ($menu->key ?? ''),
            ],
            'media_id' => [
                'type' => 'media_id',
                'name' => (string) $menu->name,
                'media_id' => (string) ($menu->media_id ?? ''),
            ],
            default => [
                'type' => 'view',
                'name' => (string) $menu->name,
                'url' => (string) ($menu->url ?? ''),
            ],
        };
    }
}
