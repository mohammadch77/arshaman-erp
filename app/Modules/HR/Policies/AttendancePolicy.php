<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;

class AttendancePolicy
{
    /**
     * دسترسی پنل ادمین به حضور و غیاب: فقط ادمین کل، ادمین هلدینگ، یا حسابدار.
     */
    protected function isAdminAuthorized(User $user): bool
    {
        return $user->is_super_admin || $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    public function recordAny(User $user): bool
    {
        return $this->isAdminAuthorized($user);
    }

    /**
     * دسترسی self-service: کارمند فقط به رکورد خودش، از طریق employees.user_id === Auth::id().
     */
    public function recordSelf(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }
}
