<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;

class ExchangeRatePolicy
{
    /**
     * مشاهده نرخ ارز برای همه نقش‌ها آزاد است — فقط ثبت نرخ محدود می‌شود.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }
}
