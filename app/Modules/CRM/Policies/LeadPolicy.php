<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\Lead;

/**
 * همان دو نقش بقیه Policy های CRM (holding_admin/operator) — نگاه کن
 * ContactSiteProfilePolicy برای دلیل کامل. view/update عمداً شرکت را از خودِ
 * $lead می‌خوانند، نه از CompanyContext فعال.
 */
class LeadPolicy
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

    public function view(User $user, Lead $lead): bool
    {
        return $this->isAuthorizedForCompany($user, $lead->owner_company_id);
    }

    /**
     * بدون نمونه. $companyId اختیاری: CreateLead شرکت هدف واقعی
     * ($data['owner_company_id']) را صریح پاس می‌دهد.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->isAuthorizedForCompany($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->isAuthorizedForCompany($user, $lead->owner_company_id);
    }
}
