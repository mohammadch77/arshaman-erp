<?php

namespace App\Modules\Blog\Policies;

use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class BlogTagPolicy
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

    public function view(User $user, BlogTag $tag): bool
    {
        return $user->hasRoleInCompany($tag->owner_company_id);
    }

    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->canManage($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, BlogTag $tag): bool
    {
        return $this->canManage($user, $tag->owner_company_id);
    }
}
