<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;

class LeavePolicy
{
    /**
     * دسترسی پنل ادمین به مرخصی‌ها: فقط ادمین کل، ادمین هلدینگ، یا حسابدار.
     */
    protected function isAdminAuthorized(User $user): bool
    {
        return $user->is_super_admin || $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    public function requestAny(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    /**
     * دسترسی self-service: کارمند فقط برای خودش، از طریق employees.user_id === Auth::id().
     */
    public function requestSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }

    /**
     * تأیید/رد درخواست مرخصی: فقط پنل ادمین — کارمند هرگز نمی‌تواند مرخصی
     * (حتی مرخصی خودش) را تأیید/رد کند.
     */
    public function review(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    /**
     * ویرایش درخواست توسط خودِ کارمند.
     *
     * دو شرط هم‌زمان و هیچ‌کدام کافی نیست:
     * ۱. درخواست متعلق به پرونده پرسنلی خودِ همین کاربر باشد.
     * ۲. هنوز در وضعیت `pending` باشد — بعد از تأیید یا رد، درخواست بخشی از
     *    داده‌ای است که جمع ماهانه و فیش حقوقی روی آن حساب کرده‌اند.
     *
     * ادمین عمداً از این مسیر رد می‌شود: تغییر یک مرخصی تأییدشده باید مسیر
     * صریح خودش را داشته باشد، نه ویرایش خاموش از پنل کارمند.
     */
    public function updateSelf(User $user, Leave $leave): bool
    {
        if (! $leave->isEditableByOwner()) {
            return false;
        }

        $employee = $leave->employee()->withoutGlobalScopes()->first();

        return $employee?->user_id !== null && $employee->user_id === $user->id;
    }

    /**
     * حذف (نرم) درخواست توسط خودِ کارمند — همان دو شرط ویرایش.
     */
    public function deleteSelf(User $user, Leave $leave): bool
    {
        return $this->updateSelf($user, $leave);
    }
}
