<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فرم «تماس با ما» (contact_form) و فرم ثبت‌نام مشتری (site_signup، ویجت
     * جدید customer_signup_form) هر دو روی همان جدول contact_submissions
     * می‌نویسند (بند ۹ CLAUDE.md — تنها الگوی موجود نوشتن CRM بدون کاربر
     * واردشده)؛ این ستون تفکیک منبع را برای پنل ادمین ممکن می‌کند.
     */
    public function up(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('source', 20)->default('contact_form')->after('message');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contact_submissions ADD CONSTRAINT chk_contact_submissions_source CHECK (source IN ('contact_form', 'site_signup'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE contact_submissions DROP CHECK chk_contact_submissions_source');
        }

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
