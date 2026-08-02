<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\FiscalPeriod;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class FiscalPeriodPolicy
{
    /**
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط نقش‌های دیگر را
     * بررسی می‌کنیم. عمداً hasRoleInCompany($companyId, 'holding_admin') یک
     * کوئری واحد است، نه hasRoleInCompany($companyId) + hasRole('holding_admin')
     * جدا — آن ترکیب چون hasRole() سراسری است، کاربری که holding_admin شرکت
     * دیگری است را هم برای این شرکت مجاز می‌کرد (بند ۹ CLAUDE.md).
     */
    protected function canManage(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, 'holding_admin');
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $fiscalPeriod خوانده می‌شود، نه از CompanyContext فعال —
     * دفاع‌درعمق (بند ۹).
     */
    public function view(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return $user->hasRoleInCompany($fiscalPeriod->owner_company_id);
    }

    /**
     * بدون نمونه (هنوز ساخته نشده). $companyId اختیاری: CreateFiscalPeriod
     * شرکت هدف واقعی ($ownerCompanyId) را صریح پاس می‌دهد؛ در غیر این صورت
     * شرکت فعال سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->canManage($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function close(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return $this->canManage($user, $fiscalPeriod->owner_company_id);
    }
}
