<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\ContactSubmission;

/**
 * همان دو نقش بقیه Policy های CRM (holding_admin/operator)، همان الگوی
 * RfmSegmentPolicy: viewAny شرکت‌محور است و از CompanyContext فعال سوییچر
 * می‌خواند (پنل ادمین فقط پیام‌های شرکت فعال را نشان می‌دهد)، ولی
 * view/update همیشه owner_company_id خودِ رکورد را چک می‌کنند.
 */
class ContactSubmissionPolicy
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

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $this->isAuthorizedForCompany($user, $submission->owner_company_id);
    }

    public function update(User $user, ContactSubmission $submission): bool
    {
        return $this->isAuthorizedForCompany($user, $submission->owner_company_id);
    }
}
