<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 100); // "سال مالی ۱۴۰۵"
            $table->date('start_date'); // میلادی معادل اول فروردین
            $table->date('end_date'); // میلادی معادل آخر اسفند
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['owner_company_id', 'name'], 'uq_fiscal_period_company_name');
            $table->index(['owner_company_id', 'start_date', 'end_date'], 'idx_fiscal_period_company_dates');

            $table->foreign('owner_company_id', 'fk_fiscal_period_company')->references('id')->on('companies');
            $table->foreign('closed_by_user_id', 'fk_fiscal_period_closed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
