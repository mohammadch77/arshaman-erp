<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\ContactSiteProfile;

/**
 * همان دو نقش بقیه Policy های CRM (holding_admin/operator). برخلاف
 * InteractionPolicy/LeadPolicy که 'view' شرکت هدف را از رکورد خودشان
 * می‌خوانند، این‌جا فهرست شرکت‌محور است (مثل LeadBoard) پس viewAny از
 * CompanyContext فعال سوییچر می‌خواند؛ calculate شرکت هدف را از خودِ
 * ContactSiteProfile می‌خواند چون Action مستقل از session فعال کار می‌کند.
 */
class RfmSegmentPolicy
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

    public function calculate(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorizedForCompany($user, $profile->owner_company_id);
    }
}
