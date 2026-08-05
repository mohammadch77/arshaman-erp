<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * وضعیت «بایگانی‌شده» طبق درخواست صریح کارفرما کاملاً از enum PHP و مقادیر
     * مجاز CHECK حذف شد. رکورد قدیمی archived (اگر وجود داشت) قبل از تنگ‌کردن
     * CHECK به draft برگردانده می‌شود، وگرنه همان ALTER TABLE با رکورد موجود رد می‌شود.
     */
    public function up(): void
    {
        DB::table('blog_posts')->where('post_status', 'archived')->update(['post_status' => 'draft']);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE blog_posts DROP CHECK chk_blog_posts_status');
            DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT chk_blog_posts_status CHECK (post_status IN ('draft', 'scheduled', 'published'))");
        }
    }

    /**
     * برگشت لاغی است — رکوردهای backfill‌شده به draft قابل بازگرداندن به
     * archived نیستند (دیگر مشخص نیست کدام‌ها بودند)، فقط دامنه CHECK قبلی
     * برمی‌گردد.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE blog_posts DROP CHECK chk_blog_posts_status');
            DB::statement("ALTER TABLE blog_posts ADD CONSTRAINT chk_blog_posts_status CHECK (post_status IN ('draft', 'scheduled', 'published', 'archived'))");
        }
    }
};
