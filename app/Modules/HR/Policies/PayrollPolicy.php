<?php

namespace App\Modules\HR\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Payslip;

class PayrollPolicy
{
    /**
     * دسترسی پنل ادمین به حقوق و دستمزد: فقط ادمین کل، ادمین هلدینگ، یا
     * حسابدار — و دقیقاً در همان شرکت هدف. همان الگوی LeavePolicy::isAdminAuthorized.
     */
    protected function isAdminAuthorized(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'accountant']);
    }

    /**
     * هم پنل ادمین شرکت‌محور (PayrollIndex) و هم گزارش هلدینگ‌محور
     * (PayrollExpenseReport) از همین متد عبور می‌کنند — طبق تصمیم Session 7:
     * «دسترسی همان PayrollPolicy::viewAny پنل ادمین حقوق است، رل جدیدی
     * تعریف نشد». شرط ورود یکسان می‌ماند (accountant/holding_admin در شرکت
     * فعال)؛ گزارش خودش بعد از این گیت با withoutGlobalScopes() همه شرکت‌ها
     * را تجمیع می‌کند.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminAuthorized($user, app(CompanyContext::class)->id());
    }

    /**
     * بدون نمونه (دوره هنوز محاسبه نشده). $companyId اختیاری: CalculatePayroll
     * شرکت هدف واقعی ($company->id) را صریح پاس می‌دهد.
     */
    public function calculate(User $user, ?string $companyId = null): bool
    {
        return $this->isAdminAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }

    /**
     * $companyId اختیاری: FinalizePayrollRun شرکت هدف واقعی
     * ($run->owner_company_id) را صریح پاس می‌دهد.
     */
    public function finalize(User $user, ?string $companyId = null): bool
    {
        return $this->isAdminAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }

    /**
     * بازگشایی یک دوره نهایی‌شده — همان سطح دسترسی نهایی‌کردن.
     *
     * عمداً متد جدا از finalize است (نه استفاده دوباره از همان) تا اگر بعداً
     * کارفرما تصمیم گرفت بازگشایی فقط کار holding_admin باشد و نه حسابدار،
     * تغییرش یک خط اینجا باشد، نه بازنویسی گارد در Action. $companyId
     * اختیاری: ReopenPayrollRun شرکت هدف واقعی ($run->owner_company_id) را
     * صریح پاس می‌دهد.
     */
    public function reopen(User $user, ?string $companyId = null): bool
    {
        return $this->isAdminAuthorized($user, $companyId ?? app(CompanyContext::class)->id());
    }

    /**
     * دسترسی self-service به یک فیش مشخص.
     *
     * دو شرط هم‌زمان — هیچ‌کدام کافی نیست:
     * ۱. فیش متعلق به پرونده پرسنلی خودِ همین کاربر باشد (employees.user_id).
     * ۲. دوره حقوق نهایی شده باشد — کارمند نباید فیش draft/calculated را ببیند،
     *    چون تا لحظه finalize مبالغ می‌توانند با بازمحاسبه عوض شوند.
     *
     * این متد عمداً به نقش کاری ندارد: یک holding_admin هم اگر فیش کارمند دیگری
     * را از این مسیر بخواهد، رد می‌شود. مسیر ادمین viewAny است، نه این.
     */
    public function viewOwn(User $user, Payslip $payslip): bool
    {
        $employee = $payslip->employee()->withoutGlobalScopes()->first();

        if ($employee?->user_id === null || $employee->user_id !== $user->id) {
            return false;
        }

        return $payslip->payrollRun()->withoutGlobalScopes()->first()?->isLocked() === true;
    }
}
