<?php

return [
    'content_types' => [
        'page' => '单页面',
        'article' => '文章',
    ],

    'content_statuses' => [
        'draft' => '草稿',
        'pending' => '待审核',
        'published' => '已发布',
        'offline' => '已下线',
        'rejected' => '已驳回',
    ],

    'channel_types' => [
        'list' => '文章栏目',
        'page' => '单页栏目',
        'link' => '外链栏目',
    ],

    'role_scopes' => [
        'platform' => '平台级',
        'site' => '站点级',
    ],

    'super_admin_user_id' => (int) env('CMS_SUPER_ADMIN_USER_ID', 1),

    'permission_modules' => [
        'attachment' => '附件管理',
        'channel' => '栏目管理',
        'content' => '内容管理',
        'database' => '数据库管理',
        'log' => '操作日志',
        'module' => '功能模块',
        'notice' => '公告管理',
        'platform' => '平台管理',
        'platform_role' => '平台角色',
        'promo' => '图宣管理',
        'setting' => '站点设置',
        'site' => '站点管理',
        'system' => '系统设置',
        'theme' => '模板管理',
        'user' => '用户管理',
    ],

    'default_platform_role_permissions' => [
        'super_admin' => [
            'database.manage',
            'module.manage',
            'platform.view',
            'site.manage',
            'theme.market.manage',
            'platform.user.manage',
            'platform.role.manage',
            'platform.notice.manage',
            'system.setting.manage',
            'platform.log.view',
        ],
        'platform_admin' => [
            'database.manage',
            'module.manage',
            'platform.view',
            'site.manage',
            'platform.user.manage',
            'platform.notice.manage',
            'system.setting.manage',
            'platform.log.view',
        ],
        'theme_admin' => [
            'platform.view',
            'theme.market.manage',
        ],
    ],

    'default_site_role_permissions' => [
        'site_admin' => [
            'channel.manage',
            'promo.manage',
            'content.manage',
            'content.publish',
            'content.audit',
            'attachment.manage',
            'module.use',
            'theme.use',
            'theme.edit',
            'setting.manage',
            'site.user.manage',
            'log.view',
        ],
        'editor' => [
            'content.manage',
            'promo.manage',
            'attachment.manage',
        ],
        'reviewer' => [
            'content.manage',
            'content.publish',
            'content.audit',
            'log.view',
        ],
        'uploader' => [
            'attachment.manage',
        ],
        'template_editor' => [
            'theme.use',
            'theme.edit',
        ],
    ],

    'promo_page_scopes' => [
        'global' => '全站通用',
        'home' => '首页',
        'channel' => '栏目页',
        'detail' => '详情页',
        'page' => '单页面',
    ],

    'promo_display_modes' => [
        'single' => '单图',
        'carousel' => '轮播',
        'floating' => '漂浮图',
    ],

    'system_setting_defaults' => [
        'system.name' => env('APP_NAME', 'School CMS'),
        'system.version' => '1.0.0',
        'admin.logo' => '/logo.jpg',
        'admin.favicon' => '/Favicon.ico',
        'attachment.allowed_extensions' => 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
        'attachment.max_size_mb' => 10,
        'attachment.image_max_size_mb' => 5,
        'attachment.image_max_width' => 4096,
        'attachment.image_max_height' => 4096,
        'attachment.image_auto_resize' => false,
        'attachment.image_auto_compress' => false,
        'attachment.image_quality' => 82,
        'admin.enabled' => true,
        'admin.disabled_message' => '后台暂时关闭，请联系系统管理员。',
    ],
];
