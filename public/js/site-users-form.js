document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('site-user-create-form') || document.getElementById('site-user-edit-form');

    if (!form) {
        return;
    }

    document.querySelectorAll('.status-choice-grid').forEach(function (group) {
        const syncStatusChoices = function () {
            group.querySelectorAll('.status-choice').forEach(function (choice) {
                const input = choice.querySelector('input[type="radio"]');
                choice.classList.toggle('is-active', !!input && input.checked);
            });
        };

        group.addEventListener('change', syncStatusChoices);
        syncStatusChoices();
    });

    document.querySelectorAll('.role-choice-grid').forEach(function (group) {
        const syncRoleChoices = function () {
            group.querySelectorAll('.role-choice').forEach(function (choice) {
                const input = choice.querySelector('input[type="radio"]');
                choice.classList.toggle('is-active', !!input && input.checked);
            });
        };

        group.addEventListener('change', syncRoleChoices);
        syncRoleChoices();
    });

    const channelModule = document.querySelector('.js-channel-permission-module');
    const channelTree = channelModule ? channelModule.querySelector('.channel-tree-wrap') : null;
    const channelPlaceholder = channelModule ? channelModule.querySelector('.js-channel-placeholder') : null;
    const channelRealPanel = channelModule ? channelModule.querySelector('.js-channel-real-panel') : null;
    const channelModuleReadonly = channelModule ? channelModule.dataset.readonly === '1' : false;

    const syncChannelDescendants = function (checkbox) {
        if (!channelTree || !checkbox) {
            return;
        }

        const currentOption = checkbox.closest('.channel-option');
        if (!currentOption) {
            return;
        }

        const currentDepth = Number(currentOption.dataset.depth || 0);
        let nextOption = currentOption.nextElementSibling;

        while (nextOption && nextOption.classList.contains('channel-option')) {
            const nextDepth = Number(nextOption.dataset.depth || 0);
            if (nextDepth <= currentDepth) {
                break;
            }

            const nextCheckbox = nextOption.querySelector('input[name="channel_ids[]"]');
            if (nextCheckbox && !nextCheckbox.disabled) {
                nextCheckbox.checked = checkbox.checked;
            }

            nextOption = nextOption.nextElementSibling;
        }
    };

    const syncChannelModule = function () {
        if (!channelModule || channelModuleReadonly) {
            return;
        }

        const checkedRole = document.querySelector('input[name="role_id"]:checked');
        const canManageContent = !!(checkedRole && checkedRole.closest('.role-choice') && checkedRole.closest('.role-choice').dataset.canManageContent === '1');

        if (channelPlaceholder) {
            channelPlaceholder.hidden = canManageContent;
        }

        if (channelRealPanel) {
            channelRealPanel.hidden = !canManageContent;
        }

        channelModule.querySelectorAll('input[name="channel_ids[]"]').forEach(function (input) {
            input.disabled = !canManageContent;
        });
    };

    document.querySelectorAll('.role-choice-grid').forEach(function (group) {
        group.addEventListener('change', syncChannelModule);
    });
    syncChannelModule();

    const syncStatusIdentity = function () {
        const displayName = document.querySelector('[data-status-display-name]');
        const displayUsername = document.querySelector('[data-status-display-username]');
        const displayAvatar = document.querySelector('[data-status-avatar]');
        const nameInput = document.getElementById('name');
        const usernameInput = document.getElementById('username');

        if (!displayName || !displayUsername || !displayAvatar || !nameInput || !usernameInput) {
            return;
        }

        const nameValue = String(nameInput.value || '').trim();
        const usernameValue = String(usernameInput.value || '').trim();
        const fallbackName = displayName.dataset.emptyName || '待填写姓名';
        const fallbackUsername = displayUsername.dataset.emptyUsername || '待设置账号';
        const fallbackAvatar = displayAvatar.dataset.fallback || '新';
        const seed = nameValue || usernameValue || fallbackAvatar;

        displayName.textContent = nameValue || fallbackName;
        displayUsername.textContent = usernameValue || fallbackUsername;
        displayAvatar.textContent = Array.from(seed)[0] || fallbackAvatar;
    };

    ['name', 'username'].forEach(function (fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.addEventListener('input', syncStatusIdentity);
        }
    });

    const avatarInput = document.getElementById('avatar');
    const avatarCard = document.querySelector('[data-avatar-trigger]');
    const avatarPreview = document.querySelector('[data-avatar-preview]');
    const avatarNote = document.querySelector('[data-avatar-note]');
    const avatarRemove = document.querySelector('[data-avatar-remove]');

    const renderAvatar = function () {
        if (!avatarInput || !avatarCard || !avatarPreview) {
            syncStatusIdentity();
            return;
        }

        const value = avatarInput.value.trim();
        let img = avatarPreview.querySelector('[data-avatar-image]');
        let fallback = avatarPreview.querySelector('[data-status-avatar]');

        if (!fallback) {
            fallback = document.createElement('span');
            fallback.className = 'site-user-status-avatar-fallback';
            fallback.setAttribute('data-status-avatar', '');
            avatarPreview.appendChild(fallback);
        }

        syncStatusIdentity();

        if (!value) {
            if (img) {
                img.remove();
            }
            fallback.hidden = false;
            avatarCard.classList.remove('has-image');
            if (avatarNote) {
                avatarNote.textContent = '点击头像，从资源库选择或上传。';
            }
            return;
        }

        if (!img) {
            img = document.createElement('img');
            img.setAttribute('data-avatar-image', '');
            img.alt = '头像预览';
            avatarPreview.prepend(img);
        }

        img.src = value;
        img.onerror = function () {
            img.remove();
            avatarInput.value = '';
            renderAvatar();
        };
        fallback.hidden = true;
        avatarCard.classList.add('has-image');
        if (avatarNote) {
            avatarNote.textContent = '已设置头像，点击可从资源库更换。';
        }
    };

    avatarCard?.addEventListener('click', function () {
        window.openSiteAttachmentLibrary?.({
            mode: 'avatar',
            context: 'avatar',
            imageOnly: true,
            onSelect(attachment) {
                if (!avatarInput) {
                    return;
                }

                avatarInput.value = attachment.url || '';
                renderAvatar();
            },
            onClear() {
                if (!avatarInput) {
                    return;
                }

                avatarInput.value = '';
                renderAvatar();
            },
        });
    });

    avatarRemove?.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!avatarInput) {
            return;
        }

        avatarInput.value = '';
        renderAvatar();
    });

    avatarInput?.addEventListener('input', renderAvatar);
    renderAvatar();

    if (channelTree) {
        channelTree.addEventListener('change', function (event) {
            const checkbox = event.target.closest('input[name="channel_ids[]"]');
            if (!checkbox) {
                return;
            }

            syncChannelDescendants(checkbox);
        });
    }

    if (window.tinymce) {
        window.tinymce.init({
            selector: 'textarea.site-user-remark-rich-editor',
            min_height: 200,
            height: 240,
            language: 'zh-CN',
            language_url: '/assets/tinymce/langs/zh-CN.js',
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
            relative_urls: false,
            plugins: 'autolink link lists code textcolor',
            toolbar: 'undo redo | bold italic underline forecolor backcolor | bullist numlist | link blockquote | removeformat code',
            content_style: 'body { font-family: PingFang SC, Microsoft YaHei, sans-serif; font-size: 14px; line-height: 1.8; }',
            setup(editor) {
                editor.on('change input undo redo', () => editor.save());
            }
        });
    }

    const deleteButton = document.querySelector('.js-site-user-delete');
    if (deleteButton) {
        deleteButton.addEventListener('click', function () {
            const formId = deleteButton.dataset.formId;
            const targetForm = formId ? document.getElementById(formId) : null;

            if (!targetForm || typeof window.showConfirmDialog !== 'function') {
                return;
            }

            window.showConfirmDialog({
                title: '确认删除操作员？',
                text: '删除后，该操作员账号及其站点绑定关系都会被清除，且操作不可恢复。',
                confirmText: '确定删除',
                onConfirm: () => targetForm.submit(),
            });
        });
    }

    const fields = {
        username: document.getElementById('username'),
        name: document.getElementById('name'),
        email: document.getElementById('email'),
        mobile: document.getElementById('mobile'),
        password: document.getElementById('password'),
        remark: document.getElementById('remark'),
    };

    const normalizeValue = function (field) {
        return String(field?.value || '').trim();
    };

    const clearFieldError = function (field) {
        if (!field) {
            return;
        }

        field.classList.remove('is-error');
        field.removeAttribute('aria-invalid');
    };

    const setFieldError = function (field) {
        if (!field) {
            return;
        }

        field.classList.add('is-error');
        field.setAttribute('aria-invalid', 'true');
    };

    const clearChoiceErrors = function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
            element.classList.remove('is-error');
        });
    };

    const setChoiceError = function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
            element.classList.add('is-error');
        });
    };

    const usernamePattern = /^[A-Za-z][A-Za-z0-9_-]{3,31}$/;
    const mobilePattern = /^[0-9\-+\s()#]{6,50}$/;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const requirePassword = form.dataset.requirePassword === '1';

    const validators = {
        username(value) {
            if (value === '') {
                return '请填写用户名。';
            }

            return usernamePattern.test(value)
                ? ''
                : '用户名需以字母开头，可使用字母、数字、下划线或短横线，长度 4-32 位。';
        },
        name(value) {
            if (value === '') {
                return '请填写姓名。';
            }

            if (value.length < 2) {
                return '姓名至少需要 2 个字符。';
            }

            return value.length <= 50 ? '' : '姓名不能超过 50 个字符。';
        },
        email(value) {
            if (value !== '' && value.length > 255) {
                return '邮箱长度不能超过 255 个字符。';
            }

            return value === '' || emailPattern.test(value) ? '' : '邮箱格式不正确，请重新填写。';
        },
        mobile(value) {
            return value === '' || mobilePattern.test(value) ? '' : '手机号格式不正确，请输入有效的电话或手机号。';
        },
        password(value) {
            if (!requirePassword && value === '') {
                return '';
            }

            if (requirePassword && value === '') {
                return '请设置初始密码。';
            }

            return value.length >= 8 ? '' : (requirePassword ? '初始密码至少需要 8 位。' : '重置密码至少需要 8 位。');
        },
        remark(value) {
            return value.length <= 10000 ? '' : '备注信息不能超过 10000 个字符。';
        },
    };

    const validateRole = function () {
        const roleInputs = Array.from(document.querySelectorAll('input[name="role_id"]'));

        if (roleInputs.length === 0) {
            return '';
        }

        return roleInputs.some(function (input) {
            return input.checked;
        }) ? '' : '请选择操作角色。';
    };

    const validateStatus = function () {
        const statusInputs = Array.from(document.querySelectorAll('input[name="status"]'));

        if (statusInputs.length === 0) {
            return '';
        }

        return statusInputs.some(function (input) {
            return input.checked;
        }) ? '' : '请选择账号状态。';
    };

    Object.entries(fields).forEach(function ([key, field]) {
        if (!field) {
            return;
        }

        const validateCurrentField = function () {
            const validator = validators[key];
            const message = validator ? validator(normalizeValue(field)) : '';

            if (message === '') {
                clearFieldError(field);
            }
        };

        field.addEventListener('input', validateCurrentField);
        field.addEventListener('blur', validateCurrentField);
    });

    document.querySelectorAll('input[name="role_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            clearChoiceErrors('.role-choice');
        });
    });

    document.querySelectorAll('input[name="status"]').forEach(function (input) {
        input.addEventListener('change', function () {
            clearChoiceErrors('.status-choice');
        });
    });

    form.addEventListener('submit', function (event) {
        const messages = [];
        let firstInvalid = null;

        Object.values(fields).forEach(function (field) {
            clearFieldError(field);
        });
        clearChoiceErrors('.role-choice');
        clearChoiceErrors('.status-choice');

        Object.entries(fields).forEach(function ([key, field]) {
            if (!field) {
                return;
            }

            const validator = validators[key];
            const message = validator ? validator(normalizeValue(field)) : '';

            if (message !== '') {
                setFieldError(field);
                messages.push(message);
                firstInvalid = firstInvalid || field;
            }
        });

        const roleMessage = validateRole();
        if (roleMessage !== '') {
            setChoiceError('.role-choice');
            messages.push(roleMessage);
            firstInvalid = firstInvalid || document.querySelector('input[name="role_id"]');
        }

        const statusMessage = validateStatus();
        if (statusMessage !== '') {
            setChoiceError('.status-choice');
            messages.push(statusMessage);
            firstInvalid = firstInvalid || document.querySelector('input[name="status"]');
        }

        if (messages.length > 0) {
            event.preventDefault();
            if (typeof window.showMessage === 'function') {
                window.showMessage([...new Set(messages)].join('，'), 'error');
            }
            firstInvalid?.focus();
            firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    try {
        const messages = JSON.parse(form.dataset.validationErrors || '[]');
        if (Array.isArray(messages) && messages.length > 0 && typeof window.showMessage === 'function') {
            window.showMessage([...new Set(messages)].join('，'), 'error');
        }
    } catch (error) {
        // no-op
    }
});
