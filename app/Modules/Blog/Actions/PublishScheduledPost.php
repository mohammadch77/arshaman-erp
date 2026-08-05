<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;

/**
 * تنها caller این Action یک فرآیند سیستمی زمان‌بندی‌شده است (blog:publish-scheduled)،
 * نه یک کاربر. برخلاف بقیه Action های ماژول Blog، عمداً پارامتر actor و
 * Gate::authorize ندارد — دقیقاً همان الگوی CreateFiscalPeriod::buildAttributes()
 * برای seederها: وقتی کاربری برای authorize کردن وجود ندارد، از مسیر Gate/Policy
 * عبور نمی‌کنیم.
 */
class PublishScheduledPost
{
    public function handle(BlogPost $post): BlogPost
    {
        $post->post_status = BlogPostStatus::Published;
        $post->save();

        activity()
            ->causedBy(null)
            ->performedOn($post)
            ->withProperties(['event' => 'scheduled_auto_publish'])
            ->log('پست به‌صورت خودکار توسط سیستم منتشر شد');

        return $post;
    }
}
