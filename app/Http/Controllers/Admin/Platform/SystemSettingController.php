<?php

namespace App\Http\Controllers\Admin\Platform;

use App\Http\Controllers\Controller;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function __construct(
        protected SystemSettings $systemSettings,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizePlatform($request, 'system.setting.manage');
        $currentSite = $this->currentSite($request);
        $activeTab = $this->normalizeTab((string) $request->query('tab', 'basic'));

        return view('admin.platform.settings.index', [
            'currentSite' => $currentSite,
            'settings' => $this->systemSettings->formDefaults(),
            'activeTab' => $activeTab,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePlatform($request, 'system.setting.manage');

        $validator = Validator::make($request->all(), [
            'system_name' => ['required', 'string', 'max:100'],
            'system_version' => ['required', 'string', 'max:50'],
            'attachment_allowed_extensions' => ['required', 'string', 'max:255'],
            'attachment_max_size_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'attachment_image_max_size_mb' => ['required', 'integer', 'min:1', 'max:512'],
            'attachment_image_max_width' => ['required', 'integer', 'min:100', 'max:20000'],
            'attachment_image_max_height' => ['required', 'integer', 'min:100', 'max:20000'],
            'attachment_image_auto_resize' => ['nullable', 'boolean'],
            'attachment_image_auto_compress' => ['nullable', 'boolean'],
            'attachment_image_quality' => ['required', 'integer', 'min:1', 'max:100'],
            'admin_enabled' => ['nullable', 'boolean'],
            'admin_disabled_message' => ['nullable', 'string', 'max:255'],
            'admin_logo_file' => ['nullable', 'file', 'image', 'max:3072'],
            'admin_favicon_file' => ['nullable', 'file', 'mimes:ico,png', 'max:1024'],
            'admin_logo_clear' => ['nullable', 'boolean'],
            'admin_favicon_clear' => ['nullable', 'boolean'],
        ], [
            'system_name.required' => '请填写系统名称。',
            'system_version.required' => '请填写系统版本号。',
            'attachment_allowed_extensions.required' => '请填写资源库允许上传的文件类型。',
            'admin_logo_file.image' => '后台 Logo 请上传图片文件。',
            'admin_favicon_file.mimes' => '后台 ICO 仅支持 ICO 或 PNG 文件。',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $normalizedExtensions = $this->normalizeExtensions((string) $request->input('attachment_allowed_extensions', ''));

            if ($normalizedExtensions === '') {
                $validator->errors()->add('attachment_allowed_extensions', '请至少保留一个合法的文件扩展名。');
            }

            if ((int) $request->input('attachment_image_max_size_mb', 0) > (int) $request->input('attachment_max_size_mb', 0)) {
                $validator->errors()->add('attachment_image_max_size_mb', '图片大小限制不能超过单文件大小限制。');
            }

            if ($request->boolean('admin_logo_clear') && $request->hasFile('admin_logo_file')) {
                $validator->errors()->add('admin_logo_file', '请不要同时上传和清除后台 Logo。');
            }

            if ($request->boolean('admin_favicon_clear') && $request->hasFile('admin_favicon_file')) {
                $validator->errors()->add('admin_favicon_file', '请不要同时上传和清除后台 ICO。');
            }
        });

        $validated = $validator->validate();

        $now = now();
        $userId = (int) $request->user()->id;
        $systemName = $this->sanitizeSingleLine((string) $validated['system_name'], 100);
        $systemVersion = $this->sanitizeSingleLine((string) $validated['system_version'], 50);
        $normalizedExtensions = $this->normalizeExtensions((string) $validated['attachment_allowed_extensions']);
        $disabledMessage = $this->sanitizeTextarea((string) ($validated['admin_disabled_message'] ?? ''), 255);

        $settings = [
            'system.name' => $systemName,
            'system.version' => $systemVersion,
            'attachment.allowed_extensions' => $normalizedExtensions,
            'attachment.max_size_mb' => (string) $validated['attachment_max_size_mb'],
            'attachment.image_max_size_mb' => (string) $validated['attachment_image_max_size_mb'],
            'attachment.image_max_width' => (string) $validated['attachment_image_max_width'],
            'attachment.image_max_height' => (string) $validated['attachment_image_max_height'],
            'attachment.image_auto_resize' => $request->boolean('attachment_image_auto_resize') ? '1' : '0',
            'attachment.image_auto_compress' => $request->boolean('attachment_image_auto_compress') ? '1' : '0',
            'attachment.image_quality' => (string) $validated['attachment_image_quality'],
            'admin.enabled' => $request->boolean('admin_enabled') ? '1' : '0',
            'admin.disabled_message' => $disabledMessage,
        ];

        if ($request->boolean('admin_logo_clear')) {
            $this->deletePublicBrandAssets('logo');
            $settings['admin.logo'] = '';
        } elseif ($request->hasFile('admin_logo_file')) {
            $settings['admin.logo'] = $this->storePublicBrandAsset($request->file('admin_logo_file'), 'logo');
        }

        if ($request->boolean('admin_favicon_clear')) {
            $this->deletePublicBrandAssets('Favicon');
            $settings['admin.favicon'] = '';
        } elseif ($request->hasFile('admin_favicon_file')) {
            $settings['admin.favicon'] = $this->storePublicBrandAsset($request->file('admin_favicon_file'), 'Favicon');
        }

        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'autoload' => 1,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->logOperation(
            'platform',
            'system_setting',
            'update',
            null,
            $userId,
            'system_setting',
            null,
            [
                'system_name' => $settings['system.name'],
                'system_version' => $settings['system.version'],
                'admin_enabled' => $settings['admin.enabled'],
            ],
            $request,
        );

        $activeTab = $this->normalizeTab((string) $request->input('current_tab', 'basic'));

        return redirect()
            ->route('admin.platform.settings.index', ['tab' => $activeTab])
            ->with('status', '系统设置已更新。');
    }

    protected function normalizeTab(string $tab): string
    {
        return in_array($tab, ['basic', 'upload', 'access'], true) ? $tab : 'basic';
    }

    protected function storePublicBrandAsset(UploadedFile $file, string $prefix): string
    {
        $extension = $this->normalizeBrandExtension($file, $prefix);
        $this->deletePublicBrandAssets($prefix);

        $filename = sprintf('%s_x.%s', $prefix, $extension);
        $file->move(public_path(), $filename);

        return '/' . $filename;
    }

    protected function deletePublicBrandAssets(string $prefix): void
    {
        $publicPath = public_path();

        foreach (glob($publicPath . DIRECTORY_SEPARATOR . $prefix . '_*.*') ?: [] as $existingFile) {
            if (is_file($existingFile)) {
                @unlink($existingFile);
            }
        }
    }

    protected function normalizeExtensions(string $value): string
    {
        return collect(preg_split('/\s*,\s*/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => preg_match('/^[a-z0-9]+$/', $item) === 1)
            ->unique()
            ->values()
            ->implode(',');
    }

    protected function sanitizeSingleLine(string $value, int $maxLength): string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $sanitized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/\s+/u', ' ', $sanitized) ?? '';
        $sanitized = trim($sanitized);

        return Str::limit($sanitized, $maxLength, '');
    }

    protected function sanitizeTextarea(string $value, int $maxLength): string
    {
        $sanitized = str_replace(["\r\n", "\r"], "\n", $value);
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace("/\n{3,}/u", "\n\n", $sanitized) ?? '';
        $sanitized = trim($sanitized);

        return Str::limit($sanitized, $maxLength, '');
    }

    protected function normalizeBrandExtension(UploadedFile $file, string $prefix): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($prefix === 'Favicon') {
            return $extension === 'png' ? 'png' : 'ico';
        }

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? $extension
            : 'png';
    }
}
