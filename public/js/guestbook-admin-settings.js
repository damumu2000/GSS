(() => {
    if (window.tinymce) {
        window.tinymce.init({
            selector: 'textarea.guestbook-notice-rich-editor',
            min_height: 200,
            height: 260,
            language: 'zh_CN',
            language_url: '/vendor/tinymce/langs/zh_CN.js',
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
            relative_urls: false,
            plugins: 'code textcolor',
            toolbar: 'undo redo | fontsize | bold italic underline | forecolor backcolor | removeformat code',
            font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px',
            content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 14px; line-height: 1.8; }',
            setup(editor) {
                editor.on('change input undo redo', () => editor.save());
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const noticeImageInput = document.getElementById('notice_image');
        const noticeImagePreview = document.querySelector('[data-notice-image-preview]');
        const noticeImagePreviewImage = document.querySelector('[data-notice-image-preview-image]');
        const noticeImagePlaceholder = document.querySelector('[data-notice-image-placeholder]');
        const noticeImageStatus = document.querySelector('[data-notice-image-status]');
        const noticeImagePrimaryText = document.querySelector('[data-notice-image-primary]');
        const noticeImageSecondaryText = document.querySelector('[data-notice-image-secondary]');
        const noticeImageClearInlineButton = document.querySelector('[data-notice-image-clear-inline]');
        const noticeImageOpenButtons = document.querySelectorAll('[data-notice-image-open]');

        const syncNoticeImageCopy = (hasImage) => {
            if (noticeImageStatus) {
                noticeImageStatus.textContent = hasImage ? '已设置背景图' : '未设置背景图';
            }

            if (noticeImagePrimaryText) {
                noticeImagePrimaryText.textContent = hasImage
                    ? '当前已选择发布须知背景图，前台会在发布须知右侧以渐隐背景方式展示。'
                    : '从站点资源库选择一张图片，前台会在发布须知右侧以渐隐背景方式展示。';
            }

            if (noticeImageSecondaryText) {
                noticeImageSecondaryText.textContent = hasImage
                    ? '点击预览区域可重新选择图片，右上角的 × 可清除当前背景图。'
                    : '选中图片后，这里会模拟前台发布须知的右侧渐隐背景效果。';
            }
        };

        const syncNoticeImagePreview = () => {
            if (!noticeImageInput || !noticeImagePreview || !noticeImagePreviewImage || !noticeImagePlaceholder) {
                return;
            }

            const value = noticeImageInput.value.trim();

            if (!value) {
                noticeImagePreview.classList.remove('is-filled');
                noticeImagePreviewImage.hidden = true;
                noticeImagePreviewImage.onerror = null;
                noticeImagePreviewImage.removeAttribute('src');
                noticeImagePlaceholder.hidden = false;
                syncNoticeImageCopy(false);
                noticeImageClearInlineButton?.setAttribute('hidden', 'hidden');
                return;
            }

            noticeImagePreview.classList.add('is-filled');
            noticeImagePreviewImage.onerror = () => {
                noticeImagePreview.classList.remove('is-filled');
                noticeImagePreviewImage.hidden = true;
                noticeImagePreviewImage.removeAttribute('src');
                noticeImagePlaceholder.hidden = false;
                syncNoticeImageCopy(false);
                noticeImageClearInlineButton?.setAttribute('hidden', 'hidden');
            };
            noticeImagePreviewImage.src = value;
            noticeImagePreviewImage.hidden = false;
            noticeImagePlaceholder.hidden = true;
            syncNoticeImageCopy(true);
            noticeImageClearInlineButton?.removeAttribute('hidden');
        };

        const openNoticeImageLibrary = () => {
            window.openSiteAttachmentLibrary?.({
                mode: 'picker',
                context: 'guestbook',
                imageOnly: true,
                onSelect(attachment) {
                    if (!noticeImageInput) {
                        return;
                    }

                    noticeImageInput.value = attachment.url || '';
                    syncNoticeImagePreview();
                },
            });
        };

        noticeImageOpenButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                openNoticeImageLibrary();
            });
        });

        noticeImagePreview?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openNoticeImageLibrary();
            }
        });

        noticeImageClearInlineButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (!noticeImageInput) {
                return;
            }

            noticeImageInput.value = '';
            syncNoticeImagePreview();
        });

        noticeImageInput?.addEventListener('input', syncNoticeImagePreview);
        syncNoticeImagePreview();
    });
})();
