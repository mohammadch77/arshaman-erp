<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Mews\Purifier\Facades\Purifier;

/**
 * تنها Action ای که با هر ضربه کلید (بعد از debounce) صدا زده می‌شود، پس عمداً
 * بسیار محدودتر از CreateBlogPost/UpdateBlogPost است: فقط title/slug/content_html
 * را می‌نویسد و هرگز اعتبارسنجی کامل (متا، تصویر، وضعیت) اجرا نمی‌کند — کاربر
 * هنوز در حال تایپ است.
 */
class AutosaveBlogPostDraft
{
    public function handle(?BlogPost $post, array $data, User $actor): BlogPost
    {
        if ($post) {
            Gate::forUser($actor)->authorize('update', $post);

            if ($post->post_status !== BlogPostStatus::Draft) {
                throw new InvalidArgumentException('فقط پیش‌نویس قابل ذخیره خودکار است.');
            }

            $post->update([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($post->owner_company_id, $data['slug'], $post->id),
                'content_html' => Purifier::clean($data['content_html'] ?? ''),
                'updated_by_user_id' => $actor->id,
            ]);

            return $post->refresh();
        }

        Gate::forUser($actor)->authorize('create', [BlogPost::class, $data['owner_company_id']]);

        return BlogPost::create([
            'owner_company_id' => $data['owner_company_id'],
            'author_user_id' => $actor->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['owner_company_id'], $data['slug'], null),
            'content_html' => Purifier::clean($data['content_html'] ?? ''),
            'post_status' => BlogPostStatus::Draft->value,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    /**
     * برخلاف CreateBlogPost/UpdateBlogPost که رد تکراری slug را به عهده
     * اعتبارسنجی فرم می‌گذارند، اینجا نباید تصادم روی نام مشابه یک ذخیره
     * خودکار خاموش را بشکند — پس خودش با پسوند عددی یکتا می‌سازد.
     */
    protected function uniqueSlug(string $companyId, string $baseSlug, ?string $excludeId): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (
            BlogPost::withoutGlobalScopes()
                ->where('owner_company_id', $companyId)
                ->where('slug', $slug)
                ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
