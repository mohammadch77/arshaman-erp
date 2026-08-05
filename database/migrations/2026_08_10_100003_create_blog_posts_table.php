<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('category_id')->nullable();
            $table->uuid('author_user_id');
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->json('content_blocks');
            // خالی می‌ماند این Session — رندر HTML سمت سرور در Session بعد با Editor.js واقعی می‌آید.
            $table->longText('content_html')->nullable();
            $table->string('featured_image_path', 255)->nullable();
            $table->smallInteger('reading_time_minutes')->unsigned()->nullable();
            // عمداً post_status نه status خام — بند ۳ DATABASE_CONVENTIONS.md: بعد وضعیت باید در نام باشد.
            $table->string('post_status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_blog_posts_company');
            $table->index('category_id', 'idx_blog_posts_category');
            $table->index('author_user_id', 'idx_blog_posts_author');
            $table->index(['owner_company_id', 'post_status'], 'idx_blog_posts_status');
            $table->unique(['owner_company_id', 'slug'], 'uq_blog_posts_company_slug');

            $table->foreign('owner_company_id', 'fk_blog_posts_company')->references('id')->on('companies');
            $table->foreign('category_id', 'fk_blog_posts_category')->references('id')->on('blog_categories');
            $table->foreign('author_user_id', 'fk_blog_posts_author')->references('id')->on('users');
            $table->foreign('created_by_user_id', 'fk_blog_posts_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_blog_posts_updated_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های قبلی — SQLite (محیط تست)
        // این سینتکس را پشتیبانی نمی‌کند، پس فقط روی mysql واقعی اعمال می‌شود.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT chk_blog_posts_status CHECK (post_status IN ('draft', 'scheduled', 'published', 'archived'))");
            DB::statement('ALTER TABLE blog_posts ADD CONSTRAINT chk_blog_posts_scheduled_needs_date CHECK (post_status <> \'scheduled\' OR published_at IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
