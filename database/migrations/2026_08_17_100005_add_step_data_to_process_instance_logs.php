<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * وقتی مرحله‌ای که این تصمیم رویش گرفته شده یک step_form_fields داشته
     * باشد (بخش ۳ Session جاری)، مقادیر واردشده در همان لحظه‌ی تأیید/رد اینجا
     * ذخیره می‌شوند — تاریخچه‌ی کامل هر مرحله با داده‌ی خودش، نه فقط comment
     * متنی آزاد. رکورد لاگ همچنان غیرقابل‌ویرایش می‌ماند (بدون updated_at).
     */
    public function up(): void
    {
        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->json('step_data')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->dropColumn('step_data');
        });
    }
};
