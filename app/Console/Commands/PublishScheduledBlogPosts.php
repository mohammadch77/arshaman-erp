<?php

namespace App\Console\Commands;

use App\Modules\Blog\Actions\PublishScheduledPost;
use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduledBlogPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'پست‌های وبلاگ زمان‌بندی‌شده‌ای که زمان انتشارشان فرارسیده را منتشر می‌کند';

    public function handle(PublishScheduledPost $action): int
    {
        // withoutGlobalScopes: این یک فرآیند سراسری هلدینگ است، محدود به
        // CompanyContext یک session نیست (که در کانتکست CLI اصلاً وجود ندارد).
        $duePosts = BlogPost::withoutGlobalScopes()
            ->where('post_status', BlogPostStatus::Scheduled)
            ->where('published_at', '<=', now())
            ->get();

        foreach ($duePosts as $post) {
            $action->handle($post);
            $this->info("منتشر شد: {$post->title}");
        }

        if ($duePosts->isEmpty()) {
            $this->info('هیچ پست زمان‌بندی‌شده‌ای برای انتشار وجود نداشت.');
        }

        return self::SUCCESS;
    }
}
