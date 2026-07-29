<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_attendance_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('owner_company_id');

            // شمسی مثل '1405-04'
            $table->string('period_month', 7);

            $table->integer('total_worked_days')->default(0);
            $table->integer('total_absent_days')->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->integer('total_overtime_minutes')->default(0);
            // مرخصی‌های approved همان ماه — تا Session مرخصی ساخته نشده، همیشه صفر
            $table->integer('total_leave_days')->default(0);

            $table->timestamp('calculated_at')->nullable();

            $table->unique(['employee_id', 'period_month'], 'uq_monthly_summary_employee_period');
            $table->index(['owner_company_id', 'period_month'], 'idx_monthly_summary_company_period');

            $table->foreign('employee_id', 'fk_monthly_summary_employee')->references('id')->on('employees');
            $table->foreign('owner_company_id', 'fk_monthly_summary_company')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_attendance_summaries');
    }
};
