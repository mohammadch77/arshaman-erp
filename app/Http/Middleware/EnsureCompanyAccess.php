<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Services\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    /**
     * تضمین می‌کند کاربر فقط به شرکتی دسترسی دارد که در user_company_roles نقش دارد.
     * شرکت هدف: پارامتر route به نام «company» (مدل یا شناسه) یا در نبود آن، شرکت فعال session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeCompany = $request->route('company');

        $companyId = match (true) {
            $routeCompany instanceof Company => $routeCompany->id,
            is_string($routeCompany) => $routeCompany,
            default => app(CompanyContext::class)->id(),
        };

        if (! $companyId || $request->user()?->cannot('access-company', $companyId)) {
            abort(403, 'شما به این شرکت دسترسی ندارید.');
        }

        return $next($request);
    }
}
