<?php

namespace App\Modules\Guestbook\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Modules\Guestbook\Support\GuestbookAttachmentRelationSync;
use App\Modules\Guestbook\Support\GuestbookModule;
use App\Modules\Guestbook\Support\GuestbookSettings;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuestbookController extends Controller
{
    public function __construct(
        protected GuestbookModule $guestbookModule,
        protected GuestbookSettings $guestbookSettings
    ) {
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'guestbook.view');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $settings = $this->guestbookSettings->forSite((int) $currentSite->id);

        $keyword = trim((string) $request->query('keyword', ''));
        $readStatus = trim((string) $request->query('read_status', ''));
        $replyStatus = trim((string) $request->query('reply_status', ''));

        $messages = DB::table('module_guestbook_messages')
            ->where('site_id', $currentSite->id)
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $query->where(function ($subQuery) use ($keyword): void {
                    $subQuery->where('name', 'like', '%'.$keyword.'%')
                        ->orWhere('phone', 'like', '%'.$keyword.'%')
                        ->orWhere('content', 'like', '%'.$keyword.'%')
                        ->orWhere('reply_content', 'like', '%'.$keyword.'%');
                });
            })
            ->when($readStatus === 'read', fn ($query) => $query->where('is_read', 1))
            ->when($readStatus === 'unread', fn ($query) => $query->where('is_read', 0))
            ->when($replyStatus === 'replied', fn ($query) => $query->where('status', 'replied'))
            ->when($replyStatus === 'pending', fn ($query) => $query->where('status', 'pending'))
            ->orderByDesc('created_at')
            ->paginate(8)
            ->withQueryString()
            ->through(fn ($message) => $this->messagePayload($message, $settings));

        $stats = [
            'total' => (int) DB::table('module_guestbook_messages')->where('site_id', $currentSite->id)->count(),
            'pending' => (int) DB::table('module_guestbook_messages')->where('site_id', $currentSite->id)->where('status', 'pending')->count(),
            'replied' => (int) DB::table('module_guestbook_messages')->where('site_id', $currentSite->id)->where('status', 'replied')->count(),
            'unread' => (int) DB::table('module_guestbook_messages')->where('site_id', $currentSite->id)->where('is_read', 0)->count(),
        ];

        return view('guestbook::admin.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'settings' => $settings,
            'guestbookPreviewUrl' => $this->guestbookPreviewUrl($request, $currentSite),
            'messages' => $messages,
            'stats' => $stats,
            'keyword' => $keyword,
            'readStatus' => $readStatus,
            'replyStatus' => $replyStatus,
            'canManageSettings' => in_array('guestbook.setting', $this->sitePermissionCodes((int) $request->user()->id, (int) $currentSite->id), true),
        ]);
    }

    public function show(Request $request, string $messageId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'guestbook.view');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $settings = $this->guestbookSettings->forSite((int) $currentSite->id);
        $message = $this->findMessageOrAbort((int) $currentSite->id, $messageId);

        if (! $message->is_read) {
            DB::table('module_guestbook_messages')
                ->where('id', $message->id)
                ->update([
                    'is_read' => 1,
                    'read_at' => now(),
                    'updated_at' => now(),
                ]);

            $message->is_read = 1;
            $message->read_at = now();
        }

        return view('guestbook::admin.show', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'settings' => $settings,
            'guestbookPreviewUrl' => $this->guestbookPreviewUrl($request, $currentSite),
            'canManageMessage' => in_array('guestbook.manage', $this->sitePermissionCodes((int) $request->user()->id, (int) $currentSite->id), true),
            'message' => $this->messagePayload($message, $settings),
        ]);
    }

    public function update(Request $request, string $messageId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'guestbook.reply');
        $this->resolveModuleOrAbort((int) $currentSite->id);
        $settings = $this->guestbookSettings->forSite((int) $currentSite->id);
        $message = $this->findMessageOrAbort((int) $currentSite->id, $messageId);

        $request->merge([
            'content' => $this->sanitizePlainText($request->input('content')),
            'reply_content' => $this->sanitizePlainText($request->input('reply_content')),
        ]);

        $validated = Validator::make($request->all(), [
            'content' => ['required', 'string', 'max:1000'],
            'reply_content' => ['nullable', 'string', 'max:1000'],
        ], [
            'content.required' => '请填写留言内容。',
            'content.max' => '留言内容不能超过 1000 字。',
            'reply_content.max' => '回复内容不能超过 1000 字。',
        ])->validate();

        $content = trim((string) ($validated['content'] ?? ''));
        $replyContent = trim((string) ($validated['reply_content'] ?? ''));
        $isReplied = $replyContent !== '';
        $currentContent = (string) $message->content;
        $canManageMessage = in_array('guestbook.manage', $this->sitePermissionCodes((int) $request->user()->id, (int) $currentSite->id), true);
        if ($content !== $currentContent && ! $canManageMessage) {
            throw ValidationException::withMessages([
                'content' => '当前角色仅可回复留言，不能修改原始留言内容。',
            ]);
        }
        $originalContent = isset($message->original_content) ? (string) ($message->original_content ?? '') : '';
        $nextOriginalContent = $originalContent !== '' ? $originalContent : null;

        if ($content !== $currentContent) {
            $nextOriginalContent = $originalContent !== '' ? $originalContent : $currentContent;

            if ($content === $nextOriginalContent) {
                $nextOriginalContent = null;
            }
        }

        DB::table('module_guestbook_messages')
            ->where('id', $message->id)
            ->update([
                'content' => $content,
                'original_content' => $nextOriginalContent,
                'reply_content' => $replyContent !== '' ? $replyContent : null,
                'status' => $isReplied ? 'replied' : 'pending',
                'is_read' => 1,
                'read_at' => now(),
                'replied_at' => $isReplied ? now() : null,
                'replied_by' => $isReplied ? $request->user()->id : null,
                'updated_at' => now(),
            ]);

        $this->logOperation(
            'site',
            'guestbook',
            'update',
            $currentSite->id,
            $request->user()->id,
            'guestbook_message',
            $message->id,
            ['display_no' => $message->display_no, 'status' => $isReplied ? 'replied' : 'pending'],
            $request,
        );

        return redirect()
            ->route('admin.guestbook.show', $message->id)
            ->with('status', '留言已更新。');
    }

    public function settings(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'guestbook.setting');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $settings = $this->guestbookSettings->forSite((int) $currentSite->id);

        return view('guestbook::admin.settings', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'guestbookPreviewUrl' => $this->guestbookPreviewUrl($request, $currentSite),
            'themeOptions' => $this->guestbookSettings->themeOptions(),
            'attachmentLibraryWorkspaceAccess' => $this->canAccessAttachmentWorkspace((int) $request->user()->id, (int) $currentSite->id),
            'settings' => [
                'enabled' => old('enabled', $settings['enabled']) ? '1' : '0',
                'name' => old('name', $settings['name']),
                'notice' => old('notice', $settings['notice']),
                'notice_image' => old('notice_image', $settings['notice_image']),
                'theme' => old('theme', $settings['theme']),
                'show_name' => old('show_name', $settings['show_name']) ? '1' : '0',
                'show_after_reply' => old('show_after_reply', $settings['show_after_reply']) ? '1' : '0',
                'captcha_enabled' => old('captcha_enabled', $settings['captcha_enabled']) ? '1' : '0',
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'guestbook.setting');
        $this->resolveModuleOrAbort((int) $currentSite->id);

        $request->merge([
            'name' => $this->sanitizePlainText($request->input('name')),
            'notice' => $this->sanitizeRichText($request->input('notice')),
            'notice_image' => $this->sanitizePlainText($request->input('notice_image')),
        ]);

        $validator = Validator::make($request->all(), [
            'enabled' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:100'],
            'notice' => ['required', 'string', 'max:1000'],
            'notice_image' => ['nullable', 'string', 'max:255'],
            'theme' => ['required', 'string', Rule::in(array_keys($this->guestbookSettings->themeOptions()))],
            'show_name' => ['nullable', 'boolean'],
            'show_after_reply' => ['nullable', 'boolean'],
            'captcha_enabled' => ['nullable', 'boolean'],
        ], [
            'name.required' => '请填写留言板名称。',
            'notice.required' => '请填写发布须知。',
            'notice.max' => '发布须知不能超过 1000 字。',
            'notice_image.max' => '发布须知背景图地址长度不能超过 255 个字符。',
            'theme.required' => '请选择留言板模板。',
            'theme.in' => '留言板模板选项无效，请重新选择。',
        ]);

        $validator->after(function ($validator) use ($request, $currentSite): void {
            $noticeImage = trim((string) $request->input('notice_image', ''));
            if ($noticeImage !== '' && ! $this->isValidNoticeImageForSite((int) $currentSite->id, (int) $request->user()->id, $noticeImage)) {
                $validator->errors()->add('notice_image', '发布须知背景图地址格式不正确，请重新选择资源库图片。');
            }

            $noticeAttachmentIds = $this->extractAttachmentIdsFromNotice((int) $currentSite->id, (string) $request->input('notice', ''));
            $visibleAttachmentIds = $this->visibleAttachmentIds((int) $currentSite->id, (int) $request->user()->id, $noticeAttachmentIds);

            if (array_values(array_diff($noticeAttachmentIds, $visibleAttachmentIds)) !== []) {
                $validator->errors()->add('notice', '发布须知中包含不可访问的资源链接，请重新从可用资源中选择。');
            }
        });

        $validated = $validator->validate();

        $this->guestbookSettings->saveForSite((int) $currentSite->id, [
            'enabled' => $request->boolean('enabled'),
            'name' => $validated['name'],
            'notice' => $validated['notice'],
            'notice_image' => $validated['notice_image'] ?? '',
            'theme' => $validated['theme'],
            'show_name' => $request->boolean('show_name'),
            'show_after_reply' => $request->boolean('show_after_reply'),
            'captcha_enabled' => $request->boolean('captcha_enabled'),
        ], (int) $request->user()->id);
        (new GuestbookAttachmentRelationSync())->syncForSite((int) $currentSite->id);

        $this->logOperation(
            'site',
            'guestbook',
            'update_settings',
            $currentSite->id,
            $request->user()->id,
            'site',
            $currentSite->id,
            ['name' => $validated['name']],
            $request,
        );

        return redirect()
            ->route('admin.guestbook.settings')
            ->with('status', '留言板设置已更新。');
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveModuleOrAbort(int $siteId): array
    {
        $module = $this->guestbookModule->boundForSite($siteId);
        abort_unless(is_array($module), 404);

        return $module;
    }

    protected function findMessageOrAbort(int $siteId, string $messageId): object
    {
        $message = DB::table('module_guestbook_messages')
            ->where('site_id', $siteId)
            ->where('id', $messageId)
            ->first();

        abort_unless($message, 404);

        return $message;
    }

    /**
     * @param  object  $message
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function messagePayload(object $message, array $settings): array
    {
        $summary = $this->makeSummary((string) $message->content);
        $isPublic = $this->messageIsPublic($message, $settings);

        return [
            'id' => (int) $message->id,
            'display_no' => $this->displayNo((int) $message->display_no),
            'name' => (string) $message->name,
            'phone' => (string) $message->phone,
            'content' => (string) $message->content,
            'original_content' => (string) ($message->original_content ?? ''),
            'summary' => $summary,
            'status' => (string) $message->status,
            'status_label' => (string) $message->status === 'replied' ? '已办理' : '待办理',
            'is_read' => (bool) $message->is_read,
            'read_label' => (bool) $message->is_read ? '已浏览' : '未浏览',
            'is_public' => $isPublic,
            'visibility_label' => $isPublic ? '前台显示中' : '未公开',
            'reply_content' => (string) ($message->reply_content ?? ''),
            'created_at' => $message->created_at,
            'created_at_label' => $message->created_at ? date('Y-m-d H:i', strtotime((string) $message->created_at)) : '',
            'replied_at_label' => $message->replied_at ? date('Y-m-d H:i', strtotime((string) $message->replied_at)) : '',
            'show_name' => (bool) $settings['show_name'],
        ];
    }

    protected function displayNo(int $displayNo): string
    {
        return str_pad((string) $displayNo, 5, '0', STR_PAD_LEFT);
    }

    protected function makeSummary(string $content): string
    {
        return Str::limit($content, 180, '...');
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

    protected function sanitizeRichText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $wrappedHtml = '<!DOCTYPE html><html><body><div id="guestbook-notice-root">'.$trimmed.'</div></body></html>';
        $document = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (! $loaded) {
            return strip_tags($trimmed);
        }

        $root = $document->getElementById('guestbook-notice-root');
        if (! $root instanceof DOMElement) {
            return strip_tags($trimmed);
        }

        foreach (iterator_to_array($root->childNodes) as $childNode) {
            $this->sanitizeRichTextNode($childNode);
        }

        $html = '';
        foreach ($root->childNodes as $childNode) {
            $html .= $document->saveHTML($childNode);
        }

        $html = trim($html);

        return $html === '' ? null : $html;
    }

    protected function sanitizeRichTextNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = preg_replace(
                '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u',
                '',
                $node->nodeValue ?? '',
            ) ?? ($node->nodeValue ?? '');

            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tagName = Str::lower($node->tagName);
        $dangerousTags = ['script', 'style', 'iframe', 'object', 'embed'];
        if (in_array($tagName, $dangerousTags, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'ul', 'ol', 'li', 'blockquote', 'a'];
        if (! in_array($tagName, $allowedTags, true)) {
            $this->unwrapDomNode($node);

            return;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            if (! $attribute) {
                continue;
            }

            $attributeName = Str::lower($attribute->nodeName);

            if ($tagName === 'a' && in_array($attributeName, ['href', 'target', 'rel'], true)) {
                continue;
            }

            $node->removeAttribute($attribute->nodeName);
        }

        if ($tagName === 'a') {
            $href = trim((string) $node->getAttribute('href'));

            if ($href === '' || ! $this->isSafeLinkHref($href)) {
                $node->removeAttribute('href');
            }

            $target = trim((string) $node->getAttribute('target'));
            if (! in_array($target, ['_blank', '_self'], true)) {
                $node->removeAttribute('target');
            }

            if ($node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            } else {
                $node->removeAttribute('rel');
            }
        }

        foreach (iterator_to_array($node->childNodes) as $childNode) {
            $this->sanitizeRichTextNode($childNode);
        }
    }

    protected function unwrapDomNode(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    protected function isSafeLinkHref(string $href): bool
    {
        $normalized = Str::lower(trim($href));

        return str_starts_with($normalized, '/')
            || str_starts_with($normalized, '#')
            || str_starts_with($normalized, 'http://')
            || str_starts_with($normalized, 'https://')
            || str_starts_with($normalized, 'mailto:')
            || str_starts_with($normalized, 'tel:');
    }

    protected function isValidNoticeImageForSite(int $siteId, int $userId, string $path): bool
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return false;
        }

        return $this->canAccessVisibleAttachmentUrl($siteId, $userId, [$normalized], true);
    }

    /**
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromNotice(int $siteId, string $content): array
    {
        if ($content === '') {
            return [];
        }

        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);

        return $this->extractAttachmentIdsFromUrls($siteId, $matches[1] ?? []);
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, int>
     */
    protected function extractAttachmentIdsFromUrls(int $siteId, array $urls): array
    {
        $urls = array_values(array_filter($urls, fn ($url) => trim((string) $url) !== ''));

        if ($urls === []) {
            return [];
        }

        $normalizedUrls = collect($urls)
            ->flatMap(function (string $url): array {
                $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

                if ($url === '') {
                    return [];
                }

                if (str_starts_with($url, '//')) {
                    $url = 'http:'.$url;
                }

                $candidates = [$url];

                if (str_starts_with($url, '/')) {
                    $candidates[] = url($url);
                }

                $parsedPath = parse_url($url, PHP_URL_PATH);

                if (is_string($parsedPath) && $parsedPath !== '') {
                    $candidates[] = $parsedPath;
                }

                return $candidates;
            })
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedUrls === []) {
            return [];
        }

        return DB::table('attachments')
            ->where('site_id', $siteId)
            ->where(function ($query) use ($normalizedUrls): void {
                $query->whereIn('url', $normalizedUrls)
                    ->orWhereIn(DB::raw("CONCAT('/', path)"), $normalizedUrls);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function guestbookPreviewUrl(Request $request, object $site): string
    {
        $host = mb_strtolower(trim((string) $request->getHost()));
        if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return route('site.guestbook.index', ['site' => $site->site_key]);
        }

        $domain = trim((string) DB::table('site_domains')
            ->where('site_id', $site->id)
            ->where('status', 1)
            ->orderBy('id')
            ->value('domain'));

        if ($domain !== '') {
            return $request->getScheme().'://'.$domain.'/guestbook';
        }

        return route('site.guestbook.index', ['site' => $site->site_key]);
    }

    protected function messageIsPublic(object $message, array $settings): bool
    {
        return ! $settings['show_after_reply'] || (string) $message->status === 'replied';
    }
}
