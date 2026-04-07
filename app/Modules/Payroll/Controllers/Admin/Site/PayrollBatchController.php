<?php

namespace App\Modules\Payroll\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Support\PayrollImportService;
use App\Modules\Payroll\Support\PayrollModule;
use App\Modules\Payroll\Support\PayrollSettings;
use InvalidArgumentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollBatchController extends Controller
{
    public function __construct(
        protected PayrollModule $payrollModule,
        protected PayrollSettings $payrollSettings,
        protected PayrollImportService $payrollImportService
    ) {
    }

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.view');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $selectedYear = trim((string) $request->query('year', ''));
        $selectedMonth = trim((string) $request->query('month', ''));

        $yearOptions = DB::table('module_payroll_batches')
            ->where('site_id', $currentSite->id)
            ->orderByDesc('month_key')
            ->pluck('month_key')
            ->map(fn ($monthKey) => substr((string) $monthKey, 0, 4))
            ->filter(fn ($year) => preg_match('/^\d{4}$/', (string) $year) === 1)
            ->unique()
            ->values()
            ->all();

        $currentYear = now()->format('Y');
        if (! in_array($currentYear, $yearOptions, true)) {
            array_unshift($yearOptions, $currentYear);
        }

        $batches = DB::table('module_payroll_batches')
            ->where('site_id', $currentSite->id)
            ->when($selectedYear !== '' && preg_match('/^\d{4}$/', $selectedYear) === 1, fn ($query) => $query->where('month_key', 'like', $selectedYear.'-%'))
            ->when($selectedMonth !== '' && preg_match('/^(0?[1-9]|1[0-2])$/', $selectedMonth) === 1, function ($query) use ($selectedMonth, $selectedYear): void {
                $month = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
                if ($selectedYear !== '' && preg_match('/^\d{4}$/', $selectedYear) === 1) {
                    $query->where('month_key', $selectedYear.'-'.$month);

                    return;
                }

                $query->where('month_key', 'like', '%-'.$month);
            })
            ->orderByDesc('month_key')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('payroll::admin.batches.index', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'settings' => $this->payrollSettings->forSite((int) $currentSite->id),
            'yearOptions' => $yearOptions,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'batches' => $batches,
            'importSummary' => session('payroll_import_summary'),
        ]);
    }

    public function create(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $existingBatchMap = DB::table('module_payroll_batches')
            ->where('site_id', $currentSite->id)
            ->orderByDesc('month_key')
            ->pluck('id', 'month_key')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingBatchLinks = [];
        foreach ($existingBatchMap as $monthKey => $batchId) {
            $existingBatchLinks[$monthKey] = route('admin.payroll.batches.edit', $batchId);
        }

        return view('payroll::admin.batches.create', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'existingBatchMap' => $existingBatchMap,
            'existingBatchLinks' => $existingBatchLinks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $this->resolveModuleOrAbort((int) $currentSite->id);

        $validated = Validator::make($request->all(), [
            'month_key' => ['required', 'date_format:Y-m'],
        ], [
            'month_key.required' => '请选择工资月份。',
            'month_key.date_format' => '工资月份格式不正确。',
        ])->validate();

        $existingBatchId = DB::table('module_payroll_batches')
            ->where('site_id', $currentSite->id)
            ->where('month_key', $validated['month_key'])
            ->value('id');

        if ($existingBatchId) {
            return redirect()
                ->route('admin.payroll.batches.edit', (int) $existingBatchId)
                ->with('status', '该月份批次已存在，已为你打开对应编辑页，可直接重新上传工资表或绩效表。');
        }

        $batchId = (int) DB::table('module_payroll_batches')->insertGetId([
            'site_id' => $currentSite->id,
            'month_key' => $validated['month_key'],
            'status' => 'draft',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.payroll.batches.edit', $batchId)
            ->with('status', '工资批次已创建，下一步可上传工资表和绩效表。');
    }

    public function edit(Request $request, string $batchId): View
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $module = $this->resolveModuleOrAbort((int) $currentSite->id);
        $batch = $this->findBatchOrAbort((int) $currentSite->id, $batchId);
        $recordStats = $this->recordStats((int) $batch->id);
        $importSummary = session('payroll_import_summary');

        if (! is_array($importSummary) || $importSummary === []) {
            $importSummary = $this->persistedImportSummary($batch, $recordStats);
        }

        return view('payroll::admin.batches.edit', [
            'sites' => $this->adminSites(),
            'currentSite' => $currentSite,
            'module' => $module,
            'batch' => $batch,
            'importSummary' => $importSummary,
            'recordStats' => $recordStats,
            'sheetPreview' => [
                'salary' => $this->recordsPreview((int) $batch->id, 'salary'),
                'performance' => $this->recordsPreview((int) $batch->id, 'performance'),
            ],
        ]);
    }

    public function update(Request $request, string $batchId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $batch = $this->findBatchOrAbort((int) $currentSite->id, $batchId);

        Validator::make($request->all(), [
            'salary_file' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'performance_file' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ], [
            'salary_file.mimes' => '工资表仅支持 xls 或 xlsx 文件。',
            'salary_file.max' => '工资表不能超过 10MB。',
            'performance_file.mimes' => '绩效表仅支持 xls 或 xlsx 文件。',
            'performance_file.max' => '绩效表不能超过 10MB。',
        ])->validate();

        $importSummary = [];

        $failingField = null;

        try {
            DB::transaction(function () use ($request, $currentSite, $batch, &$importSummary, &$failingField): void {
                if ($request->hasFile('salary_file')) {
                    $failingField = 'salary_file';
                    $result = $this->payrollImportService->import((int) $currentSite->id, (int) $batch->id, $request->file('salary_file'), 'salary');
                    $importSummary['salary'] = $result;

                    DB::table('module_payroll_batches')
                        ->where('id', $batch->id)
                        ->update([
                            'salary_file_name' => $request->file('salary_file')->getClientOriginalName(),
                            'status' => 'imported',
                            'imported_at' => now(),
                            'imported_by' => (int) $request->user()->id,
                            'updated_at' => now(),
                        ]);
                }

                if ($request->hasFile('performance_file')) {
                    $failingField = 'performance_file';
                    $result = $this->payrollImportService->import((int) $currentSite->id, (int) $batch->id, $request->file('performance_file'), 'performance');
                    $importSummary['performance'] = $result;

                    DB::table('module_payroll_batches')
                        ->where('id', $batch->id)
                        ->update([
                            'performance_file_name' => $request->file('performance_file')->getClientOriginalName(),
                            'status' => 'imported',
                            'imported_at' => now(),
                            'imported_by' => (int) $request->user()->id,
                            'updated_at' => now(),
                        ]);
                }
            });
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.payroll.batches.edit', $batch->id)
                ->withInput()
                ->withErrors([($failingField ?: 'salary_file') => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.payroll.batches.edit', $batch->id)
            ->with('status', $importSummary === [] ? '未选择新表格，本次未重新解析。' : '工资表数据已重新解析。')
            ->with('payroll_import_summary', $importSummary);
    }

    public function export(Request $request, string $batchId, string $type): StreamedResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $batch = $this->findBatchOrAbort((int) $currentSite->id, $batchId);

        abort_unless(in_array($type, ['salary', 'performance'], true), 404);

        $rows = DB::table('module_payroll_records')
            ->where('site_id', $currentSite->id)
            ->where('batch_id', $batch->id)
            ->where('sheet_type', $type)
            ->orderBy('employee_name')
            ->get(['employee_name', 'items_json']);

        abort_if($rows->isEmpty(), 404);

        $columns = [];
        $normalizedRows = [];

        foreach ($rows as $row) {
            $items = array_values(array_filter(json_decode((string) $row->items_json, true) ?: [], fn ($item) => is_array($item)));
            $mapped = [];

            foreach ($items as $item) {
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '' || in_array($label, ['姓名', '员工姓名', '老师姓名'], true)) {
                    continue;
                }

                if (! in_array($label, $columns, true)) {
                    $columns[] = $label;
                }

                $mapped[$label] = (string) ($item['value'] ?? '');
            }

            $normalizedRows[] = [
                'employee_name' => (string) $row->employee_name,
                'items' => $mapped,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($type === 'salary' ? '工资数据' : '绩效数据');

        $sheet->setCellValue('A1', '姓名');
        foreach ($columns as $index => $label) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 2);
            $sheet->setCellValue($columnLetter.'1', $label);
        }

        foreach ($normalizedRows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $sheet->setCellValue('A'.$excelRow, $row['employee_name']);

            foreach ($columns as $columnIndex => $label) {
                $columnLetter = Coordinate::stringFromColumnIndex($columnIndex + 2);
                $sheet->setCellValue($columnLetter.$excelRow, $row['items'][$label] ?? '');
            }
        }

        foreach (range(1, count($columns) + 1) as $columnNumber) {
            $sheet->getColumnDimensionByColumn($columnNumber)->setAutoSize(true);
        }

        $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $batch->month_key)->format('Y年n月');
        $typeLabel = $type === 'salary' ? '工资数据' : '绩效数据';
        $fileName = $monthLabel.$typeLabel.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(Request $request, string $batchId): RedirectResponse
    {
        $currentSite = $this->currentSite($request);
        $this->authorizeSite($request, (int) $currentSite->id, 'payroll.manage');
        $batch = $this->findBatchOrAbort((int) $currentSite->id, $batchId);

        DB::transaction(function () use ($batch): void {
            DB::table('module_payroll_records')
                ->where('batch_id', $batch->id)
                ->delete();

            DB::table('module_payroll_batches')
                ->where('id', $batch->id)
                ->delete();
        });

        return redirect()
            ->route('admin.payroll.batches.index')
            ->with('status', \Illuminate\Support\Carbon::createFromFormat('Y-m', (string) $batch->month_key)->format('Y年n月').'工资批次及对应解析数据已删除。');
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveModuleOrAbort(int $siteId): array
    {
        $module = $this->payrollModule->boundForSite($siteId);
        abort_unless(is_array($module), 404);

        return $module;
    }

    protected function findBatchOrAbort(int $siteId, string $batchId): object
    {
        $batch = DB::table('module_payroll_batches')
            ->where('site_id', $siteId)
            ->where('id', $batchId)
            ->first();

        abort_unless($batch, 404);

        return $batch;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recordsPreview(int $batchId, string $sheetType): array
    {
        return DB::table('module_payroll_records')
            ->where('batch_id', $batchId)
            ->where('sheet_type', $sheetType)
            ->orderBy('employee_name')
            ->limit(3)
            ->get()
            ->map(function ($record): array {
                return [
                    'employee_name' => (string) $record->employee_name,
                    'items' => array_values(array_filter(json_decode((string) $record->items_json, true) ?: [], fn ($item) => is_array($item))),
                ];
            })
            ->all();
    }

    /**
     * @return array{salary:int,performance:int}
     */
    protected function recordStats(int $batchId): array
    {
        $rows = DB::table('module_payroll_records')
            ->where('batch_id', $batchId)
            ->select('sheet_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('sheet_type')
            ->get()
            ->keyBy('sheet_type');

        return [
            'salary' => (int) ($rows->get('salary')->aggregate ?? 0),
            'performance' => (int) ($rows->get('performance')->aggregate ?? 0),
        ];
    }

    /**
     * @param  object  $batch
     * @param  array{salary:int,performance:int}  $recordStats
     * @return array<string, array<string, mixed>>
     */
    protected function persistedImportSummary(object $batch, array $recordStats): array
    {
        $summary = [];

        foreach (['salary' => 'salary_file_name', 'performance' => 'performance_file_name'] as $type => $fileColumn) {
            $matched = (int) ($recordStats[$type] ?? 0);
            if ($matched <= 0) {
                continue;
            }

            $summary[$type] = [
                'matched' => $matched,
                'imported_at' => ! empty($batch->imported_at) ? (string) $batch->imported_at : null,
                'sheets' => [[
                    'name' => (string) ($batch->{$fileColumn} ?: ($type === 'salary' ? '工资数据' : '绩效数据')),
                    'mode' => 'persisted',
                    'matched' => $matched,
                ]],
            ];
        }

        return $summary;
    }
}
