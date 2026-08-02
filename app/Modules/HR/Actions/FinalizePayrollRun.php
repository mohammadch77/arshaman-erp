<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * قفل مالی یک دوره حقوق.
 *
 * بعد از این، نه بازمحاسبه ممکن است (CalculatePayroll رد می‌کند) و نه ویرایش
 * مستقیم فیش‌ها (نگهبان مدل Payslip رد می‌کند) — دو لایه، چون Action تنها
 * caller نیست. CLAUDE.md بند ۵.۵ و بند ۹.
 */
class FinalizePayrollRun
{
    public function handle(PayrollRun $run, User $actor): PayrollRun
    {
        Gate::forUser($actor)->authorize('finalize', [PayrollRun::class, $run->owner_company_id]);

        // فقط از وضعیت «محاسبه‌شده» — یک دوره draft خالی نباید نهایی شود،
        // وگرنه ماهی بدون هیچ فیشی برای همیشه قفل می‌ماند.
        if ($run->payroll_status !== PayrollStatus::Calculated) {
            throw ValidationException::withMessages([
                'payroll_status' => $run->isLocked()
                    ? 'این دوره حقوق قبلاً نهایی شده است.'
                    : 'فقط دوره‌ای که محاسبه شده باشد قابل نهایی‌کردن است.',
            ]);
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->update([
                'payroll_status' => PayrollStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by_user_id' => $actor->id,
            ]);

            return $run->fresh();
        });
    }
}
