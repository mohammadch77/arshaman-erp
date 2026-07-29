<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;

class EmployeePolicy
{
    /**
     * دسترسی به بخش پرسنل: فقط ادمین کل، ادمین هلدینگ، یا حسابدار.
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط دو نقش دیگر را بررسی می‌کنیم.
     */
    protected function isAuthorized(User $user): bool
    {
        return $user->is_super_admin || $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user);
    }

    public function create(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user);
    }

    public function terminate(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user);
    }

    public function link(User $user, Employee $employee): bool
    {
        return $this->isAuthorized($user);
    }
}
