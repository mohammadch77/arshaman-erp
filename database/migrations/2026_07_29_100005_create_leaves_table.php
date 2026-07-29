<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('owner_company_id');

            // annual | sick | unpaid
            $table->string('leave_type', 20);
            $table->date('start_date');
            $table->date('end_date');
            // بدون جمعه/تعطیل رسمی، از WorkCalendar
            $table->integer('days_count');

            // pending | approved | rejected
            $table->string('leave_status', 20)->default('pending');
            $table->text('reason')->nullable();

            $table->uuid('approved_by_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'leave_status'], 'idx_leaves_employee_status');
            $table->index(['owner_company_id', 'start_date', 'end_date'], 'idx_leaves_company_dates');

            $table->foreign('employee_id', 'fk_leaves_employee')->references('id')->on('employees');
            $table->foreign('owner_company_id', 'fk_leaves_company')->references('id')->on('companies');
            $table->foreign('approved_by_user_id', 'fk_leaves_approved_by')->references('id')->on('users');
            $table->foreign('created_by_user_id', 'fk_leaves_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
