<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CompanyContext
{
    protected const SESSION_KEY = 'active_company_id';

    /**
     * مقدار ویژه session برای «نمای تجمیعی هلدینگ» — فقط ادمین کل.
     */
    protected const AGGREGATE_MARKER = '__holding_aggregate__';

    /**
     * شناسه شرکت فعال session جاری، اگر کاربر یک نقش داشته باشد به‌طور پیش‌فرض همان.
     * در «نمای تجمیعی هلدینگ» مقدار null برمی‌گرداند.
     */
    public function id(): ?string
    {
        if (session()->has(self::SESSION_KEY)) {
            $value = session(self::SESSION_KEY);

            return $value === self::AGGREGATE_MARKER ? null : $value;
        }

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $firstCompanyId = $user->companyRoles()->value('owner_company_id');

        if ($firstCompanyId) {
            $this->set($firstCompanyId);
        }

        return $firstCompanyId;
    }

    /**
     * آیا کاربر در حالت «نمای تجمیعی هلدینگ» است.
     */
    public function isAggregateView(): bool
    {
        return session(self::SESSION_KEY) === self::AGGREGATE_MARKER;
    }

    /**
     * شرکت فعال را عوض می‌کند؛ فقط اگر کاربر در آن شرکت نقش داشته باشد یا ادمین کل باشد.
     *
     * @throws AuthorizationException
     */
    public function set(string $companyId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasRoleInCompany($companyId)) {
            throw new AuthorizationException('کاربر در این شرکت نقشی ندارد.');
        }

        session([self::SESSION_KEY => $companyId]);
    }

    /**
     * فعال‌کردن «نمای تجمیعی هلدینگ»؛ فقط ادمین کل.
     *
     * @throws AuthorizationException
     */
    public function setAggregate(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->is_super_admin) {
            throw new AuthorizationException('فقط ادمین کل به نمای تجمیعی هلدینگ دسترسی دارد.');
        }

        session([self::SESSION_KEY => self::AGGREGATE_MARKER]);
    }

    /**
     * مدل شرکت فعال، یا null در نمای تجمیعی هلدینگ.
     */
    public function activeCompany(): ?Company
    {
        $id = $this->id();

        return $id ? Company::find($id) : null;
    }
}
