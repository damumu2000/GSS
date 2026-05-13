<?php

namespace App\Modules\Guestbook\Controllers\Frontend;

use App\Http\Controllers\SiteController;
use App\Jobs\SendGuestbookMessageNotificationJob;
use App\Modules\Guestbook\Support\GuestbookModule;
use App\Modules\Guestbook\Support\GuestbookSettings;
use App\Support\SystemSettings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GuestbookController extends SiteController
{
    public function __construct(
        protected GuestbookModule $guestbookModule,
        protected GuestbookSettings $guestbookSettings,
        protected SystemSettings $systemSettings
    ) {
    }

    public function index(Request $request): Response
    {
        [$site, $settings, $siteQuery, $available] = $this->resolveGuestbookContext($request);
        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        $messages = DB::table('module_guestbook_messages')
            ->where('site_id', $site->id)
            ->when($settings['show_after_reply'], fn ($query) => $query->where('status', 'replied'))
            ->orderByDesc('created_at')
            ->paginate(8)
            ->withQueryString()
            ->through(fn ($message) => $this->messagePayload($message, $settings));

        return response()->view('guestbook::frontend.index', [
            'site' => $site,
            'settings' => $settings,
            'messages' => $messages,
            'siteQuery' => $siteQuery,
        ]);
    }

    public function create(Request $request): Response
    {
        [$site, $settings, $siteQuery, $available] = $this->resolveGuestbookContext($request);
        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        return response()->view('guestbook::frontend.create', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'captchaUrl' => route('site.guestbook.captcha', $siteQuery),
        ]);
    }

    public function detail(Request $request, int $displayNo): Response
    {
        [$site, $settings, $siteQuery, $available] = $this->resolveGuestbookContext($request);
        if (! $available) {
            return $this->disabledResponse($site, $settings, $siteQuery);
        }

        $message = DB::table('module_guestbook_messages')
            ->where('site_id', $site->id)
            ->where('display_no', $displayNo)
            ->first();

        abort_unless($message && $this->messageIsPublic($message, $settings), 404);

        return response()->view('guestbook::frontend.show', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
            'message' => $this->messagePayload($message, $settings),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        [$site, $settings, $siteQuery, $available] = $this->resolveGuestbookContext($request);
        if (! $available) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '当前留言板暂未开放，请稍后再试。',
                ], 422);
            }

            return redirect()
                ->route('site.guestbook.index', $siteQuery)
                ->with('status', '当前留言板暂未开放，请稍后再试。');
        }

        $rawPhone = $this->sanitizePlainText($request->input('phone'));

        $request->merge([
            'name' => $this->sanitizePlainText($request->input('name')),
            'phone' => $this->sanitizePhone($request->input('phone')),
            'content' => $this->sanitizePlainText($request->input('content')),
            'captcha' => $this->sanitizePlainText($request->input('captcha')),
            'website' => $this->sanitizePlainText($request->input('website')),
        ]);

        $siteId = (int) $site->id;
        $phone = (string) $request->input('phone', '');

        if ((string) $request->input('website', '') !== '') {
            $this->rememberSubmissionFailure($request, $siteId);
            $this->rememberSubmissionAttempt($request, $siteId, $phone);

            throw ValidationException::withMessages([
                'form' => '提交失败，请稍后再试。',
            ]);
        }

        $this->ensureSubmissionAllowed($request, $siteId, $phone);
        $captchaRequired = $this->shouldRequireCaptcha($request, $siteId, $phone, $settings);

        if ($captchaRequired) {
            $request->session()->flash('guestbook_captcha_required', true);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\p{Han}A-Za-z]+(?:[·•\s][\p{Han}A-Za-z]+)*$/u'],
            'phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'content' => ['required', 'string', 'max:1000'],
            'captcha' => [$captchaRequired ? 'required' : 'nullable', 'string', 'size:4'],
        ], [
            'name.required' => '请输入你的称呼。',
            'name.min' => '你的称呼格式错误，请重新输入。',
            'name.max' => '你的称呼格式错误，请重新输入。',
            'name.regex' => '你的称呼格式错误，请重新输入。',
            'phone.required' => $rawPhone === null ? '请输入联系电话。' : '联系电话格式错误，请输入正确的手机号码。',
            'phone.regex' => '联系电话格式错误，请输入正确的手机号码。',
            'content.required' => '请输入你的需求。',
            'content.max' => '你的需求内容不能超过 1000 字。',
            'captcha.required' => '请输入验证码。',
            'captcha.size' => '验证码格式错误，请输入 4 位验证码。',
        ]);

        $validator->after(function ($validator) use ($request, $settings, $captchaRequired): void {
            if (! $settings['captcha_enabled'] || ! $captchaRequired) {
                return;
            }

            $submitted = strtoupper(trim((string) $request->input('captcha', '')));
            $expected = strtoupper((string) $request->session()->get('guestbook_captcha', ''));

            if ($submitted === '' || $expected === '' || $submitted !== $expected) {
                $validator->errors()->add('captcha', '验证码不正确，请重新输入。');
            }
        });

        if ($validator->fails()) {
            if ($validator->errors()->has('captcha') || $validator->errors()->has('form')) {
                $this->rememberSubmissionFailure($request, $siteId);
            }

            $this->rememberSubmissionAttempt($request, $siteId, $phone);
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $messageId = 0;
        $displayNo = DB::transaction(function () use ($site, $validated, $request, &$messageId): int {
            $displayNo = ((int) DB::table('module_guestbook_messages')
                ->where('site_id', $site->id)
                ->max('display_no')) + 1;

            $messageId = (int) DB::table('module_guestbook_messages')->insertGetId([
                'site_id' => $site->id,
                'display_no' => $displayNo,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'content' => $validated['content'],
                'status' => 'pending',
                'is_read' => 0,
                'reply_content' => null,
                'replied_at' => null,
                'replied_by' => null,
                'ip_address' => (string) $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $displayNo;
        });

        $request->session()->forget('guestbook_captcha');
        $request->session()->forget('guestbook_captcha_required');
        $this->rememberSubmissionAttempt($request, $siteId, $phone);
        $this->rememberSuccessfulSubmission($request, $siteId, $phone);
        $this->dispatchNotificationIfNeeded($siteId, $messageId, $settings, 'submitted');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '留言已提交，编号为 '.$this->displayNo($displayNo).'。',
                'display_no' => $this->displayNo($displayNo),
            ]);
        }

        return redirect()
            ->route('site.guestbook.index', $siteQuery)
            ->with('status', '留言已提交，编号为 '.$this->displayNo($displayNo).'。');
    }

    public function captcha(Request $request): Response
    {
        [, , , $available] = $this->resolveGuestbookContext($request);
        abort_unless($available, 404);

        $code = strtoupper(Str::random(4));
        $request->session()->put('guestbook_captcha', $code);

        $image = imagecreatetruecolor(120, 40);
        $background = imagecolorallocate($image, 248, 250, 252);
        $border = imagecolorallocate($image, 226, 232, 240);
        $textColor = imagecolorallocate($image, 31, 41, 55);
        $noiseColor = imagecolorallocate($image, 203, 213, 225);

        imagefill($image, 0, 0, $background);
        imagerectangle($image, 0, 0, 119, 39, $border);

        for ($i = 0; $i < 20; $i++) {
            imagesetpixel($image, random_int(0, 119), random_int(0, 39), $noiseColor);
        }

        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($code);
        $textHeight = imagefontheight($font);
        $textX = max(0, (int) floor((120 - $textWidth) / 2));
        $textY = max(0, (int) floor((40 - $textHeight) / 2));

        imagestring($image, $font, $textX, $textY, $code, $textColor);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function verifyCaptcha(Request $request): JsonResponse
    {
        [$site, , , $available] = $this->resolveGuestbookContext($request);
        abort_unless($available, 404);

        $siteId = (int) $site->id;
        $verifyMaxAttempts = $this->systemSettings->guestbookLimitCaptchaVerifyMaxAttempts();
        $verifyWindowSeconds = $this->systemSettings->guestbookLimitCaptchaVerifyWindowSeconds();
        $verifyBlockSeconds = $this->systemSettings->guestbookLimitCaptchaVerifyBlockSeconds();
        $blockKey = $this->captchaVerifyBlockKey($siteId, $request);
        if (RateLimiter::tooManyAttempts($blockKey, 1)) {
            $this->logGuestbookRiskEvent($request, $siteId, 'captcha_verify_block_active', [
                'verify_window_seconds' => $verifyWindowSeconds,
                'verify_max_attempts' => $verifyMaxAttempts,
                'verify_block_seconds' => $verifyBlockSeconds,
            ]);
            return response()->json([
                'valid' => false,
                'message' => '验证码校验过于频繁，请稍后再试。',
            ], 429);
        }

        $limitKey = $this->captchaVerifyRateLimitKey($siteId, $request);
        if (RateLimiter::tooManyAttempts($limitKey, $verifyMaxAttempts)) {
            RateLimiter::hit($blockKey, $verifyBlockSeconds);
            $this->logGuestbookRiskEvent($request, $siteId, 'captcha_verify_rate_limited', [
                'verify_window_seconds' => $verifyWindowSeconds,
                'verify_max_attempts' => $verifyMaxAttempts,
                'verify_block_seconds' => $verifyBlockSeconds,
            ]);
            return response()->json([
                'valid' => false,
                'message' => '验证码校验过于频繁，请稍后再试。',
            ], 429);
        }
        RateLimiter::hit($limitKey, $verifyWindowSeconds);

        $submitted = strtoupper((string) ($this->sanitizePlainText($request->input('captcha')) ?? ''));
        if ($submitted === '' || strlen($submitted) !== 4) {
            return response()->json([
                'valid' => false,
                'message' => '验证码格式错误，请输入 4 位验证码。',
            ]);
        }

        $expected = strtoupper((string) $request->session()->get('guestbook_captcha', ''));
        $isValid = $expected !== '' && hash_equals($expected, $submitted);

        return response()->json([
            'valid' => $isValid,
            'message' => $isValid ? '输入正确' : '验证码不正确，请重新输入。',
        ]);
    }

    /**
     * @return array{0: object, 1: array<string,mixed>, 2: array<string,string>, 3: bool}
     */
    protected function resolveGuestbookContext(Request $request): array
    {
        $site = $this->resolvedGuestbookSite($request);
        abort_unless($site, 404);
        if ($disabled = $this->renderWhenFrontendDisabled($site)) {
            throw new HttpResponseException($disabled);
        }

        $settings = $this->guestbookSettings->forSite((int) $site->id);
        $module = $this->guestbookModule->activeForSite((int) $site->id);
        $siteQuery = $request->query('site') ? ['site' => $site->site_key] : [];
        $available = is_array($module) && $settings['enabled'];

        return [$site, $settings, $siteQuery, $available];
    }

    protected function resolvedGuestbookSite(Request $request): ?object
    {
        $host = mb_strtolower(trim((string) $request->getHost()));

        if ($host !== '') {
            $site = DB::table('site_domains')
                ->join('sites', 'sites.id', '=', 'site_domains.site_id')
                ->whereRaw('LOWER(site_domains.domain) = ?', [$host])
                ->where('site_domains.status', 1)
                ->where('sites.status', 1)
                ->first(['sites.*']);

            if ($site) {
                return $site;
            }
        }

        if (! $this->allowsPreviewSiteQuery($host)) {
            return null;
        }

        $siteKey = trim((string) $request->query('site', ''));
        if ($siteKey === '') {
            return null;
        }

        return DB::table('sites')
            ->where('site_key', $siteKey)
            ->where('status', 1)
            ->first();
    }

    protected function allowsPreviewSiteQuery(string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    /**
     * @param  array<string,mixed>  $settings
     * @param  array<string,string>  $siteQuery
     */
    protected function disabledResponse(object $site, array $settings, array $siteQuery): Response
    {
        return response()->view('guestbook::frontend.disabled', [
            'site' => $site,
            'settings' => $settings,
            'siteQuery' => $siteQuery,
        ]);
    }

    protected function ensureSubmissionAllowed(Request $request, int $siteId, string $phone): void
    {
        $ipMaxAttempts = $this->systemSettings->guestbookLimitIpMaxAttempts();
        $phoneMaxAttempts = $this->systemSettings->guestbookLimitPhoneMaxAttempts();
        $ipBlockSeconds = $this->systemSettings->guestbookLimitIpBlockSeconds();
        $phoneBlockSeconds = $this->systemSettings->guestbookLimitPhoneBlockSeconds();
        $ipBlockKey = $this->ipBlockRateLimitKey($request, $siteId);
        if (RateLimiter::tooManyAttempts($ipBlockKey, 1)) {
            $this->logGuestbookRiskEvent($request, $siteId, 'submit_ip_block_active', [
                'ip_window_seconds' => $this->systemSettings->guestbookLimitIpWindowSeconds(),
                'ip_max_attempts' => $ipMaxAttempts,
                'ip_block_seconds' => $ipBlockSeconds,
            ], $phone);
            throw ValidationException::withMessages([
                'form' => '提交过于频繁，请稍后再试。',
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->ipRateLimitKey($request, $siteId), $ipMaxAttempts)) {
            RateLimiter::hit($ipBlockKey, $ipBlockSeconds);
            $this->logGuestbookRiskEvent($request, $siteId, 'submit_ip_rate_limited', [
                'ip_window_seconds' => $this->systemSettings->guestbookLimitIpWindowSeconds(),
                'ip_max_attempts' => $ipMaxAttempts,
                'ip_block_seconds' => $ipBlockSeconds,
            ], $phone);
            throw ValidationException::withMessages([
                'form' => '提交过于频繁，请稍后再试。',
            ]);
        }

        if ($phone !== '') {
            $phoneBlockKey = $this->phoneBlockRateLimitKey($siteId, $phone);
            if (RateLimiter::tooManyAttempts($phoneBlockKey, 1)) {
                $this->logGuestbookRiskEvent($request, $siteId, 'submit_phone_block_active', [
                    'phone_window_seconds' => $this->systemSettings->guestbookLimitPhoneWindowSeconds(),
                    'phone_max_attempts' => $phoneMaxAttempts,
                    'phone_block_seconds' => $phoneBlockSeconds,
                ], $phone);
                throw ValidationException::withMessages([
                    'form' => '提交过于频繁，请稍后再试。',
                ]);
            }

            if (RateLimiter::tooManyAttempts($this->phoneRateLimitKey($siteId, $phone), $phoneMaxAttempts)) {
                RateLimiter::hit($phoneBlockKey, $phoneBlockSeconds);
                $this->logGuestbookRiskEvent($request, $siteId, 'submit_phone_rate_limited', [
                    'phone_window_seconds' => $this->systemSettings->guestbookLimitPhoneWindowSeconds(),
                    'phone_max_attempts' => $phoneMaxAttempts,
                    'phone_block_seconds' => $phoneBlockSeconds,
                ], $phone);
                throw ValidationException::withMessages([
                    'form' => '提交过于频繁，请稍后再试。',
                ]);
            }
        }
    }

    protected function shouldRequireCaptcha(Request $request, int $siteId, string $phone, array $settings): bool
    {
        if (! ($settings['captcha_enabled'] ?? false)) {
            return false;
        }

        if (RateLimiter::attempts($this->captchaFailureKey($request, $siteId)) >= 1) {
            return true;
        }

        if (RateLimiter::attempts($this->captchaTriggerIpKey($request, $siteId)) >= 2) {
            return true;
        }

        return $phone !== '' && RateLimiter::attempts($this->captchaTriggerPhoneKey($siteId, $phone)) >= 1;
    }

    protected function rememberSubmissionAttempt(Request $request, int $siteId, string $phone): void
    {
        $ipWindowSeconds = $this->systemSettings->guestbookLimitIpWindowSeconds();
        $phoneWindowSeconds = $this->systemSettings->guestbookLimitPhoneWindowSeconds();

        RateLimiter::hit($this->ipRateLimitKey($request, $siteId), $ipWindowSeconds);

        if ($phone !== '') {
            RateLimiter::hit($this->phoneRateLimitKey($siteId, $phone), $phoneWindowSeconds);
        }
    }

    protected function rememberSuccessfulSubmission(Request $request, int $siteId, string $phone): void
    {
        RateLimiter::hit($this->captchaTriggerIpKey($request, $siteId), 600);
        RateLimiter::clear($this->captchaFailureKey($request, $siteId));

        if ($phone !== '') {
            RateLimiter::hit($this->captchaTriggerPhoneKey($siteId, $phone), 1800);
        }
    }

    protected function rememberSubmissionFailure(Request $request, int $siteId): void
    {
        RateLimiter::hit($this->captchaFailureKey($request, $siteId), 600);
    }

    protected function ipRateLimitKey(Request $request, int $siteId): string
    {
        return 'guestbook-submit-ip:'.$siteId.':'.sha1((string) $request->ip());
    }

    protected function phoneRateLimitKey(int $siteId, string $phone): string
    {
        return 'guestbook-submit-phone:'.$siteId.':'.sha1($phone);
    }

    protected function captchaTriggerIpKey(Request $request, int $siteId): string
    {
        return 'guestbook-submit-captcha-ip:'.$siteId.':'.sha1((string) $request->ip());
    }

    protected function captchaTriggerPhoneKey(int $siteId, string $phone): string
    {
        return 'guestbook-submit-captcha-phone:'.$siteId.':'.sha1($phone);
    }

    protected function captchaFailureKey(Request $request, int $siteId): string
    {
        $sessionPart = (string) $request->session()->getId();

        return 'guestbook-submit-captcha-fail:'.$siteId.':'.sha1((string) $request->ip().':'.$sessionPart);
    }

    protected function captchaVerifyRateLimitKey(int $siteId, Request $request): string
    {
        $sessionPart = (string) $request->session()->getId();

        return 'guestbook-captcha-verify:'.$siteId.':'.sha1((string) $request->ip().':'.$sessionPart);
    }

    protected function captchaVerifyBlockKey(int $siteId, Request $request): string
    {
        $sessionPart = (string) $request->session()->getId();

        return 'guestbook-captcha-verify-block:'.$siteId.':'.sha1((string) $request->ip().':'.$sessionPart);
    }

    protected function ipBlockRateLimitKey(Request $request, int $siteId): string
    {
        return 'guestbook-submit-ip-block:'.$siteId.':'.sha1((string) $request->ip());
    }

    protected function phoneBlockRateLimitKey(int $siteId, string $phone): string
    {
        return 'guestbook-submit-phone-block:'.$siteId.':'.sha1($phone);
    }

    protected function dispatchNotificationIfNeeded(int $siteId, int $messageId, array $settings, string $trigger): void
    {
        if ($messageId <= 0) {
            return;
        }

        if (! ($settings['email_notify_enabled'] ?? false)) {
            return;
        }

        if (($settings['email_notify_on'] ?? 'submitted') !== $trigger) {
            return;
        }

        SendGuestbookMessageNotificationJob::dispatch($siteId, $messageId, $trigger);
    }

    /**
     * @param  object  $message
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    protected function messagePayload(object $message, array $settings): array
    {
        $summary = $this->makeSummary((string) $message->content);

        return [
            'display_no' => $this->displayNo((int) $message->display_no),
            'content' => (string) $message->content,
            'summary' => $summary,
            'status_label' => (string) $message->status === 'replied' ? '已办理' : '待办理',
            'read_label' => (bool) $message->is_read ? '已浏览' : '未浏览',
            'created_at_label' => $message->created_at ? date('Y-m-d', strtotime((string) $message->created_at)) : '',
            'replied_at_label' => $message->replied_at ? date('Y-m-d', strtotime((string) $message->replied_at)) : '',
            'name' => $settings['show_name']
                ? (string) $message->name
                : $this->maskName((string) $message->name),
            'reply_content' => (string) ($message->reply_content ?? ''),
        ];
    }

    protected function messageIsPublic(object $message, array $settings): bool
    {
        return ! $settings['show_after_reply'] || (string) $message->status === 'replied';
    }

    protected function displayNo(int $displayNo): string
    {
        return str_pad((string) $displayNo, 5, '0', STR_PAD_LEFT);
    }

    protected function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $first = mb_substr($name, 0, 1, 'UTF-8');

        return $first.'***';
    }

    protected function makeSummary(string $content): string
    {
        return Str::limit($content, 220, '...');
    }

    protected function logGuestbookRiskEvent(Request $request, int $siteId, string $action, array $payload, string $phone = ''): void
    {
        $maskedPhone = $this->maskPhone($phone);
        if ($maskedPhone !== '') {
            $payload['phone_masked'] = $maskedPhone;
        }

        $this->logOperation(
            'site',
            'guestbook',
            $action,
            $siteId,
            null,
            'guestbook_risk',
            null,
            $payload,
            $request,
        );
    }

    protected function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        if (! preg_match('/^\d{11}$/', $phone)) {
            return '';
        }

        return substr($phone, 0, 3).'****'.substr($phone, -4);
    }

    protected function sanitizePlainText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $cleaned = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $value) ?? $value;
        $cleaned = preg_replace('/[ \t]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    protected function sanitizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $cleaned = preg_replace('/\D+/u', '', $value) ?? '';
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }
}
