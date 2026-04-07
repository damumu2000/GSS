<?php

namespace App\Modules\Payroll\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollSpreadsheetParser
{
    /**
     * @return array{records: array<int, array<string, mixed>>, sheets: array<int, array<string, mixed>>, duplicates: array<int, string>}
     */
    public function parse(string $path, string $sheetType): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $spreadsheet = $reader->load($path);
        $records = [];
        $sheets = [];
        $duplicates = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            if ($highestRow < 2 || ($highestColumn === 'A' && trim($this->readWorksheetCellValue($worksheet, 1, 1)) === '')) {
                continue;
            }

            $rows = $this->extractWorksheetRows($worksheet, $highestRow, $highestColumn);
            $sheetPayload = $this->parseWorksheet($rows, $worksheet->getTitle(), $sheetType);

            if ($sheetPayload['matched'] === 0) {
                $sheets[] = [
                    'name' => $worksheet->getTitle(),
                    'title' => $worksheet->getTitle(),
                    'mode' => 'skipped',
                    'matched' => 0,
                    'reason' => '未识别到可导入的姓名与项目结构',
                ];

                continue;
            }

            $sheets[] = [
                'name' => $worksheet->getTitle(),
                'title' => $worksheet->getTitle(),
                'mode' => $sheetPayload['mode'],
                'matched' => $sheetPayload['matched'],
                'reason' => null,
            ];

            foreach ($sheetPayload['duplicates'] as $duplicateName) {
                $duplicates[$duplicateName] = $duplicateName;
            }

            foreach ($sheetPayload['records'] as $name => $items) {
                if (isset($records[$name])) {
                    $duplicates[$name] = $name;
                }

                $records[$name] ??= [
                    'employee_name' => $name,
                    'sheet_type' => $sheetType,
                    'items' => [],
                ];

                foreach ($items as $item) {
                    $this->pushItem($records[$name]['items'], $item['label'], $item['value']);
                }
            }
        }

        return [
            'records' => array_values(array_map(function (array $record): array {
                $record['items'] = array_values($record['items']);

                return $record;
            }, $records)),
            'sheets' => $sheets,
            'duplicates' => array_values($duplicates),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractWorksheetRows(Worksheet $worksheet, int $highestRow, string $highestColumn): array
    {
        $rows = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $row = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $columnName = Coordinate::stringFromColumnIndex($columnIndex);
                $row[$columnName] = $this->readWorksheetCellValue($worksheet, $columnIndex, $rowIndex);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function readWorksheetCellValue(Worksheet $worksheet, int $columnIndex, int $rowIndex): mixed
    {
        $cell = $worksheet->getCell([$columnIndex, $rowIndex]);

        if ($cell->isFormula()) {
            $oldCalculatedValue = $cell->getOldCalculatedValue();
            if ($oldCalculatedValue !== null) {
                return $oldCalculatedValue;
            }

            return '';
        }

        if ($cell->getDataType() === DataType::TYPE_NULL) {
            return '';
        }

        return $cell->getValue();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{mode: string, matched: int, records: array<string, array<int, array{label: string, value: string}>>, duplicates: array<int, string>}
     */
    protected function parseWorksheet(array $rows, string $sheetTitle, string $sheetType): array
    {
        if ($this->looksLikeHorizontalSheet($rows)) {
            return [
                'mode' => 'horizontal',
                ...$this->parseHorizontalSheet($rows),
            ];
        }

        if ($this->looksLikePairedSheet($rows)) {
            return [
                'mode' => 'paired',
                ...$this->parsePairedSheet($rows, $sheetTitle, $sheetType),
            ];
        }

        return [
            'mode' => 'unknown',
            'matched' => 0,
            'records' => [],
            'duplicates' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function looksLikeHorizontalSheet(array $rows): bool
    {
        $header = Arr::first($rows);

        if (! is_array($header)) {
            return false;
        }

        $headerValues = array_values($header);
        $nonEmptyHeaders = array_values(array_filter(array_map(fn ($value) => $this->normalizeHeader((string) $value), $headerValues)));

        return count($nonEmptyHeaders) >= 3 && $this->looksLikeNameHeader($nonEmptyHeaders[0] ?? '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function looksLikePairedSheet(array $rows): bool
    {
        $header = Arr::first($rows);

        if (! is_array($header)) {
            return false;
        }

        $values = array_values($header);
        $nameHeaderCount = collect($values)
            ->filter(fn ($value) => $this->looksLikeNameHeader((string) $value))
            ->count();

        return $nameHeaderCount >= 1 && count(array_filter($values, fn ($value) => trim((string) $value) !== '')) >= 2;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{matched: int, records: array<string, array<int, array{label: string, value: string}>>, duplicates: array<int, string>}
     */
    protected function parseHorizontalSheet(array $rows): array
    {
        $headerRow = array_shift($rows) ?? [];
        $columns = array_keys($headerRow);
        $nameColumn = $columns[0] ?? 'A';
        $headerMap = [];

        foreach ($columns as $column) {
            $label = $this->normalizeHeader((string) ($headerRow[$column] ?? ''));
            if ($column === $nameColumn || $label === '') {
                continue;
            }

            $headerMap[$column] = $label;
        }

        $records = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $name = $this->normalizeName((string) ($row[$nameColumn] ?? ''));
            if ($name === '') {
                continue;
            }

            if (isset($records[$name])) {
                $duplicates[$name] = $name;
            }

            $items = [
                ['label' => '姓名', 'value' => $name],
            ];

            foreach ($headerMap as $column => $label) {
                $value = $this->normalizeCellValue($row[$column] ?? '');
                if ($value === '') {
                    continue;
                }

                $items[] = ['label' => $label, 'value' => $value];
            }

            if (count($items) > 1) {
                $records[$name] = $items;
            }
        }

        return [
            'matched' => count($records),
            'records' => $records,
            'duplicates' => array_values($duplicates),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{matched: int, records: array<string, array<int, array{label: string, value: string}>>, duplicates: array<int, string>}
     */
    protected function parsePairedSheet(array $rows, string $sheetTitle, string $sheetType): array
    {
        $headerRow = array_shift($rows) ?? [];
        $headers = array_values($headerRow);
        $columnCount = count($headers);
        $pairs = [];

        for ($index = 0; $index < $columnCount - 1; $index++) {
            $nameHeader = $this->normalizeHeader((string) ($headers[$index] ?? ''));
            $valueHeader = $this->normalizeHeader((string) ($headers[$index + 1] ?? ''));

            if (! $this->looksLikeNameHeader($nameHeader)) {
                continue;
            }

            $pairs[] = [
                'name_index' => $index,
                'value_index' => $index + 1,
                'label' => $this->resolvePairedLabel($valueHeader, $sheetTitle, $sheetType),
            ];
        }

        $records = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $values = array_values($row);

            foreach ($pairs as $pair) {
                $name = $this->normalizeName((string) ($values[$pair['name_index']] ?? ''));
                $value = $this->normalizeCellValue($values[$pair['value_index']] ?? '');

                if ($name === '' || $value === '') {
                    continue;
                }

                if (isset($records[$name])) {
                    $duplicates[$name] = $name;
                }

                $records[$name] ??= [
                    ['label' => '姓名', 'value' => $name],
                ];

                $this->pushItem($records[$name], $pair['label'], $value);
            }
        }

        return [
            'matched' => count($records),
            'records' => $records,
            'duplicates' => array_values($duplicates),
        ];
    }

    protected function resolvePairedLabel(string $headerValue, string $sheetTitle, string $sheetType): string
    {
        $headerValue = trim($headerValue);

        if ($headerValue !== '' && ! in_array($headerValue, ['金额', '金额（元）', '金额(元)'], true)) {
            return $headerValue;
        }

        return $sheetType === 'salary' ? '工资项目' : $sheetTitle;
    }

    protected function normalizeHeader(string $value): string
    {
        $value = trim(Str::of($value)->replace("\u{3000}", ' ')->replace("\xc2\xa0", ' ')->squish()->value());

        return $value;
    }

    protected function looksLikeNameHeader(string $value): bool
    {
        return in_array(trim($value), ['姓名', '员工姓名', '老师姓名'], true);
    }

    protected function normalizeName(string $value): string
    {
        return trim(Str::of($value)->replace("\u{3000}", ' ')->replace("\xc2\xa0", ' ')->squish()->value());
    }

    protected function normalizeCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = trim((string) $value);

        return $string;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $items
     */
    protected function pushItem(array &$items, string $label, string $value): void
    {
        foreach ($items as $index => $item) {
            if ($item['label'] === $label) {
                $items[$index] = ['label' => $label, 'value' => $value];

                return;
            }
        }

        $items[] = ['label' => $label, 'value' => $value];
    }
}
