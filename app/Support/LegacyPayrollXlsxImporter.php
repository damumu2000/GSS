<?php

namespace App\Support;

use App\Modules\Payroll\Support\PayrollSettings;
use App\Support\Modules\ModuleManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use RuntimeException;

class LegacyPayrollXlsxImporter
{
    public function __construct(
        protected ModuleManager $moduleManager,
        protected PayrollSettings $payrollSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $siteKey, string $employeesPath, string $batchesPath, bool $execute = false): array
    {
        $site = DB::table('sites')
            ->where('site_key', trim($siteKey))
            ->first(['id', 'site_key', 'name']);

        if (! $site) {
            throw new RuntimeException('未找到目标站点：'.$siteKey);
        }

        $employeeRows = $this->loadWorkbookRows($employeesPath);
        $batchRows = $this->loadWorkbookRows($batchesPath);

        $summary = [
            'site' => [
                'id' => (int) $site->id,
                'site_key' => (string) $site->site_key,
                'name' => (string) $site->name,
            ],
            'dry_run' => ! $execute,
            'source' => [
                'employees' => $employeesPath,
                'batches' => $batchesPath,
            ],
            'counts' => [
                'employees' => count($employeeRows),
                'batches' => count($batchRows),
            ],
            'imported' => [
                'employees_created' => 0,
                'employees_updated' => 0,
                'batches_created' => 0,
                'batches_updated' => 0,
            ],
            'warnings' => [
                '本次导入仅写入工资员工与月份批次元数据，不会自动生成工资/绩效明细记录。',
                '如需导入工资条详情，请在后续补充原始工资表/绩效表文件后单独处理。',
            ],
        ];

        if (! $execute) {
            return $summary;
        }

        return DB::transaction(function () use ($site, $employeeRows, $batchRows, $summary): array {
            $this->ensurePayrollModuleReady((int) $site->id);

            foreach ($employeeRows as $row) {
                $result = $this->importEmployeeRow((int) $site->id, $row);

                if ($result === null) {
                    continue;
                }

                $summary['imported'][$result ? 'employees_created' : 'employees_updated']++;
            }

            foreach ($batchRows as $row) {
                $result = $this->importBatchRow((int) $site->id, $row);

                if ($result === null) {
                    continue;
                }

                $summary['imported'][$result ? 'batches_created' : 'batches_updated']++;
            }

            return $summary;
        });
    }

    protected function ensurePayrollModuleReady(int $siteId): void
    {
        $this->moduleManager->synchronize();

        DB::table('modules')
            ->where('code', 'payroll')
            ->update([
                'status' => 1,
                'updated_at' => now(),
            ]);

        $moduleId = (int) DB::table('modules')->where('code', 'payroll')->value('id');
        if ($moduleId <= 0) {
            throw new RuntimeException('工资模块未注册成功。');
        }

        DB::table('site_module_bindings')->updateOrInsert(
            ['site_id' => $siteId, 'module_id' => $moduleId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $systemUserId = (int) (DB::table('users')->orderBy('id')->value('id') ?? 1);

        $this->payrollSettings->saveForSite($siteId, [
            'enabled' => true,
            'registration_enabled' => false,
            'wechat_app_id' => '',
            'wechat_app_secret' => '',
            'registration_disabled_message' => '当前已禁止自动注册，请联系管理员。',
        ], $systemUserId);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return bool|null true created, false updated, null skipped
     */
    protected function importEmployeeRow(int $siteId, array $row): ?bool
    {
        $name = $this->stringValue($row, ['xingming', 'name', 'xm']);
        $mobile = $this->stringValue($row, ['dianhua', 'mobile', 'phone']);
        $openid = $this->stringValue($row, ['openid', 'open_id', 'openID', 'OpenID']);

        if ($name === '' && $mobile === '' && $openid === '') {
            return null;
        }

        $wechatNickname = $this->stringValue($row, ['wxname', 'wx_name', 'nickname']);
        $wechatAvatar = $this->stringValue($row, ['wxicon', 'wx_icon', 'avatar']);
        $password = $this->stringValue($row, ['chaxun', 'password', 'query_password']);
        $passwordFlag = $this->stringValue($row, ['chaxunflag', 'chaxun_flag', 'chaxunfla']);
        $passwordEnabled = $password !== '' && mb_strtolower($passwordFlag) === 'on';
        $createdAt = $this->legacyTimestamp($row['addtime'] ?? null);
        $updatedAt = $this->legacyTimestamp($row['lasttime'] ?? null) ?? $createdAt ?? now();
        $approvedAt = $createdAt ?? $updatedAt ?? now();

        $existing = null;
        if ($openid !== '') {
            $existing = DB::table('module_payroll_employees')
                ->where('site_id', $siteId)
                ->where('wechat_openid', $openid)
                ->first(['id']);
        }

        if (! $existing && $mobile !== '') {
            $existing = DB::table('module_payroll_employees')
                ->where('site_id', $siteId)
                ->where('mobile', $mobile)
                ->first(['id']);
        }

        if (! $existing && $name !== '') {
            $existing = DB::table('module_payroll_employees')
                ->where('site_id', $siteId)
                ->where('name', $name)
                ->first(['id']);
        }

        $payload = [
            'site_id' => $siteId,
            'wechat_openid' => $openid !== '' ? $openid : null,
            'wechat_unionid' => null,
            'wechat_nickname' => $wechatNickname !== '' ? $wechatNickname : null,
            'wechat_avatar' => $wechatAvatar !== '' ? $wechatAvatar : null,
            'name' => $name !== '' ? $name : ($mobile !== '' ? $mobile : '未命名员工'),
            'mobile' => $mobile !== '' ? $mobile : null,
            'status' => 'approved',
            'password_enabled' => $passwordEnabled ? 1 : 0,
            'password_hash' => $passwordEnabled ? Hash::make($password) : null,
            'approved_at' => $approvedAt,
            'approved_by' => null,
            'last_login_at' => $updatedAt,
            'last_login_ip' => null,
            'updated_at' => $updatedAt ?? now(),
        ];

        if ($existing) {
            DB::table('module_payroll_employees')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return false;
        }

        DB::table('module_payroll_employees')->insert($payload + [
            'created_at' => $createdAt ?? now(),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return bool|null true created, false updated, null skipped
     */
    protected function importBatchRow(int $siteId, array $row): ?bool
    {
        $year = (int) $this->stringValue($row, ['nian', 'year']);
        $month = (int) $this->stringValue($row, ['yue', 'month']);

        if ($year <= 0 || $month < 1 || $month > 12) {
            return null;
        }

        $monthKey = sprintf('%04d-%02d', $year, $month);
        $salaryPath = $this->stringValue($row, ['gongzi', 'salary', 'salary_path']);
        $performancePath = $this->stringValue($row, ['jixiao', 'performance', 'performance_path']);
        $importedAt = $this->legacyTimestamp($row['edit_time'] ?? null)
            ?? $this->legacyTimestamp($row['time'] ?? null)
            ?? Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'));

        $existing = DB::table('module_payroll_batches')
            ->where('site_id', $siteId)
            ->where('month_key', $monthKey)
            ->first(['id']);

        $payload = [
            'site_id' => $siteId,
            'month_key' => $monthKey,
            'status' => 'imported',
            'salary_file_name' => $salaryPath !== '' ? basename(str_replace('\\', '/', $salaryPath)) : null,
            'performance_file_name' => $performancePath !== '' ? basename(str_replace('\\', '/', $performancePath)) : null,
            'imported_at' => $importedAt,
            'imported_by' => null,
            'updated_at' => $importedAt ?? now(),
        ];

        if ($existing) {
            DB::table('module_payroll_batches')
                ->where('id', (int) $existing->id)
                ->update($payload);

            return false;
        }

        DB::table('module_payroll_batches')->insert($payload + [
            'created_at' => $importedAt ?? now(),
        ]);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadWorkbookRows(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException('文件不存在：'.$path);
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $rows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetRows = $worksheet->toArray(null, true, true, false);
            if ($sheetRows === [] || ! isset($sheetRows[0]) || ! is_array($sheetRows[0])) {
                continue;
            }

            $headers = collect($sheetRows[0])
                ->map(fn ($header) => $this->normalizeHeader((string) $header))
                ->values()
                ->all();

            foreach (array_slice($sheetRows, 1) as $sheetRow) {
                if (! is_array($sheetRow)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $assoc[$header] = $sheetRow[$index] ?? null;
                }

                if ($assoc === [] || $this->rowIsEffectivelyEmpty($assoc)) {
                    continue;
                }

                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    protected function stringValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeHeader($key);
            if (! array_key_exists($normalizedKey, $row)) {
                continue;
            }

            $value = trim((string) $row[$normalizedKey]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\n", "\r", ' '], '', $value);

        return mb_strtolower($value);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIsEffectivelyEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function legacyTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(SpreadsheetDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        $text = ltrim($text, "'");

        $formats = [
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i',
            'n/j/Y H:i:s',
            'n/j/Y G:i:s',
            'n/j H:i:s',
            'n/j G:i:s',
            'm/d H:i:s',
            'm/d G:i:s',
            'Y-m-d',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $text, config('app.timezone'));
                if (str_contains($format, 'n/j ') || str_contains($format, 'm/d ')) {
                    $parsed->year((int) now()->year);
                }

                return $parsed;
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($text, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
