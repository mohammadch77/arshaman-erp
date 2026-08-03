<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // همان دلیل migration های 2026_08_03/2026_08_04: سینتکس MySQL خام،
        // روی sqlite (تست پیش‌فرض) کاری انجام نمی‌دهد.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // بلندتر از نام کارمند (VARCHAR(80)) چون اسم اشخاص حقوقی می‌تواند بلندتر
        // باشد، ولی کوتاه‌تر از VARCHAR(200) قبلی که بیش‌ازحد بود.
        DB::statement('ALTER TABLE parties MODIFY name VARCHAR(150) NOT NULL');

        // بزرگ‌تر از قبل — بدون ریسک truncation.
        DB::statement('ALTER TABLE parties MODIFY email VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE parties MODIFY name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE parties MODIFY email VARCHAR(200) NULL');
    }
};
