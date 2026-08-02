<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Interaction;

/**
 * همان دو نقش ContactSiteProfilePolicy: holding_admin/operator. برخلاف آن
 * Policy، این‌جا به CompanyContext فعال سوییچر تکیه نمی‌شود — چون تعامل به یک
 * ContactSiteProfile مشخص (که می‌تواند در هر شرکتی باشد، حتی غیر از شرکت فعال
 * سوییچر، مثلاً از داخل نمای ۳۶۰ هلدینگی) وصل می‌شود، شرکت هدف باید از خودِ
 * profile خوانده شود، نه از session جاری.
 */
class InteractionPolicy
{
    protected function isAuthorizedForCompany(User $user, string $companyId): bool
    {
        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    public function view(User $user, Interaction $interaction): bool
    {
        return $this->isAuthorizedForCompany($user, $interaction->owner_company_id);
    }

    public function create(User $user, ContactSiteProfile $profile): bool
    {
        return $this->isAuthorizedForCompany($user, $profile->owner_company_id);
    }
}
