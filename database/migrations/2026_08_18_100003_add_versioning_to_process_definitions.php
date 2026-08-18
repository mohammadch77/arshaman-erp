<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * نسخه‌بندی تعریف فرایند (بخش ۴.۲ Session جاری): وقتی یک تعریف فقط
     * instance تمام‌شده دارد (بدون هیچ در‌جریان)، ویرایش ساختاری دیگر جای
     * قبلی را رونویسی نمی‌کند — یک process_definitions تازه با همان process_key
     * و version+1 ساخته می‌شود (نگاه کن CreateProcessDefinitionVersion). از دید
     * UI فقط «یک فرایند با نام ثابت» دیده می‌شود چون فهرست/لوکاپ‌ها همیشه
     * is_current_version=true را می‌خوانند.
     *
     * UNIQUE قبلی (owner_company_id, process_key) با ورود version دیگر کافی
     * نیست — چند نسخه از یک process_key باید هم‌زمان بتوانند وجود داشته باشند.
     */
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('version')->default(1)->after('process_key');
            $table->boolean('is_current_version')->default(true)->after('version');
        });

        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropUnique('uq_process_definitions_company_key');
            $table->unique(['owner_company_id', 'process_key', 'version'], 'uq_process_definitions_company_key_version');
            $table->index(['owner_company_id', 'process_key', 'is_current_version'], 'idx_process_definitions_current_version');
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropIndex('idx_process_definitions_current_version');
            $table->dropUnique('uq_process_definitions_company_key_version');
            $table->unique(['owner_company_id', 'process_key'], 'uq_process_definitions_company_key');
        });

        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropColumn(['version', 'is_current_version']);
        });
    }
};
