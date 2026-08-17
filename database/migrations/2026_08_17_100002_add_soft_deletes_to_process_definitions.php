<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف واقعی فرایند (Session جاری): اگر تعریف هیچ‌وقت instance نداشته، حذف کامل
     * (hard delete) کافی است؛ اگر حتی یک instance (حتی تاریخی) دارد، RESTRICT FK های
     * process_instances/process_instance_logs → process_steps اجازه‌ی حذف واقعی
     * مراحل را نمی‌دهند و نباید بدهند — به‌جایش soft-delete تا از فهرست فعال مخفی
     * شود ولی داده‌ی تاریخی/لاگ دست‌نخورده بماند (بند ۳ CLAUDE.md: هرگز حذف فیزیکی).
     */
    public function up(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
