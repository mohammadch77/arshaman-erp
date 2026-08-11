<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('page_demo_id');
            $table->string('title', 150);
            $table->string('slug', 150);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            // کپی از widget_tree دموی انتخاب‌شده هنگام ساخت صفحه + مقادیر
            // ویرایش‌شده کاربر. ساختار (تعداد/ترتیب ویجت) هرگز از طریق UI
            // تغییر نمی‌کند، فقط مقادیر داخل فیلدهای هر ویجت.
            $table->json('widget_tree');
            // همیشه از WidgetContentRenderer ساخته می‌شود، هرگز مستقیم از فرم.
            $table->longText('content_html');
            // طبق تصمیم مستند DECISIONS.md: operator هم اجازه نوشتن این دو
            // فیلد را دارد، بدون sanitize — ریسک آگاهانه پذیرفته‌شده کارفرما.
            $table->text('extra_css')->nullable();
            $table->text('extra_js')->nullable();
            // ENUM نیتیو MySQL طبق استثنای مستند بخش ۱۴ DATABASE_CONVENTIONS.md.
            $table->enum('page_status', ['draft', 'published'])->default('draft');
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_pages_company');
            $table->index('page_demo_id', 'idx_pages_demo');
            $table->index(['owner_company_id', 'page_status'], 'idx_pages_status');
            $table->unique(['owner_company_id', 'slug'], 'uq_pages_company_slug');

            $table->foreign('owner_company_id', 'fk_pages_company')->references('id')->on('companies');
            $table->foreign('page_demo_id', 'fk_pages_demo')->references('id')->on('page_demos');
            $table->foreign('created_by_user_id', 'fk_pages_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_pages_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
