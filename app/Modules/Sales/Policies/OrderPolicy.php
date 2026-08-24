<?php

namespace App\Modules\Sales\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Sales\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->is_super_admin || $user->hasRole('holding_admin')) {
            return true;
        }

        return $user->hasRoleInCompany($order->owner_company_id);
    }

    /**
     * ساخت سفارش دستی — همان سه نقش عملیاتی/مالی ProductPolicy/StockPolicy::manage.
     * $companyId اختیاری: اگر Action شرکت هدف واقعی را می‌داند همان را صریح
     * پاس می‌دهد، وگرنه شرکت فعال سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        $companyId ??= app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant', 'operator']);
    }
}
