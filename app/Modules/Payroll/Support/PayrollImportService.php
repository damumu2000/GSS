<?php

namespace App\Modules\Payroll\Support;

use InvalidArgumentException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PayrollImportService
{
    public function __construct(
        protected PayrollSpreadsheetParser $parser
    ) {
    }

    /**
     * @return array{matched: int, sheets: array<int, array<string, mixed>>, imported_at: string}
     */
    public function import(int $siteId, int $batchId, UploadedFile $file, string $sheetType): array
    {
        $payload = $this->parser->parse($file->getRealPath(), $sheetType);

        if (! empty($payload['duplicates'])) {
            $duplicateName = (string) ($payload['duplicates'][0] ?? '');

            throw new InvalidArgumentException('检测到姓名“'.$duplicateName.'”重复，请检查后重新提交。');
        }

        if ($duplicateEmployeeName = $this->duplicateEmployeeNameInSite($siteId, $payload['records'])) {
            throw new InvalidArgumentException('检测到姓名“'.$duplicateEmployeeName.'”重复，请检查后重新提交。');
        }

        if (count($payload['records']) === 0) {
            $sheetNames = collect($payload['sheets'] ?? [])
                ->map(fn ($sheet) => trim((string) ($sheet['name'] ?? $sheet['title'] ?? '')))
                ->filter()
                ->values()
                ->all();

            $scopeLabel = $sheetType === 'salary' ? '工资表' : '绩效表';
            $sheetHint = $sheetNames === [] ? '' : '（工作表：'.implode('、', $sheetNames).'）';

            throw new InvalidArgumentException($scopeLabel.'未识别到可导入的姓名与项目结构'.$sheetHint.'，请检查表格格式后重新上传。');
        }

        DB::table('module_payroll_records')
            ->where('site_id', $siteId)
            ->where('batch_id', $batchId)
            ->where('sheet_type', $sheetType)
            ->delete();

        $rows = [];

        foreach ($payload['records'] as $record) {
            $rows[] = [
                'site_id' => $siteId,
                'batch_id' => $batchId,
                'employee_name' => $record['employee_name'],
                'sheet_type' => $sheetType,
                'items_json' => json_encode($record['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'row_hash' => hash('sha256', $record['employee_name'].'|'.$sheetType.'|'.json_encode($record['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('module_payroll_records')->insert($rows);
        }

        return [
            'matched' => count($payload['records']),
            'sheets' => $payload['sheets'],
            'imported_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function duplicateEmployeeNameInSite(int $siteId, array $records): ?string
    {
        $recordNames = collect($records)
            ->pluck('employee_name')
            ->map(fn ($name) => $this->sanitizeName((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recordNames === []) {
            return null;
        }

        $duplicates = DB::table('module_payroll_employees')
            ->where('site_id', $siteId)
            ->get(['name'])
            ->map(fn ($employee) => $this->sanitizeName((string) $employee->name))
            ->filter()
            ->groupBy(fn ($name) => $name)
            ->filter(fn ($rows) => $rows->count() > 1)
            ->keys();

        foreach ($recordNames as $recordName) {
            if ($duplicates->contains($recordName)) {
                return $recordName;
            }
        }

        return null;
    }

    protected function sanitizeName(string $value): string
    {
        return trim((string) \Illuminate\Support\Str::of($value)
            ->replace("\u{3000}", ' ')
            ->replace("\xc2\xa0", ' ')
            ->squish());
    }
}
