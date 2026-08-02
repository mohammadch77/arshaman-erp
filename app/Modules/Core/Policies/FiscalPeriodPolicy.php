<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\FiscalPeriod;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class FiscalPeriodPolicy
{
    /**
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط نقش‌های دیگر را بررسی می‌کنیم.
     */
    protected function canManage(User $user): bool
    {
        return $user->hasRole('holding_admin');
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function close(User $user, FiscalPeriod $fiscalPeriod): bool
    {
        return $this->canManage($user);
    }
}
