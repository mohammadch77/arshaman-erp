<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class PartyPolicy
{
    /**
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط نقش‌های دیگر را بررسی می‌کنیم.
     * ساخت/ویرایش طرف‌حساب فقط برای نقش‌های عملیاتی/مالی، طبق الگوی EmployeePolicy.
     */
    protected function canManage(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant', 'operator']);
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $party خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹).
     */
    public function view(User $user, Party $party): bool
    {
        return $user->hasRoleInCompany($party->owner_company_id);
    }

    /**
     * بدون نمونه (هنوز ساخته نشده). $companyId اختیاری: اگر Action شرکت هدف
     * واقعی را می‌داند (مثلاً از $data['owner_company_id']) همان را صریح
     * پاس می‌دهد؛ در غیر این صورت (مثلاً pre-check لایه Livewire) شرکت فعال
     * سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->canManage($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Party $party): bool
    {
        return $this->canManage($user, $party->owner_company_id);
    }
}
