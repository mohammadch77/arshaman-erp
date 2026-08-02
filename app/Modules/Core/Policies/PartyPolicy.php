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
    protected function canManage(User $user): bool
    {
        return $user->hasRole('holding_admin') || $user->hasRole('accountant') || $user->hasRole('operator');
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, Party $party): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Party $party): bool
    {
        return $this->canManage($user);
    }
}
