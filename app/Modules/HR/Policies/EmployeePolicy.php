<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Employee;

class EmployeePolicy
{
    /**
     * دسترسی به بخش پرسنل: فقط ادمین کل، ادمین هلدینگ، یا حسابدار — و دقیقاً
     * در همان شرکت هدف. ادمین کل با Gate::before پیش‌تر تأیید می‌شود.
     */
    protected function isAuthorized(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorized($user, app(CompanyContext::class)->id());
    }

    /**
     * شرکت از خودِ $employee خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹).
     */
    public function view(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user, $employee->owner_company_id);
    }

    /**
     * بدون نمونه (هنوز ساخته نشده). $companyId اختیاری: CreateEmployee شرکت
     * هدف واقعی ($data['owner_company_id']) را صریح پاس می‌دهد؛ در غیر این
     * صورت شرکت فعال سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->isAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user, $employee->owner_company_id);
    }

    public function terminate(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user, $employee->owner_company_id);
    }

    public function link(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user, $employee->owner_company_id);
    }
}
