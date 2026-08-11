<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول سراسری (بدون owner_company_id) — کاتالوگ ویجت مشترک بین کل
        // هلدینگ، مثل contacts/holidays. مدیریت‌شده توسط تیم فنی/seeder، نه
        // ورود کاربر نهایی؛ به همین دلیل بدون created_by_user_id/updated_by_user_id
        // (بند ۱ DATABASE_CONVENTIONS.md — جدول‌های سراسری خیلی ساده مستثنی‌اند).
        Schema::create('widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('widget_key', 30)->unique();
            $table->string('name', 60);
            $table->string('icon', 50)->nullable();
            // شامل تعریف "فیلدهای قابل ویرایش" هر ویجت است، مثلاً:
            // {"editable_fields": [{"key":"text","type":"text","label":"متن عنوان"}]}
            $table->json('default_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
