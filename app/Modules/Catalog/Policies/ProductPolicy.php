<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;

class ProductPolicy
{
    /**
     * ادمین کل با Gate::before پیش‌تر تأیید می‌شود؛ اینجا فقط نقش‌های دیگر را بررسی می‌کنیم.
     * ساخت/ویرایش محصول فقط برای نقش‌های عملیاتی/مالی، طبق الگوی PartyPolicy.
     */
    protected function canManage(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant', 'operator']);
    }

    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $product خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹).
     */
    public function view(User $user, Product $product): bool
    {
        return $user->hasRoleInCompany($product->owner_company_id);
    }

    /**
     * بدون نمونه (هنوز ساخته نشده). $companyId اختیاری: اگر Action شرکت هدف
     * واقعی را می‌داند همان را صریح پاس می‌دهد؛ در غیر این صورت (مثلاً pre-check
     * لایه Livewire) شرکت فعال سوییچر پیش‌فرض است.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->canManage($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canManage($user, $product->owner_company_id);
    }
}
