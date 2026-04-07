<script>
    (() => {
        const form = document.getElementById('platform-user-create-form') || document.getElementById('platform-user-edit-form');
        if (!form) {
            return;
        }

        const serverValidationErrors = @json($errors->all());
        if (Array.isArray(serverValidationErrors) && serverValidationErrors.length > 0) {
            const messages = [...new Set(serverValidationErrors.filter((message) => typeof message === 'string' && message.trim() !== ''))];
            if (messages.length > 0) {
                window.setTimeout(() => {
                    window.showMessage?.(messages.join('，'), 'error');
                }, 0);
            }
        }

        const fields = {
            username: document.getElementById('username'),
            name: document.getElementById('name'),
            email: document.getElementById('email'),
            mobile: document.getElementById('mobile'),
            password: document.getElementById('password'),
        };

        const normalizeValue = (field) => String(field?.value || '').trim();
        const isCreateMode = form.id === 'platform-user-create-form';
        const usernamePattern = /^[A-Za-z][A-Za-z0-9_-]{3,31}$/;
        const mobilePattern = /^[0-9\-+\s()#]{6,50}$/;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        const clearFieldError = (field) => {
            if (!field) {
                return;
            }

            field.classList.remove('is-error');
            field.removeAttribute('aria-invalid');
        };

        const setFieldError = (field) => {
            if (!field) {
                return;
            }

            field.classList.add('is-error');
            field.setAttribute('aria-invalid', 'true');
        };

        const clearChoiceErrors = () => {
            document.querySelectorAll('.platform-user-role-chip').forEach((element) => {
                element.classList.remove('is-error');
            });
        };

        const setChoiceError = () => {
            document.querySelectorAll('.platform-user-role-chip').forEach((element) => {
                element.classList.add('is-error');
            });
        };

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
                if (!isCreateMode && value === '') {
                    return '';
                }

                if (value === '') {
                    return '请设置初始密码。';
                }

                return value.length >= 8 ? '' : (isCreateMode ? '初始密码至少需要 8 位。' : '重置密码至少需要 8 位。');
            },
        };

        const validateRole = () => {
            const hiddenRole = form.querySelector('input[type="hidden"][name="role_id"]');
            if (hiddenRole && String(hiddenRole.value || '').trim() !== '') {
                return '';
            }

            const roleInputs = Array.from(document.querySelectorAll('input[name="role_id"][type="radio"]'));
            if (roleInputs.length === 0) {
                return '';
            }

            return roleInputs.some((input) => input.checked) ? '' : '请选择一个平台角色。';
        };

        Object.entries(fields).forEach(([key, field]) => {
            if (!field) {
                return;
            }

            const validateCurrentField = () => {
                const message = validators[key]?.(normalizeValue(field)) || '';
                if (message === '') {
                    clearFieldError(field);
                }
            };

            field.addEventListener('input', validateCurrentField);
            field.addEventListener('blur', validateCurrentField);
        });

        document.querySelectorAll('input[name="role_id"]').forEach((input) => {
            input.addEventListener('change', clearChoiceErrors);
        });

        form.addEventListener('submit', (event) => {
            const messages = [];
            let firstInvalid = null;

            Object.values(fields).forEach((field) => clearFieldError(field));
            clearChoiceErrors();

            Object.entries(fields).forEach(([key, field]) => {
                if (!field) {
                    return;
                }

                const message = validators[key]?.(normalizeValue(field)) || '';
                if (message !== '') {
                    setFieldError(field);
                    messages.push(message);
                    firstInvalid = firstInvalid || field;
                }
            });

            const roleMessage = validateRole();
            if (roleMessage !== '') {
                setChoiceError();
                messages.push(roleMessage);
                firstInvalid = firstInvalid || document.querySelector('input[name="role_id"]');
            }

            if (messages.length > 0) {
                event.preventDefault();
                window.showMessage?.([...new Set(messages)].join('，'), 'error');
                firstInvalid?.focus();
                firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    })();
</script>
