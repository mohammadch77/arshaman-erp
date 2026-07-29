<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');

            // شمسی، مثل '1405-04'
            $table->string('period_month', 7);

            // draft | calculated | finalized
            $table->string('payroll_status', 20)->default('draft');

            $table->timestamp('calculated_at')->nullable();
            $table->uuid('calculated_by_user_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->uuid('finalized_by_user_id')->nullable();
            $table->timestamps();

            // یک دوره حقوق در هر ماه/شرکت — ضامن idempotency در سطح دیتابیس،
            // نه فقط در لایه اپلیکیشن (CLAUDE.md بند ۳: اعتبارسنجی در هر دو لایه).
            $table->unique(['owner_company_id', 'period_month'], 'uq_payroll_run_company_period');

            $table->foreign('owner_company_id', 'fk_payroll_run_company')->references('id')->on('companies');
            $table->foreign('calculated_by_user_id', 'fk_payroll_run_calculated_by')->references('id')->on('users');
            $table->foreign('finalized_by_user_id', 'fk_payroll_run_finalized_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
