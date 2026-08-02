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
    /**
     * عمداً از User::hasRole() استفاده نمی‌شود — آن متد سراسری است (نقش را در
     * *هر* شرکتی که کاربر داشته باشد پیدا می‌کند)، نه فقط $companyId. اگر اینجا
     * `hasRoleInCompany($companyId) && hasRole('operator')` نوشته می‌شد، کاربری
     * که فقط viewer شرکت ب است ولی operator شرکت الف هم هست، برای تعامل روی
     * پروفایل شرکت ب هم مجاز تشخیص داده می‌شد — نشت ایزولاسیون شرکت (بند ۵.۱).
     * پس یک کوئری واحد لازم است: نقش هلدینگ_ادمین/operator دقیقاً *در همان*
     * $companyId.
     */
    protected function isAuthorizedForCompany(User $user, string $companyId): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $user->companyRoles()
            ->where('owner_company_id', $companyId)
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['holding_admin', 'operator']))
            ->exists();
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
