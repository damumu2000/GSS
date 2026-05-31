<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_payroll_records')) {
            return;
        }

        $duplicate = DB::table('module_payroll_records')
            ->select('site_id', 'batch_id', 'sheet_type', 'employee_name', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('site_id', 'batch_id', 'sheet_type', 'employee_name')
            ->having('aggregate', '>', 1)
            ->first();

        if ($duplicate) {
            throw new \RuntimeException(sprintf(
                'Duplicate payroll records exist before adding unique index: site_id=%s, batch_id=%s, sheet_type=%s, employee_name=%s.',
                $duplicate->site_id,
                $duplicate->batch_id,
                $duplicate->sheet_type,
                $duplicate->employee_name
            ));
        }

        Schema::table('module_payroll_records', function (Blueprint $table): void {
            $table->unique(
                ['site_id', 'batch_id', 'sheet_type', 'employee_name'],
                'module_payroll_records_batch_employee_unique'
            );

            $table->index(
                ['site_id', 'employee_name', 'batch_id', 'sheet_type'],
                'module_payroll_records_employee_batch_idx'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_payroll_records')) {
            return;
        }

        Schema::table('module_payroll_records', function (Blueprint $table): void {
            $table->dropUnique('module_payroll_records_batch_employee_unique');
            $table->dropIndex('module_payroll_records_employee_batch_idx');
        });
    }
};
