<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\ContactSiteProfile;

/**
 * دسترسی سطح شرکت: عمداً محدودتر از الگوی PartyPolicy — فقط holding_admin
 * و operator. کار accountant با Party است (طرف‌حساب مالی)، نه Contact؛
 * viewer هم از اساس دسترسی مدیریتی ندارد. مشاهده و مدیریت هر دو یک سطح
 * دسترسی دارند، پس یک متد مشترک کافی است.
 *
 * view/update عمداً شرکت را از خودِ $profile می‌خوانند، نه از CompanyContext
 * فعال — دفاع‌درعمق (بند ۹ CLAUDE.md): حتی اگر معمولاً model binding با
 * Global Scope همان شرکت فعال را برمی‌گرداند، خود Policy نباید کورکورانه به
 * session جاری تکیه کند وقتی خودِ نمونه شرکتش را می‌داند.
 */
class ContactSiteProfilePolicy
{
    protected function isAuthorizedForCompany(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorizedForCompany($user, app(CompanyContext::class)->id());
    }

    public function view(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorizedForCompany($user, $profile->owner_company_id);
    }

    /**
     * بدون نمونه (هنوز ساخته نشده). $companyId اختیاری: CreateContactSiteProfile
     * شرکت هدف واقعی ($data['owner_company_id']) را صریح پاس می‌دهد؛ در غیر
     * این صورت شرکت فعال سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->isAuthorizedForCompany($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorizedForCompany($user, $profile->owner_company_id);
    }
}
