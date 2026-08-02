<?php

namespace App\Modules\CRM\Policies;

use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

/**
 * دسترسی سطح شرکت: عمداً محدودتر از الگوی PartyPolicy — فقط holding_admin
 * و operator. کار accountant با Party است (طرف‌حساب مالی)، نه Contact؛
 * viewer هم از اساس دسترسی مدیریتی ندارد. مشاهده و مدیریت هر دو یک سطح
 * دسترسی دارند، پس یک متد مشترک کافی است.
 */
class ContactSiteProfilePolicy
{
    protected function isAuthorized(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null || ! $user->hasRoleInCompany($companyId)) {
            return false;
        }

        return $user->hasRole('holding_admin') || $user->hasRole('operator');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function view(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorized($user);
    }

    public function create(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function update(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorized($user);
    }
}
