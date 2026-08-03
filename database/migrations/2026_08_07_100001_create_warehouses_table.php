<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عمداً بدون owner_company_id — طبق بند ۵.۸ CLAUDE.md انبار فیزیکاً بین
        // شرکت‌ها مشترک است (یک ساختمان/فضای واحد). موجودی به تفکیک مالکیت
        // در stocks.owner_company_id نگه‌داری می‌شود، نه اینجا. همان الگوی
        // منابع مشترک هلدینگ (contacts, holidays, currencies) — هرگز BelongsToCompany
        // روی مدل این جدول نگذار.
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id', 'fk_warehouses_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_warehouses_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
