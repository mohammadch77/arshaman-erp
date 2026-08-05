<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فقط سینتکس MySQL دارد (DROP CHECK / ADD CONSTRAINT) — همان الگوی
        // 2026_08_03_000001_fix_datatypes_and_add_checks. روی SQLite (محیط تست) کاری
        // انجام نمی‌دهد چون آن CHECK از ابتدا آنجا اضافه نشده بود.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contact_submissions DROP CHECK chk_contact_submissions_status');
        DB::statement("ALTER TABLE contact_submissions ADD CONSTRAINT chk_contact_submissions_status CHECK (status IN ('new', 'read', 'in_progress', 'replied', 'archived'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contact_submissions DROP CHECK chk_contact_submissions_status');
        DB::statement("ALTER TABLE contact_submissions ADD CONSTRAINT chk_contact_submissions_status CHECK (status IN ('new', 'read', 'replied', 'archived'))");
    }
};
