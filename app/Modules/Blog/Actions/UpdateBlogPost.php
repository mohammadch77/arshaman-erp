<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateBlogPost
{
    /**
     * @param  array{category_id: ?string, author_user_id: ?string, title: string, slug: string, meta_title: ?string, meta_description: ?string, content_blocks: array, content_html: ?string, featured_image_path: ?string, reading_time_minutes: ?int, post_status: string, published_at: ?string, tag_ids: array<int, string>}  $data
     */
    public function handle(BlogPost $post, array $data, User $actor): BlogPost
    {
        Gate::forUser($actor)->authorize('update', $post);

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        // operator نمی‌تواند نویسنده یا وضعیت را عوض کند — طبق Policy این مسیر فقط برای
        // پست‌های از قبل draft خودِ operator باز است، پس همیشه همان‌جا می‌ماند.
        if (! $actor->hasRoleInCompany($post->owner_company_id, 'holding_admin')) {
            $data['author_user_id'] = $post->author_user_id;
            $data['post_status'] = BlogPostStatus::Draft->value;
            $data['published_at'] = null;
        }

        if ($data['post_status'] === BlogPostStatus::Scheduled->value && empty($data['published_at'])) {
            throw new InvalidArgumentException('برای وضعیت زمان‌بندی‌شده، تاریخ انتشار الزامی است.');
        }

        DB::transaction(function () use ($post, $data, $tagIds, $actor) {
            $post->update([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);

            $post->tags()->sync($tagIds);
        });

        return $post->refresh();
    }
}
