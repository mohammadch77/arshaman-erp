<?php

namespace App\Modules\SiteBuilder\Policies;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Models\SiteSetting;

class SiteSettingPolicy
{
    /**
     * شرکت از خودِ $siteSetting خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹ CLAUDE.md).
     * همان دو نقش مدیریتی PagePolicy (تنظیمات سایت مکمل صفحات است، نه یک دامنه دسترسی جدا).
     */
    public function update(User $user, SiteSetting $siteSetting): bool
    {
        return $user->hasRoleInCompany($siteSetting->owner_company_id, ['holding_admin', 'operator']);
    }
}
