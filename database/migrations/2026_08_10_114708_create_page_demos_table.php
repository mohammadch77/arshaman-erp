<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سراسری (بدون owner_company_id) — کاتالوگ دموی آماده هر دسته، فقط
        // توسط seeder/تیم فنی ساخته می‌شود، مثل widgets.
        Schema::create('page_demos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_category_id');
            $table->string('name', 60);
            $table->string('thumbnail_path', 255)->nullable();
            $table->json('widget_tree');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('page_category_id', 'idx_page_demos_category');
            $table->foreign('page_category_id', 'fk_page_demos_category')->references('id')->on('page_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_demos');
    }
};
