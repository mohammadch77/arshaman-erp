<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\Campaign;

/**
 * همان دو نقش بقیه Policy های CRM (holding_admin/operator) — نگاه کن
 * LeadPolicy/RfmSegmentPolicy برای دلیل کامل. viewAny شرکت‌محور است (فهرست
 * کمپین‌های شرکت فعال سوییچر، مثل LeadBoard)؛ create/update/trigger شرکت
 * هدف را از خودِ رکورد یا پارامتر صریح می‌خوانند تا مستقل از session فعال
 * (مثل یک Action صداشده از جای دیگر) هم درست کار کنند.
 */
class CampaignPolicy
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

    /**
     * بدون نمونه. $companyId اختیاری: CreateCampaign شرکت هدف واقعی
     * ($data['owner_company_id']) را صریح پاس می‌دهد.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->isAuthorizedForCompany($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->isAuthorizedForCompany($user, $campaign->owner_company_id);
    }

    public function trigger(User $user, Campaign $campaign): bool
    {
        return $this->isAuthorizedForCompany($user, $campaign->owner_company_id);
    }
}
