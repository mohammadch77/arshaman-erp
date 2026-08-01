<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Payslip;

class PayrollPolicy
{
    /**
     * دسترسی پنل ادمین به حقوق و دستمزد: فقط ادمین کل، ادمین هلدینگ، یا حسابدار.
     * همان الگوی LeavePolicy::isAdminAuthorized.
     */
    protected function isAdminAuthorized(User $user): bool
    {
        return $user->is_super_admin || $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    public function calculate(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    public function finalize(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    /**
     * بازگشایی یک دوره نهایی‌شده — همان سطح دسترسی نهایی‌کردن.
     *
     * عمداً متد جدا از finalize است (نه استفاده دوباره از همان) تا اگر بعداً
     * کارفرما تصمیم گرفت بازگشایی فقط کار holding_admin باشد و نه حسابدار،
     * تغییرش یک خط اینجا باشد، نه بازنویسی گارد در Action.
     */
    public function reopen(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    /**
     * دسترسی self-service به یک فیش مشخص.
     *
     * دو شرط هم‌زمان — هیچ‌کدام کافی نیست:
     * ۱. فیش متعلق به پرونده پرسنلی خودِ همین کاربر باشد (employees.user_id).
     * ۲. دوره حقوق نهایی شده باشد — کارمند نباید فیش draft/calculated را ببیند،
     *    چون تا لحظه finalize مبالغ می‌توانند با بازمحاسبه عوض شوند.
     *
     * این متد عمداً به نقش کاری ندارد: یک holding_admin هم اگر فیش کارمند دیگری
     * را از این مسیر بخواهد، رد می‌شود. مسیر ادمین viewAny است، نه این.
     */
    public function viewOwn(User $user, Payslip $payslip): bool
    {
        $employee = $payslip->employee()->withoutGlobalScopes()->first();

        if ($employee?->user_id === null || $employee->user_id !== $user->id) {
            return false;
        }

        return $payslip->payrollRun()->withoutGlobalScopes()->first()?->isLocked() === true;
    }
}
