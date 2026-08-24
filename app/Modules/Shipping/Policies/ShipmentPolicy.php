<?php

namespace App\Modules\Shipping\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Shipping\Models\Shipment;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->is_super_admin || $user->hasRole('holding_admin')) {
            return true;
        }

        return $user->hasRoleInCompany($shipment->owner_company_id);
    }

    /**
     * بسته‌بندی/ثبت کد رهگیری/تحویل — همان سه نقش عملیاتی/مالی OrderPolicy::create.
     * $companyId اختیاری: اگر Action شرکت هدف واقعی را می‌داند همان را صریح
     * پاس می‌دهد، وگرنه شرکت فعال سوییچر پیش‌فرض است.
     */
    public function manage(User $user, ?string $companyId = null): bool
    {
        $companyId ??= app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant', 'operator']);
    }
}
