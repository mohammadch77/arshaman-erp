<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;

class UserPolicy
{
    /**
     * دسترسی به بخش مدیریت کاربران: فقط ادمین کل یا ادمین هلدینگ.
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط holding_admin را بررسی می‌کنیم.
     */
    protected function isAdmin(User $user): bool
    {
        return $user->is_super_admin || $user->hasRole('holding_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->isAdmin($user);
    }

    public function assignRole(User $user): bool
    {
        return $this->isAdmin($user);
    }
}
