<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_payroll_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('wechat_openid', 100)->nullable();
            $table->string('wechat_unionid', 100)->nullable();
            $table->string('wechat_nickname', 100)->nullable();
            $table->string('wechat_avatar')->nullable();
            $table->string('name', 50);
            $table->string('mobile', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('password_enabled')->default(false);
            $table->string('password_hash')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'name']);
            $table->unique(['site_id', 'wechat_openid'], 'module_payroll_employees_site_openid_unique');
        });

        Schema::create('module_payroll_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('month_key', 7);
            $table->string('status', 20)->default('draft');
            $table->string('salary_file_name')->nullable();
            $table->string('performance_file_name')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'month_key'], 'module_payroll_batches_site_month_unique');
            $table->index(['site_id', 'status']);
        });

        Schema::create('module_payroll_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('module_payroll_batches')->cascadeOnDelete();
            $table->string('employee_name', 50);
            $table->string('sheet_type', 20);
            $table->json('items_json');
            $table->string('row_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'batch_id', 'sheet_type']);
            $table->index(['site_id', 'employee_name']);
        });

        Schema::create('module_payroll_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('module_payroll_employees')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['site_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_payroll_login_logs');
        Schema::dropIfExists('module_payroll_records');
        Schema::dropIfExists('module_payroll_batches');
        Schema::dropIfExists('module_payroll_employees');
    }
};
