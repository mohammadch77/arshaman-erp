<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_run_id');
            $table->uuid('employee_id');

            // انحراف مستند از نسخه اول docs/schema_hr_mysql.sql (جدول ۷):
            // آن نسخه ستون شرکت نداشت و شرکت را فقط از راه payroll_run استنتاج می‌کرد.
            // طبق CLAUDE.md بند ۵.۱ («هر مدل عملیاتی بدون BelongsToCompany یک باگ
            // امنیتی است») ستون مستقیم اضافه شد تا Global Scope یک‌لایه و بدون join
            // کار کند. تصمیم A، Session 6.
            $table->uuid('owner_company_id');

            // همه مبالغ snapshot لحظه محاسبه‌اند، نه reference زنده به employees.base_salary
            // — CLAUDE.md بند ۵.۲.
            $table->decimal('gross_salary_amount', 18, 2);
            $table->decimal('overtime_amount', 18, 2)->default(0);
            $table->decimal('absence_deduction_amount', 18, 2)->default(0);
            $table->decimal('unpaid_leave_deduction_amount', 18, 2)->default(0);
            // ⚠️ فرمول موقت — نگاه کن config/payroll.php
            $table->decimal('insurance_amount', 18, 2)->default(0);
            // ⚠️ فرمول موقت — نگاه کن config/payroll.php
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('benefits_amount', 18, 2)->default(0);
            // gross + overtime + benefits - absence - unpaid_leave - insurance - tax
            $table->decimal('net_amount', 18, 2);

            $table->uuid('currency_id')->nullable(); // بدون FK فعلاً؛ ماژول Currency هنوز نیست

            // pending | posted — در این فاز همیشه pending. TODO: BACKLOG.md #1
            $table->string('expense_posting_status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'uq_payslip_run_employee');
            $table->index('expense_posting_status', 'idx_payslip_expense_status');
            $table->index(['owner_company_id', 'employee_id'], 'idx_payslip_company_employee');

            $table->foreign('payroll_run_id', 'fk_payslip_run')->references('id')->on('payroll_runs');
            $table->foreign('employee_id', 'fk_payslip_employee')->references('id')->on('employees');
            $table->foreign('owner_company_id', 'fk_payslip_company')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
