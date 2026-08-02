<?php

namespace App\Modules\CRM\Policies;

use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

/**
 * دسترسی سطح شرکت: هر نقشی که در شرکت دارد پروفایل‌های همان شرکت را ببیند؛
 * ساخت/ویرایش فقط برای نقش‌های عملیاتی/مالی، طبق الگوی PartyPolicy.
 */
class ContactSiteProfilePolicy
{
    protected function canManage(User $user): bool
    {
        return $user->hasRole('holding_admin') || $user->hasRole('accountant') || $user->hasRole('operator');
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, ContactSiteProfile $profile): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ContactSiteProfile $profile): bool
    {
        return $this->canManage($user);
    }
}
