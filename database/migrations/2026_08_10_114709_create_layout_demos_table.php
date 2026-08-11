<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سراسری (بدون owner_company_id) — دموهای هدر/فوتر سراسری سایت، جدا از
        // صفحات. layout_type عمداً VARCHAR+CHECK استاندارد است، نه ENUM نیتیو —
        // استثنای بخش ۱۴ DATABASE_CONVENTIONS.md فقط برای page_categories.category_key
        // و pages.page_status مستند شده، نه یک تغییر قرارداد کلی برای کل ماژول.
        Schema::create('layout_demos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('layout_type', 20);
            $table->string('name', 60);
            $table->string('thumbnail_path', 255)->nullable();
            $table->json('widget_tree');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('layout_type', 'idx_layout_demos_type');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE layout_demos ADD CONSTRAINT chk_layout_demos_type CHECK (layout_type IN ('header', 'footer'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_demos');
    }
};
