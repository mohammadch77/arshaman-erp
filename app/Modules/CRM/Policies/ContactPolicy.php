<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;

/**
 * دسترسی سطح هلدینگ (نمای ۳۶۰ مخاطب) — Policy جدا از ContactSiteProfilePolicy
 * چون دامنه سؤال دسترسی متفاوت است (چندشرکتی هم‌زمان، نه فقط شرکت فعال
 * سوییچر)، اما مجموعه نقش مجاز باید همان استدلال ContactSiteProfilePolicy
 * را داشته باشد: Contact کار عملیاتی/فروش است، نه حسابداری — accountant با
 * Party (طرف‌حساب مالی) سروکار دارد، نه Contact. پس همان holding_admin/operator،
 * بدون accountant/viewer.
 */
class ContactPolicy
{
    protected function isAuthorized(User $user): bool
    {
        return $user->hasRole('holding_admin') || $user->hasRole('operator');
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
