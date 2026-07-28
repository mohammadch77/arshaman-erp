<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');

            // اتصال از طریق سیستم دعوت‌نامه موجود (User Invitation) انجام می‌شود،
            // نه در این Session — نگاه کن Session 2.5 در docs/PROJECT_02_HR.md
            $table->uuid('user_id')->nullable();

            $table->string('full_name', 200);
            $table->string('national_id', 10);
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('position', 150);
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('employment_status', 20)->default('active');
            $table->string('contract_type', 20);
            $table->date('contract_start_date');
            $table->date('contract_end_date')->nullable();
            $table->decimal('base_salary', 18, 2);
            $table->uuid('currency_id')->nullable(); // بدون FK فعلاً؛ ماژول Currency هنوز نیست
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_company_id', 'national_id'], 'uq_employees_company_national_id');
            $table->index(['owner_company_id', 'employment_status'], 'idx_employees_company_status');
            $table->index('user_id', 'idx_employees_user');

            $table->foreign('owner_company_id', 'fk_employees_company')->references('id')->on('companies');
            $table->foreign('user_id', 'fk_employees_user')->references('id')->on('users');
            $table->foreign('created_by_user_id', 'fk_employees_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_employees_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
