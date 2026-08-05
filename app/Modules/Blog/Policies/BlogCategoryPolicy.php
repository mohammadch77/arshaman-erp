<?php

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class BlogCategoryPolicy
{
    /**
     * عمداً بدون accountant — مدیریت taxonomy محتوایی است نه مالی (برخلاف Product).
     */
    protected function canManage(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, BlogCategory $category): bool
    {
        return $user->hasRoleInCompany($category->owner_company_id);
    }

    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->canManage($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, BlogCategory $category): bool
    {
        return $this->canManage($user, $category->owner_company_id);
    }
}
