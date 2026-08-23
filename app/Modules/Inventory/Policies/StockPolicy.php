<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Inventory\Models\Stock;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * holding_admin/is_super_admin با hasRole سراسری (نه hasRoleInCompany مقید به
     * owner_company_id همان Stock) اجازه می‌گیرند — دقیقاً همان استثنای مستند
     * WarehouseIndex::getStocksByWarehouseProperty() برای همین مدل Stock (بند ۵.۸/۱۱
     * CLAUDE.md: انبار بین‌شرکتی است، هلدینگ‌ادمین باید بتواند دفترچه حرکت هر شرکتی
     * را از پنل انبار مشترک ببیند).
     */
    public function view(User $user, Stock $stock): bool
    {
        if ($user->is_super_admin || $user->hasRole('holding_admin')) {
            return true;
        }

        return $user->hasRoleInCompany($stock->owner_company_id);
    }

    /**
     * دریافت/خروج کالا — همان سه نقش عملیاتی/مالی ProductPolicy::canManage.
     * $companyId اختیاری: اگر Action شرکت هدف واقعی را می‌داند همان را صریح
     * پاس می‌دهد؛ در غیر این صورت شرکت فعال سوییچر پیش‌فرض است.
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
