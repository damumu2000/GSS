@php
    $attachmentSystemSettings = app(\App\Support\SystemSettings::class);
    $attachmentLibraryRuleText = sprintf(
        "支持 %s\n单文件不超过 %dMB；图片不超过 %dMB；最大 %d×%d 像素。",
        strtoupper(implode(' / ', $attachmentSystemSettings->attachmentAllowedExtensions())),
        $attachmentSystemSettings->attachmentMaxSizeMb(),
        $attachmentSystemSettings->attachmentImageMaxSizeMb(),
        $attachmentSystemSettings->attachmentImageMaxWidth(),
        $attachmentSystemSettings->attachmentImageMaxHeight()
    );
    $attachmentLibraryWorkspaceAccessValue = isset($attachmentLibraryWorkspaceAccess)
        ? (bool) $attachmentLibraryWorkspaceAccess
        : (isset($avatarAttachmentWorkspaceAccess) ? (bool) $avatarAttachmentWorkspaceAccess : false);
@endphp

<div
    id="attachment-library-config"
    hidden
    data-rule-text="{{ $attachmentLibraryRuleText }}"
    data-auto-compress-enabled="{{ $attachmentSystemSettings->attachmentImageAutoCompressEnabled() ? '1' : '0' }}"
    data-workspace-access="{{ $attachmentLibraryWorkspaceAccessValue ? '1' : '0' }}"
    data-feed-url="{{ route('admin.attachments.library-feed') }}"
    data-replace-url-template="{{ route('admin.attachments.replace', ['attachment' => '__ATTACHMENT__']) }}"
    data-upload-url="{{ route('admin.attachments.library-upload') }}"
    data-delete-url-template="{{ route('admin.attachments.destroy', ['attachment' => '__ATTACHMENT__']) }}"
    data-usage-url-template="{{ route('admin.attachments.usages', ['attachment' => '__ATTACHMENT__']) }}"
></div>

<div id="attachment-library-modal" class="attachment-library-modal" hidden>
    <div class="attachment-library-backdrop" data-close-attachment-library></div>
    <div class="attachment-library-panel" role="dialog" aria-modal="true" aria-labelledby="attachment-library-title">
        <div class="attachment-library-header">
            <div>
                <h3 id="attachment-library-title">站点资源库</h3>
                <div class="muted attachment-library-rule-text">当前上传规则加载中...</div>
            </div>
            <button class="button secondary" type="button" data-close-attachment-library>关闭</button>
        </div>
        <div class="attachment-library-toolbar">
            <input id="attachment-library-search" class="field" type="text" placeholder="搜索文件名">
            <div class="site-select" data-attachment-site-select>
                <select id="attachment-library-filter" class="field site-select-native">
                    <option value="all">全部类型</option>
                    <option value="image">仅图片</option>
                    <option value="file">仅文件</option>
                </select>
                <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">全部类型</button>
                <div class="site-select-panel" data-select-panel></div>
            </div>
            <div class="site-select" data-attachment-site-select>
                <select id="attachment-library-usage" class="field site-select-native">
                    <option value="all">全部引用状态</option>
                    <option value="used">仅已引用</option>
                    <option value="unused">仅未引用</option>
                </select>
                <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">全部引用状态</button>
                <div class="site-select-panel" data-select-panel></div>
            </div>
            <div class="site-select" data-attachment-site-select>
                <select id="attachment-library-sort" class="field site-select-native">
                    <option value="latest">最新上传</option>
                    <option value="oldest">最早上传</option>
                </select>
                <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">最新上传</button>
                <div class="site-select-panel" data-select-panel></div>
            </div>
        </div>
        <div class="attachment-library-upload">
            <input id="attachment-library-file" type="file" hidden>
            <input id="attachment-library-replace-file" type="file" hidden>
            <button id="attachment-library-upload-trigger" class="button" type="button">上传新资源</button>
            <span id="attachment-library-upload-status" class="muted"></span>
        </div>
        <div id="attachment-library-contextbar" class="attachment-library-contextbar"></div>
        <div id="image-insert-panel" class="image-insert-panel" hidden>
            <div class="image-insert-grid">
                <div>
                    <label for="image-insert-width">图片宽度</label>
                    <div class="site-select" data-attachment-site-select>
                        <select id="image-insert-width" class="field site-select-native">
                            <option value="100">通栏 100%</option>
                            <option value="80">较宽 80%</option>
                            <option value="60" selected>标准 60%</option>
                            <option value="40">小图 40%</option>
                            <option value="auto">原始宽度</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">标准 60%</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
                <div>
                    <label for="image-insert-align">对齐方式</label>
                    <div class="site-select" data-attachment-site-select>
                        <select id="image-insert-align" class="field site-select-native">
                            <option value="center" selected>居中</option>
                            <option value="left">居左</option>
                            <option value="right">居右</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">居中</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
                <div>
                    <label for="image-insert-radius">圆角</label>
                    <div class="site-select" data-attachment-site-select>
                        <select id="image-insert-radius" class="field site-select-native">
                            <option value="0">无圆角</option>
                            <option value="12" selected>柔和圆角</option>
                            <option value="20">明显圆角</option>
                            <option value="999">圆形裁切</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">柔和圆角</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
                <div>
                    <label for="image-insert-spacing">下方留白</label>
                    <div class="site-select" data-attachment-site-select>
                        <select id="image-insert-spacing" class="field site-select-native">
                            <option value="12">紧凑</option>
                            <option value="20" selected>标准</option>
                            <option value="32">宽松</option>
                        </select>
                        <button class="site-select-trigger" type="button" data-select-trigger aria-expanded="false">标准</button>
                        <div class="site-select-panel" data-select-panel></div>
                    </div>
                </div>
            </div>
            <div>
                <label for="image-insert-caption">图片说明</label>
                <input id="image-insert-caption" class="field" type="text" placeholder="例如：学校运动会开幕式现场">
            </div>
            <div class="action-row">
                <button id="image-insert-confirm" class="button" type="button">插入图片</button>
                <button id="image-insert-cancel" class="button secondary" type="button">取消</button>
            </div>
        </div>
        <div id="attachment-library-grid" class="attachment-library-grid"></div>
        <div id="attachment-library-pagination" class="attachment-library-pagination" hidden></div>
    </div>
</div>

<div id="attachment-usage-modal" class="attachment-usage-modal" hidden>
    <div class="attachment-usage-backdrop" data-close-attachment-usage></div>
    <div class="attachment-usage-panel" role="dialog" aria-modal="true" aria-labelledby="attachment-usage-title">
        <div class="attachment-usage-header">
            <div>
                <h3 class="attachment-usage-title" id="attachment-usage-title">引用详情</h3>
                <div class="attachment-usage-desc" id="attachment-usage-desc">正在加载附件引用信息...</div>
            </div>
            <button class="attachment-usage-close" type="button" data-close-attachment-usage aria-label="关闭">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
        <div class="attachment-usage-loading" id="attachment-usage-loading">正在整理该附件的引用内容...</div>
        <div class="attachment-usage-list" id="attachment-usage-list" hidden></div>
        <div class="attachment-usage-empty" id="attachment-usage-empty" hidden>当前没有找到可见的引用内容。</div>
    </div>
</div>
