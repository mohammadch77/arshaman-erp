<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // مسیر ثبت گمنام (guest) است — owner_company_id مستقیم از پارامتر
            // route (companies.slug) پر می‌شود، نه از CompanyContext session
            // (که برای کاربر مهمان همیشه null است). عمداً بدون
            // created_by_user_id/updated_by_user_id چون ثبت‌کننده کاربر سامانه نیست.
            $table->uuid('owner_company_id');
            $table->string('full_name', 80);
            $table->char('phone', 11);
            $table->string('email', 255)->nullable();
            $table->string('subject', 100)->nullable();
            $table->text('message');
            // enum PHP در App\Modules\CRM\Enums\ContactSubmissionStatus + CHECK زیر.
            $table->string('status', 20)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->uuid('replied_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_contact_submissions_company');
            $table->index(['owner_company_id', 'status'], 'idx_contact_submissions_status');

            $table->foreign('owner_company_id', 'fk_contact_submissions_company')->references('id')->on('companies');
            $table->foreign('replied_by_user_id', 'fk_contact_submissions_replied_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های موجود
        // (products.fulfillment_type, employees.phone) — SQLite (محیط تست) این
        // سینتکس را پشتیبانی نمی‌کند، پس فقط روی MySQL واقعی اعمال می‌شود.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contact_submissions ADD CONSTRAINT chk_contact_submissions_status CHECK (status IN ('new', 'read', 'replied', 'archived'))");
            DB::statement("ALTER TABLE contact_submissions ADD CONSTRAINT chk_contact_submissions_phone CHECK (phone REGEXP '^09[0-9]{9}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
