<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;

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
}
