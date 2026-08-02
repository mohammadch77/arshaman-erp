<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;

/**
 * دسترسی سطح هلدینگ (نمای ۳۶۰ مخاطب) — عمداً جدا و محدودتر از
 * ContactSiteProfilePolicy: اینجا داده چند شرکت هم‌زمان کنار هم دیده می‌شود
 * (نام/موبایل/ایمیل/جمع‌مبلغ هر سایت)، پس فقط نقش‌های سطح هلدینگ مجازند —
 * operator یک شرکت نباید سابقه مخاطب در شرکت‌های دیگر را ببیند.
 */
class ContactPolicy
{
    protected function isAuthorized(User $user): bool
    {
        return $user->hasRole('holding_admin') || $user->hasRole('accountant');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorized($user);
    }

    public function view(User $user): bool
    {
        return $this->isAuthorized($user);
    }
}
