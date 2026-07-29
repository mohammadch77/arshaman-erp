<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // NULL = تعطیلی سراسری (همه شرکت‌ها)؛ عمداً بدون BelongsToCompany
            // چون آن trait فقط رکوردهای متعلق به شرکت فعال را نگه می‌دارد، نه NULL سراسری.
            $table->uuid('owner_company_id')->nullable();

            $table->string('title', 150);
            $table->date('holiday_date');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('holiday_date', 'idx_holidays_date');
            $table->index('owner_company_id', 'idx_holidays_company');

            $table->foreign('owner_company_id', 'fk_holidays_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_holidays_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
