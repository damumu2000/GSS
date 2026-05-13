function initGuestbookForms() {
  var forms = document.querySelectorAll('[data-guestbook-form]');
  if (!forms.length) {
    return;
  }

  forms.forEach(function (form) {
    if (form.getAttribute('data-guestbook-bound') === '1') {
      return;
    }
    try {
      var bound = initGuestbookForm(form);
      if (bound) {
        form.setAttribute('data-guestbook-bound', '1');
      }
    } catch (error) {
      form.removeAttribute('data-guestbook-bound');
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('guestbook form init failed', error);
      }
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initGuestbookForms, { once: true });
} else {
  initGuestbookForms();
}

window.addEventListener('pageshow', function () {
  initGuestbookForms();
});

window.initGuestbookForms = initGuestbookForms;
initGuestbookForms();

  function initGuestbookForm(form) {
    var submitButton = form.querySelector('[data-guestbook-submit]');
    var captchaBlock = form.querySelector('[data-guestbook-captcha-block]');
    var captchaImage = form.querySelector('[data-guestbook-captcha-image]');
    var captchaTrigger = form.querySelector('[data-guestbook-captcha-trigger]') || captchaImage;
    var successModalId = form.getAttribute('data-guestbook-success-modal') || '';
    var successModal = successModalId ? document.getElementById(successModalId) : null;
    var successBackdrop = successModal ? successModal.querySelector('[data-guestbook-success-backdrop]') : null;
    var successClose = successModal ? successModal.querySelector('[data-guestbook-success-close]') : null;
    var successFeedback = form.querySelector('[data-guestbook-success-feedback]');
    var formError = form.querySelector('[data-guestbook-form-error]');
    var successRedirectUrl = form.getAttribute('data-guestbook-success-redirect') || '';
    var previewHosts = ['127.0.0.1', 'localhost'];
    var currentHost = window.location.hostname || '';
    var currentSiteKey = new URLSearchParams(window.location.search).get('site') || '';
    var captchaVerifySerial = 0;
    var captchaVerifiedValue = '';

    function resolveEndpoint(url) {
      try {
        var parsed = new URL(url, window.location.origin);
        var isPreviewHost = previewHosts.indexOf(currentHost) !== -1;
        if (isPreviewHost && currentSiteKey !== '' && !parsed.searchParams.get('site')) {
          parsed.searchParams.set('site', currentSiteKey);
        }

        return parsed.pathname + (parsed.search || '');
      } catch (error) {
        return url;
      }
    }

    var submitUrl = resolveEndpoint(form.getAttribute('action') || '/guestbook');
    var captchaImageUrl = resolveEndpoint(form.getAttribute('data-captcha-url') || '/guestbook/captcha');
    var captchaVerifyUrl = resolveEndpoint(form.getAttribute('data-captcha-verify-url') || '/guestbook/captcha/verify');
    var csrfToken = form.getAttribute('data-csrf-token') || getCookie('XSRF-TOKEN');

    form.setAttribute('action', submitUrl);

    var fields = {
      name: form.querySelector('[data-guestbook-field="name"]'),
      phone: form.querySelector('[data-guestbook-field="phone"]'),
      content: form.querySelector('[data-guestbook-field="content"]'),
      captcha: form.querySelector('[data-guestbook-field="captcha"]')
    };
    var extraFields = Array.from(form.querySelectorAll('[data-guestbook-extra]'));
    var honeypotField = form.querySelector('[data-guestbook-honeypot]') || form.querySelector('input[name="website"]');
    var contentCounter = form.querySelector('[data-guestbook-counter]');

    function getErrorNode(key) {
      return form.querySelector('[data-guestbook-error-for="' + key + '"]');
    }

    function getCookie(name) {
      var prefix = name + '=';
      var parts = document.cookie ? document.cookie.split('; ') : [];
      for (var i = 0; i < parts.length; i += 1) {
        if (parts[i].indexOf(prefix) === 0) {
          return decodeCookie(parts[i].slice(prefix.length));
        }
      }
      return '';
    }

    function decodeCookie(value) {
      try {
        return decodeURIComponent(value.replace(/\+/g, '%20'));
      } catch (error) {
        return value;
      }
    }

    function countChars(text) {
      return Array.from(text || '').length;
    }

    function isCaptchaVisible() {
      return !!(captchaBlock && !captchaBlock.hasAttribute('hidden'));
    }

    function toggleBodyScroll(disabled) {
      document.body.style.overflow = disabled ? 'hidden' : '';
    }

    function openSuccessModal() {
      if (!successModal) {
        return false;
      }
      successModal.classList.remove('hidden');
      toggleBodyScroll(true);
      return true;
    }

    function closeSuccessModal(shouldRedirect) {
      if (!successModal) {
        return;
      }
      successModal.classList.add('hidden');
      toggleBodyScroll(false);

      if (shouldRedirect && successRedirectUrl) {
        window.location.href = successRedirectUrl;
      }
    }

    function showSuccessMessage(message) {
      if (openSuccessModal()) {
        var messageNode = successModal.querySelector('[data-guestbook-success-message]');
        if (messageNode && message) {
          messageNode.textContent = message;
        }
        return;
      }

      if (successFeedback) {
        successFeedback.textContent = message || '提交成功';
        successFeedback.hidden = false;
      }
    }

    function hideSuccessMessage() {
      if (successFeedback) {
        successFeedback.hidden = true;
        successFeedback.textContent = '';
      }
    }

    function setFormError(message) {
      if (!formError) {
        return;
      }
      formError.textContent = message || '';
      formError.hidden = !message;
    }

    function setFieldState(key, state, message) {
      var field = fields[key];
      var errorNode = getErrorNode(key);
      if (!field) {
        return;
      }

      field.classList.remove('is-error', 'is-valid');
      field.removeAttribute('aria-invalid');
      if (errorNode) {
        errorNode.textContent = '';
        errorNode.hidden = true;
        errorNode.classList.remove('is-error');
        errorNode.classList.remove('is-valid');
      }

      if (!state) {
        return;
      }

      field.classList.add(state === 'error' ? 'is-error' : 'is-valid');
      if (state === 'error') {
        field.setAttribute('aria-invalid', 'true');
      }

      if (errorNode && message) {
        errorNode.textContent = message;
        errorNode.hidden = false;
        if (state === 'error') {
          errorNode.classList.add('is-error');
        }
        if (state === 'valid') {
          errorNode.classList.add('is-valid');
        }
      }
    }

    function syncCounter() {
      if (!fields.content || !contentCounter) {
        return;
      }
      var limit = Number.parseInt(fields.content.getAttribute('maxlength') || '1000', 10);
      var length = countChars(fields.content.value || '');
      contentCounter.textContent = length + ' / ' + limit;
      contentCounter.classList.toggle('is-near-limit', length >= Math.max(0, limit - 120) && length <= limit);
      contentCounter.classList.toggle('is-over-limit', length > limit);
    }

    function buildContentPayload() {
      var lines = [];
      extraFields.forEach(function (field) {
        var value = (field.value || '').trim();
        if (!value) {
          return;
        }
        var label = field.getAttribute('data-guestbook-extra-label') || '';
        lines.push(label ? label + '：' + value : value);
      });

      var contentValue = fields.content ? (fields.content.value || '').trim() : '';
      if (contentValue) {
        var contentLabel = fields.content.getAttribute('data-guestbook-content-label') || '';
        lines.push(contentLabel ? contentLabel + '：' + contentValue : contentValue);
      }

      return lines.join('\n');
    }

    function validateField(key, mode) {
      var field = fields[key];
      if (!field) {
        return { valid: true, message: '' };
      }

      var raw = field.value || '';
      var value = raw.trim();
      var result = { valid: true, message: '' };

      if (key === 'name') {
        if (value === '') {
          result.valid = false;
          result.message = '请输入你的称呼。';
        } else if (value.length < 2 || value.length > 20 || !/^[\u4e00-\u9fa5A-Za-z]+(?:[·•\s][\u4e00-\u9fa5A-Za-z]+)*$/.test(value)) {
          result.valid = false;
          result.message = '你的称呼格式错误，请重新输入。';
        }
      } else if (key === 'phone') {
        var digits = value.replace(/\D+/g, '');
        if (raw !== digits) {
          field.value = digits;
          value = digits;
        }
        if (value === '') {
          result.valid = false;
          result.message = '请输入联系电话。';
        } else if (!/^1[3-9]\d{9}$/.test(value)) {
          result.valid = false;
          result.message = '请输入正确的手机号码。';
        }
      } else if (key === 'content') {
        var payloadLength = countChars(buildContentPayload());
        if (value === '') {
          result.valid = false;
          result.message = '请输入你的需求。';
        } else if (payloadLength > 1000) {
          result.valid = false;
          result.message = '你的需求内容不能超过 1000 字。';
        } else if (raw.length > 0 && value.replace(/\s+/g, '') === '') {
          result.valid = false;
          result.message = '留言内容不能为空白字符，请重新填写。';
        }
      } else if (key === 'captcha') {
        if (!isCaptchaVisible()) {
          return result;
        }
        var normalized = value.toUpperCase();
        field.value = normalized.slice(0, 4);
        normalized = field.value.trim();
        if (normalized === '') {
          result.valid = false;
          result.message = '请输入验证码。';
        } else if (!/^[A-Z0-9]{4}$/.test(normalized)) {
          result.valid = false;
          result.message = '验证码格式错误，请输入 4 位验证码。';
        } else if (captchaVerifiedValue === normalized) {
          result.message = '输入正确';
        }
      }

      if (mode === 'input' && value === '' && key !== 'captcha') {
        setFieldState(key, '', '');
        return result;
      }

      if (!result.valid) {
        setFieldState(key, 'error', result.message);
      } else if (value !== '') {
        setFieldState(key, 'valid', result.message || '输入正确');
      } else {
        setFieldState(key, '', '');
      }

      return result;
    }

    function refreshCaptcha() {
      if (!captchaImage || !isCaptchaVisible()) {
        return;
      }
      var separator = captchaImageUrl.indexOf('?') === -1 ? '?' : '&';
      captchaImage.src = captchaImageUrl + separator + 't=' + Date.now();
      captchaVerifiedValue = '';
      captchaVerifySerial += 1;
      setFieldState('captcha', '', '');
    }

    function showCaptchaBlock() {
      if (!captchaBlock) {
        return;
      }
      if (captchaBlock.hasAttribute('hidden')) {
        captchaBlock.removeAttribute('hidden');
      }
      refreshCaptcha();
    }

    async function verifyCaptchaValue() {
      if (!fields.captcha || !isCaptchaVisible()) {
        return true;
      }
      var normalized = (fields.captcha.value || '').trim().toUpperCase();
      if (!/^[A-Z0-9]{4}$/.test(normalized)) {
        setFieldState('captcha', 'error', '验证码格式错误，请输入 4 位验证码。');
        return false;
      }

      var currentSerial = ++captchaVerifySerial;
      try {
        if (!csrfToken) {
          setFieldState('captcha', 'error', '验证码校验失败，请刷新页面后重试。');
          return false;
        }

        var verifyPayload = new URLSearchParams();
        verifyPayload.set('captcha', normalized);

        var response = await fetch(captchaVerifyUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: verifyPayload.toString(),
          credentials: 'same-origin'
        });

        var data = await response.json();
        if (currentSerial !== captchaVerifySerial) {
          return false;
        }

        if (!response.ok) {
          setFieldState('captcha', 'error', data && data.message ? data.message : '验证码校验失败，请稍后重试。');
          return false;
        }

        if (data && data.valid) {
          captchaVerifiedValue = normalized;
          setFieldState('captcha', 'valid', '输入正确');
          return true;
        }

        captchaVerifiedValue = '';
        setFieldState('captcha', 'error', data && data.message ? data.message : '验证码不正确，请重新输入。');
        return false;
      } catch (error) {
        if (currentSerial === captchaVerifySerial) {
          captchaVerifiedValue = '';
          setFieldState('captcha', 'error', '验证码校验失败，请稍后重试。');
        }
        return false;
      }
    }

    function applyServerErrors(errors) {
      var firstInvalidField = null;
      ['name', 'phone', 'content', 'captcha'].forEach(function (key) {
        var message = errors[key] && errors[key][0] ? errors[key][0] : '';
        if (!message) {
          return;
        }
        if (key === 'captcha') {
          showCaptchaBlock();
        }
        setFieldState(key, 'error', message);
        if (!firstInvalidField && fields[key]) {
          firstInvalidField = fields[key];
        }
      });

      var formMessage = errors.form && errors.form[0] ? errors.form[0] : '';
      setFormError(formMessage);

      if (firstInvalidField) {
        firstInvalidField.focus();
      }
    }

    function resetFormState() {
      form.reset();
      captchaVerifiedValue = '';
      hideSuccessMessage();
      setFormError('');
      ['name', 'phone', 'content', 'captcha'].forEach(function (key) {
        setFieldState(key, '', '');
      });
      syncCounter();
      if (isCaptchaVisible()) {
        refreshCaptcha();
      }
    }

    async function submitForm(event) {
      event.preventDefault();
      hideSuccessMessage();
      setFormError('');
      syncCounter();

      var nameValid = validateField('name', 'submit').valid;
      var phoneValid = validateField('phone', 'submit').valid;
      var contentValid = validateField('content', 'submit').valid;
      var captchaValid = validateField('captcha', 'submit').valid;

      if (!nameValid || !phoneValid || !contentValid || !captchaValid) {
        return;
      }

      if (isCaptchaVisible()) {
        var verified = await verifyCaptchaValue();
        if (!verified) {
          if (fields.captcha) {
            fields.captcha.focus();
          }
          return;
        }
      }

      if (!csrfToken) {
        setFormError('提交失败，请刷新页面后重试。');
        return;
      }

      var payload = new URLSearchParams();
      payload.set('name', fields.name ? fields.name.value.trim() : '');
      payload.set('phone', fields.phone ? fields.phone.value.trim() : '');
      payload.set('content', buildContentPayload());
      payload.set('captcha', isCaptchaVisible() && fields.captcha ? fields.captcha.value.trim() : '');
      payload.set('website', honeypotField ? (honeypotField.value || '').trim() : '');

      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        var response = await fetch(submitUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: payload.toString(),
          credentials: 'same-origin'
        });

        var data = await response.json().catch(function () {
          return {};
        });

        if (response.ok) {
          resetFormState();
          showSuccessMessage(data && data.message ? data.message : '留言已提交。');
          return;
        }

        if (response.status === 422) {
          applyServerErrors(data && data.errors ? data.errors : {});
          return;
        }

        if (data && data.message) {
          setFormError(data.message);
          return;
        }

        setFormError('提交失败，请稍后再试。');
      } catch (error) {
        setFormError('提交失败，请稍后再试。');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    }

    form.addEventListener('submit', submitForm);

    if (fields.name) {
      fields.name.addEventListener('input', function () { validateField('name', 'input'); });
      fields.name.addEventListener('blur', function () { validateField('name', 'blur'); });
    }

    if (fields.phone) {
      fields.phone.addEventListener('input', function () { validateField('phone', 'input'); });
      fields.phone.addEventListener('blur', function () { validateField('phone', 'blur'); });
    }

    if (fields.content) {
      fields.content.addEventListener('input', function () {
        syncCounter();
        validateField('content', 'input');
      });
      fields.content.addEventListener('blur', function () { validateField('content', 'blur'); });
    }

    extraFields.forEach(function (field) {
      field.addEventListener('input', syncCounter);
    });

    if (fields.captcha) {
      fields.captcha.addEventListener('input', function () {
        if (!isCaptchaVisible()) {
          return;
        }
        fields.captcha.value = fields.captcha.value.toUpperCase().slice(0, 4);
        if (captchaVerifiedValue && captchaVerifiedValue !== fields.captcha.value.trim().toUpperCase()) {
          captchaVerifiedValue = '';
        }
        validateField('captcha', 'input');
      });
      fields.captcha.addEventListener('blur', function () {
        if (!isCaptchaVisible()) {
          return;
        }
        validateField('captcha', 'blur');
      });
    }

    if (captchaTrigger) {
      captchaTrigger.addEventListener('click', refreshCaptcha);
      captchaTrigger.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          refreshCaptcha();
        }
      });
    }

    if (successClose) {
      successClose.addEventListener('click', function () {
        closeSuccessModal(true);
      });
    }

    if (successBackdrop) {
      successBackdrop.addEventListener('click', function () {
        closeSuccessModal(false);
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && successModal && !successModal.classList.contains('hidden')) {
        closeSuccessModal(false);
      }
    });

    syncCounter();
    return true;
  }
