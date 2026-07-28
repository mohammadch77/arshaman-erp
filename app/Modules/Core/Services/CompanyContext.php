<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CompanyContext
{
    protected const SESSION_KEY = 'active_company_id';

    /**
     * شناسه شرکت فعال session جاری، اگر کاربر یک نقش داشته باشد به‌طور پیش‌فرض همان.
     */
    public function id(): ?string
    {
        if (session()->has(self::SESSION_KEY)) {
            return session(self::SESSION_KEY);
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
}
