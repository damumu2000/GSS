<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Support\Site as SitePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Display the current site's settings.
     */
    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'setting.manage');

        $settings = DB::table('site_settings')
            ->where('site_id', $currentSite->id)
            ->pluck('setting_value', 'setting_key');

        $domains = DB::table('site_domains')
            ->where('site_id', $currentSite->id)
            ->orderByDesc('is_primary')
            ->orderBy('domain')
            ->get(['domain', 'is_primary']);

        return view('admin.site.settings.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'settings' => $settings,
            'domains' => $domains,
        ]);
    }

    /**
     * Update the current site's settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'setting.manage');

        $request->merge($this->sanitizeInput($request));

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\+\s()#]{6,50}$/'],
            'contact_email' => ['nullable', 'email:filter', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:255'],
            'favicon' => ['nullable', 'string', 'max:255'],
            'filing_number' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\x{4E00}-\x{9FA5}\-\(\)（）〔〕\[\]【】\/\s]+$/u'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'article_requires_review' => ['nullable', 'boolean'],
            'article_share_enabled' => ['nullable', 'boolean'],
            'attachment_share_enabled' => ['nullable', 'boolean'],
        ], [
            'name.required' => '请填写站点名称。',
            'contact_phone.regex' => '联系电话格式不正确，请输入有效的电话或手机号。',
            'contact_email.email' => '联系邮箱格式不正确，请重新填写。',
            'filing_number.regex' => '备案号格式不正确，请仅使用中文、字母、数字、空格及常见连接符。',
        ]);

        $validator->after(function ($validator) use ($request): void {
            foreach (['logo', 'favicon'] as $imageField) {
                $value = trim((string) $request->input($imageField, ''));
                if ($value !== '' && ! $this->isValidAssetPath($value)) {
                    $validator->errors()->add($imageField, '图片地址格式不正确，请上传后重试。');
                }
            }
        });

        $validated = $validator->validate();

        DB::table('sites')
            ->where('id', $currentSite->id)
            ->update([
                'name' => $validated['name'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'address' => $validated['address'] ?? null,
                'logo' => $validated['logo'] ?? null,
                'favicon' => $validated['favicon'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'updated_at' => now(),
            ]);

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $currentSite->id, 'setting_key' => 'site.filing_number'],
            [
                'setting_value' => $validated['filing_number'] ?? '',
                'autoload' => 1,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $currentSite->id, 'setting_key' => 'content.article_requires_review'],
            [
                'setting_value' => $request->boolean('article_requires_review') ? '1' : '0',
                'autoload' => 1,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $currentSite->id, 'setting_key' => 'content.article_share_enabled'],
            [
                'setting_value' => $request->boolean('article_share_enabled') ? '1' : '0',
                'autoload' => 1,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('site_settings')->updateOrInsert(
            ['site_id' => $currentSite->id, 'setting_key' => 'attachment.share_enabled'],
            [
                'setting_value' => $request->boolean('attachment_share_enabled') ? '1' : '0',
                'autoload' => 1,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->logOperation(
            'site',
            'setting',
            'update',
            $currentSite->id,
            $request->user()->id,
            'site',
            $currentSite->id,
            ['name' => $validated['name']],
            $request,
        );

        return redirect()
            ->route('admin.settings.index')
            ->with('status', '站点设置已更新。');
    }

    public function mediaUpload(Request $request): JsonResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, $currentSite->id, 'setting.manage');

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,ico'],
            'slot' => ['required', 'string', 'in:logo,favicon'],
        ], [], [
            'file' => '图片文件',
            'slot' => '图片位置',
        ]);

        $file = $validated['file'];
        $slot = (string) $validated['slot'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $directory = SitePath::brandMediaRelative($currentSite->site_key);
        $filename = $slot.'.'.$extension;

        $this->deleteSlotVariants($directory, $slot);

        $path = $file->storeAs($directory, $filename, 'site');
        $url = SitePath::urlForStoredPath($path);

        return response()->json([
            'url' => $url,
        ]);
    }

    /**
     * Sanitize string input before validation/persistence.
     *
     * @return array<string, mixed>
     */
    protected function sanitizeInput(Request $request): array
    {
        $fields = [
            'name',
            'contact_phone',
            'contact_email',
            'address',
            'logo',
            'favicon',
            'filing_number',
            'seo_title',
            'seo_keywords',
            'seo_description',
        ];

        $sanitized = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            $sanitized[$field] = $this->sanitizePlainText($value);
        }

        return $sanitized;
    }

    protected function sanitizePlainText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $value) ?? $value;
        $cleaned = preg_replace('/[ \t]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    protected function deleteSlotVariants(string $directory, string $slot): void
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'] as $extension) {
            $candidate = $directory.'/'.$slot.'.'.$extension;
            if (Storage::disk('site')->exists($candidate)) {
                Storage::disk('site')->delete($candidate);
            }
        }
    }

    protected function isValidAssetPath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://');
    }
}
