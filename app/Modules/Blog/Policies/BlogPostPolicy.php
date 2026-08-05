<?php

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $post خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹ CLAUDE.md).
     */
    public function view(User $user, BlogPost $post): bool
    {
        return $user->hasRoleInCompany($post->owner_company_id);
    }

    public function create(User $user, ?string $companyId = null): bool
    {
        $companyId ??= app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    /**
     * holding_admin هر پستی را ویرایش می‌کند. operator فقط پست پیش‌نویسِ خودش را —
     * پستی که ادمین منتشر/زمان‌بندی کرده دیگر دست operator نیست.
     */
    public function update(User $user, BlogPost $post): bool
    {
        if ($user->hasRoleInCompany($post->owner_company_id, 'holding_admin')) {
            return true;
        }

        if (! $user->hasRoleInCompany($post->owner_company_id, 'operator')) {
            return false;
        }

        return $post->author_user_id === $user->id && $post->post_status === BlogPostStatus::Draft;
    }

    /**
     * دقیقاً همان قانون update — holding_admin هر پستی، operator فقط پیش‌نویس خودش.
     */
    public function delete(User $user, BlogPost $post): bool
    {
        return $this->update($user, $post);
    }

    /**
     * تنها holding_admin مجاز است status را به scheduled/published تغییر دهد.
     * متد جدا (نه بخشی از update) چون هم در فرم (فعال/غیرفعال‌کردن فیلد) و هم در Action لازم است.
     */
    public function canPublish(User $user, string $companyId): bool
    {
        return $user->hasRoleInCompany($companyId, 'holding_admin');
    }
}
