<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Employee;

class AttendancePolicy
{
    /**
     * دسترسی پنل ادمین به حضور و غیاب: فقط ادمین کل، ادمین هلدینگ، یا حسابدار
     * — و دقیقاً در همان شرکت هدف.
     */
    protected function isAdminAuthorized(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminAuthorized($user, app(CompanyContext::class)->id());
    }

    /**
     * بدون نمونه (تردد هنوز ثبت نشده). $companyId اختیاری: RecordAttendance
     * شرکت هدف واقعی ($employee->owner_company_id) را صریح پاس می‌دهد؛ در
     * غیر این صورت (pre-check لایه Livewire) شرکت فعال سوییچر پیش‌فرض است.
     */
    public function recordAny(User $user, ?string $companyId = null): bool
    {
        return $this->isAdminAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }

    /**
     * دسترسی self-service: کارمند فقط به رکورد خودش، از طریق employees.user_id === Auth::id().
     */
    public function recordSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }

    /**
     * دسترسی به گزارش جمع ماهانه کارکرد (MonthlyAttendanceSummary): همان دسترسی پنل ادمین حضور و غیاب.
     */
    public function viewSummary(User $user): bool
    {
        return $this->isAdminAuthorized($user, app(CompanyContext::class)->id());
    }

    public function calculate(User $user, ?string $companyId = null): bool
    {
        return $this->isAdminAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }
}
