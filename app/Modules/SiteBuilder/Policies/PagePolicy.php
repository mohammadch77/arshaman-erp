<?php

namespace App\Modules\SiteBuilder\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Models\Page;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $page خوانده می‌شود، نه از CompanyContext فعال — دفاع‌درعمق (بند ۹ CLAUDE.md).
     */
    public function view(User $user, Page $page): bool
    {
        return $user->hasRoleInCompany($page->owner_company_id);
    }

    public function create(User $user, ?string $companyId = null): bool
    {
        $companyId ??= app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    /**
     * holding_admin هر صفحه‌ای را ویرایش می‌کند. operator هر صفحه‌ی draft را —
     * برخلاف BlogPost، Page نویسنده مستقل ندارد، پس محدود به «خودش» بی‌معناست.
     */
    public function update(User $user, Page $page): bool
    {
        if ($user->hasRoleInCompany($page->owner_company_id, 'holding_admin')) {
            return true;
        }

        if (! $user->hasRoleInCompany($page->owner_company_id, 'operator')) {
            return false;
        }

        return $page->page_status === PageStatus::Draft;
    }

    /**
     * تنها holding_admin مجاز است page_status را به published تغییر دهد.
     * متد جدا (نه بخشی از update) چون هم در فرم (فعال/غیرفعال‌کردن فیلد) و هم در Action لازم است.
     */
    public function canPublish(User $user, string $companyId): bool
    {
        return $user->hasRoleInCompany($companyId, 'holding_admin');
    }

    /**
     * طبق docs/DECISIONS.md: برخلاف بقیه فیلدها، operator اجازه دارد
     * extra_css/extra_js را حتی روی صفحه‌ی published هم ویرایش کند —
     * این دو فیلد از قید draft-only متد update() مستثنی‌اند.
     */
    public function canEditExtraCode(User $user, string $companyId): bool
    {
        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }
}
