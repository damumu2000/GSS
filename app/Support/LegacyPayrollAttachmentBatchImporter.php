<?php

namespace App\Support;

use App\Modules\Payroll\Support\PayrollImportService;
use App\Modules\Payroll\Support\PayrollModule;
use App\Modules\Payroll\Support\PayrollSettings;
use App\Support\Modules\ModuleManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class LegacyPayrollAttachmentBatchImporter
{
    /**
     * @var array<int, string>
     */
    protected array $skipPerformanceMonths = ['2024-10', '2025-02'];

    public function __construct(
        protected PayrollImportService $payrollImportService,
        protected PayrollModule $payrollModule,
        protected PayrollSettings $payrollSettings,
        protected ModuleManager $moduleManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $siteKey, string $rootPath, bool $execute = false): array
    {
        $site = DB::table('sites')
            ->where('site_key', trim($siteKey))
            ->first(['id', 'site_key', 'name']);

        if (! $site) {
            throw new RuntimeException('未找到目标站点：'.$siteKey);
        }

        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        if (! File::isDirectory($rootPath)) {
            throw new RuntimeException('工资附件目录不存在：'.$rootPath);
        }

        $batches = DB::table('module_payroll_batches')
            ->where('site_id', (int) $site->id)
            ->orderBy('month_key')
            ->orderBy('id')
            ->get(['id', 'month_key', 'salary_file_name', 'performance_file_name', 'status']);

        $fileIndex = $this->buildFileIndex($rootPath, $batches);

        $summary = [
            'site' => [
                'id' => (int) $site->id,
                'site_key' => (string) $site->site_key,
                'name' => (string) $site->name,
            ],
            'root' => $rootPath,
            'dry_run' => ! $execute,
            'counts' => [
                'batches' => $batches->count(),
                'salary_files_found' => 0,
                'performance_files_found' => 0,
                'salary_files_missing' => 0,
                'performance_files_missing' => 0,
            ],
            'imported' => [
                'salary_batches_imported' => 0,
                'performance_batches_imported' => 0,
                'salary_records_imported' => 0,
                'performance_records_imported' => 0,
            ],
            'warnings' => [],
            'errors' => [],
            'details' => [],
        ];

        $this->ensurePayrollModuleReady((int) $site->id);

        foreach ($batches as $batch) {
            $batchDetail = [
                'batch_id' => (int) $batch->id,
                'month_key' => (string) $batch->month_key,
                'salary' => $this->buildFileResolution($fileIndex, (string) ($batch->salary_file_name ?? '')),
                'performance' => $this->buildFileResolution($fileIndex, (string) ($batch->performance_file_name ?? '')),
            ];

            foreach (['salary', 'performance'] as $sheetType) {
                $resolution = $batchDetail[$sheetType];

                if (($resolution['status'] ?? '') === 'found') {
                    $summary['counts'][$sheetType.'_files_found']++;
                } elseif (($resolution['status'] ?? '') !== 'empty') {
                    $summary['counts'][$sheetType.'_files_missing']++;
                }
            }

            $summary['details'][] = $batchDetail;
        }

        if (! $execute) {
            foreach ($summary['details'] as $detail) {
                foreach (['salary' => '工资表', 'performance' => '绩效表'] as $sheetType => $label) {
                    if (($detail[$sheetType]['status'] ?? '') === 'missing') {
                        $summary['warnings'][] = $detail['month_key'].' '.$label.'未找到：'.($detail[$sheetType]['file_name'] ?? '');
                    }
                    if (($detail[$sheetType]['status'] ?? '') === 'duplicate') {
                        $summary['warnings'][] = $detail['month_key'].' '.$label.'存在重名文件：'.implode('，', $detail[$sheetType]['paths'] ?? []);
                    }
                }
            }

            return $summary;
        }

        foreach ($summary['details'] as &$detail) {
            $batchId = (int) $detail['batch_id'];

            foreach (['salary', 'performance'] as $sheetType) {
                $resolution = $detail[$sheetType];
                $status = (string) ($resolution['status'] ?? '');

                if ($sheetType === 'performance' && $this->shouldSkipPerformanceMonth((string) $detail['month_key'])) {
                    DB::table('module_payroll_records')
                        ->where('site_id', (int) $site->id)
                        ->where('batch_id', $batchId)
                        ->where('sheet_type', 'performance')
                        ->delete();

                    $detail[$sheetType]['imported'] = false;
                    $detail[$sheetType]['skipped'] = true;
                    $detail[$sheetType]['reason'] = '按导入规则跳过该月份绩效表，并清空现有绩效明细。';
                    $summary['warnings'][] = $detail['month_key'].' 绩效表按规则跳过，并清空现有绩效明细。';

                    continue;
                }

                if ($status !== 'found') {
                    if ($status === 'missing') {
                        $summary['errors'][] = $detail['month_key'].' '.($sheetType === 'salary' ? '工资表' : '绩效表').'未找到：'.($resolution['file_name'] ?? '');
                    } elseif ($status === 'duplicate') {
                        $summary['errors'][] = $detail['month_key'].' '.($sheetType === 'salary' ? '工资表' : '绩效表').'存在重名文件：'.implode('，', $resolution['paths'] ?? []);
                    }

                    continue;
                }

                try {
                    $result = $this->importSingleSheet((int) $site->id, $batchId, (string) $resolution['path'], $sheetType);

                    DB::table('module_payroll_batches')
                        ->where('id', $batchId)
                        ->update([
                            'status' => 'imported',
                            'imported_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $detail[$sheetType]['imported'] = true;
                    $detail[$sheetType]['matched'] = (int) ($result['matched'] ?? 0);
                    $detail[$sheetType]['sheets'] = $result['sheets'] ?? [];

                    $summary['imported'][$sheetType.'_batches_imported']++;
                    $summary['imported'][$sheetType.'_records_imported'] += (int) ($result['matched'] ?? 0);
                } catch (InvalidArgumentException $exception) {
                    $detail[$sheetType]['imported'] = false;
                    $detail[$sheetType]['error'] = $exception->getMessage();
                    $summary['errors'][] = $detail['month_key'].' '.($sheetType === 'salary' ? '工资表' : '绩效表').'解析失败：'.$exception->getMessage();
                } catch (\Throwable $exception) {
                    $detail[$sheetType]['imported'] = false;
                    $detail[$sheetType]['error'] = $exception->getMessage();
                    $summary['errors'][] = $detail['month_key'].' '.($sheetType === 'salary' ? '工资表' : '绩效表').'导入异常：'.$exception->getMessage();
                } finally {
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }
        }
        unset($detail);

        return $summary;
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

        $currentSettings = $this->payrollSettings->forSite($siteId);
        $systemUserId = (int) (DB::table('users')->orderBy('id')->value('id') ?? 1);

        $this->payrollSettings->saveForSite($siteId, [
            'enabled' => true,
            'registration_enabled' => (bool) ($currentSettings['registration_enabled'] ?? false),
            'wechat_app_id' => $currentSettings['wechat_app_id'] ?? '',
            'wechat_app_secret' => $currentSettings['wechat_app_secret'] ?? '',
            'registration_disabled_message' => $currentSettings['registration_disabled_message'] ?? '当前已禁止自动注册，请联系管理员。',
        ], $systemUserId);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function buildFileIndex(string $rootPath, Collection $batches): array
    {
        $index = [];
        $needed = $batches
            ->flatMap(fn ($batch) => [
                mb_strtolower(trim((string) ($batch->salary_file_name ?? ''))),
                mb_strtolower(trim((string) ($batch->performance_file_name ?? ''))),
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($needed === []) {
            return $index;
        }

        $neededLookup = array_fill_keys($needed, true);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $basename = mb_strtolower($file->getFilename());
            if (! isset($neededLookup[$basename])) {
                continue;
            }

            $index[$basename] ??= [];
            $index[$basename][] = $file->getPathname();
        }

        return $index;
    }

    /**
     * @param  array<string, array<int, string>>  $fileIndex
     * @return array<string, mixed>
     */
    protected function buildFileResolution(array $fileIndex, string $fileName): array
    {
        $fileName = trim($fileName);

        if ($fileName === '') {
            return [
                'status' => 'empty',
                'file_name' => '',
            ];
        }

        $matches = $fileIndex[mb_strtolower($fileName)] ?? [];

        if ($matches === []) {
            return [
                'status' => 'missing',
                'file_name' => $fileName,
            ];
        }

        if (count($matches) > 1) {
            return [
                'status' => 'duplicate',
                'file_name' => $fileName,
                'paths' => $matches,
            ];
        }

        return [
            'status' => 'found',
            'file_name' => $fileName,
            'path' => $matches[0],
        ];
    }

    /**
     * @return array{matched: int, sheets: array<int, array<string, mixed>>, imported_at: string}
     */
    protected function importSingleSheet(int $siteId, int $batchId, string $path, string $sheetType): array
    {
        $uploadedFile = new UploadedFile(
            $path,
            basename($path),
            File::mimeType($path) ?: 'application/octet-stream',
            null,
            true
        );

        return $this->payrollImportService->import($siteId, $batchId, $uploadedFile, $sheetType, true);
    }

    protected function shouldSkipPerformanceMonth(string $monthKey): bool
    {
        return in_array($monthKey, $this->skipPerformanceMonths, true);
    }
}
