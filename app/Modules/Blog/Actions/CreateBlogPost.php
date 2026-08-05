<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CreateBlogPost
{
    /**
     * @param  array{owner_company_id: string, category_id: ?string, author_user_id: ?string, title: string, slug: string, meta_title: ?string, meta_description: ?string, content_blocks: array, content_html: ?string, featured_image_path: ?string, reading_time_minutes: ?int, post_status: string, published_at: ?string, tag_ids: array<int, string>}  $data
     */
    public function handle(array $data, User $actor): BlogPost
    {
        Gate::forUser($actor)->authorize('create', [BlogPost::class, $data['owner_company_id']]);

        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        // operator فقط پیش‌نویس خودش می‌سازد — ورودی نویسنده/وضعیت هرچه باشد، نادیده گرفته می‌شود.
        if (! $actor->hasRoleInCompany($data['owner_company_id'], 'holding_admin')) {
            $data['author_user_id'] = $actor->id;
            $data['post_status'] = BlogPostStatus::Draft->value;
            $data['published_at'] = null;
        }

        if ($data['post_status'] === BlogPostStatus::Scheduled->value && empty($data['published_at'])) {
            throw new InvalidArgumentException('برای وضعیت زمان‌بندی‌شده، تاریخ انتشار الزامی است.');
        }

        return DB::transaction(function () use ($data, $tagIds, $actor) {
            $post = BlogPost::create([
                ...$data,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $post->tags()->sync($tagIds);

            return $post;
        });
    }
}
