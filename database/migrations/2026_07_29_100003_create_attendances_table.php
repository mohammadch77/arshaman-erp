<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('owner_company_id');

            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);

            // self = خودِ کارمند از پنل شخصی، admin = ثبت دستی ادمین/حسابدار
            $table->string('recorded_by', 10)->default('admin');

            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date'], 'uq_attendance_employee_date');
            $table->index(['owner_company_id', 'attendance_date'], 'idx_attendance_company_date');

            $table->foreign('employee_id', 'fk_attendance_employee')->references('id')->on('employees');
            $table->foreign('owner_company_id', 'fk_attendance_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_attendance_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
