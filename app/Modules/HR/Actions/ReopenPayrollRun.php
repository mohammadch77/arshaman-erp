<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * بازگشایی یک دوره حقوقِ نهایی‌شده.
 *
 * این **تنها** مسیر مجاز برای برداشتن قفل مالی است. ویرایش مستقیم فیش قفل‌شده
 * همچنان ممنوع می‌ماند (نگهبان `updating`/`deleting` روی مدل Payslip) — بند ۵.۵
 * CLAUDE.md می‌گوید سند posted غیرقابل ویرایش است و اصلاح باید مسیر صریح خودش
 * را داشته باشد، نه دست‌بردن مخفیانه در داده.
 *
 * چرخه اصلاح یک دوره:
 *   finalized ──reopen(دلیل اجباری)──► draft ──calculate──► calculated ──finalize──► finalized
 *
 * وضعیت به draft برمی‌گردد و نه calculated، چون بعد از بازگشایی مبالغ فیش‌ها
 * دیگر لزوماً با داده روز نمی‌خوانند؛ باید عمداً دوباره محاسبه شوند. همین باعث
 * می‌شود FinalizePayrollRun (که فقط از calculated قبول می‌کند) نتواند یک دوره
 * بازگشایی‌شده را بدون محاسبه دوباره قفل کند.
 */
class ReopenPayrollRun
{
    /**
     * حداقل طول دلیل — یک کاراکتر یا «ok» عملاً یعنی بدون دلیل، و کل ارزش
     * ردگیری این عملیات به همین متن است.
     */
    protected const MIN_REASON_LENGTH = 10;

    public function handle(PayrollRun $run, string $reason, User $actor): PayrollRun
    {
        // authorize داخل خود Action — بند ۹ CLAUDE.md.
        Gate::forUser($actor)->authorize('reopen', PayrollRun::class);

        $reason = trim($reason);

        Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:'.self::MIN_REASON_LENGTH],
        ], [
            'reason.required' => 'برای بازگشایی یک دوره نهایی‌شده، ثبت دلیل الزامی است.',
            'reason.min' => 'دلیل بازگشایی باید روشن و قابل‌فهم باشد (حداقل :min کاراکتر).',
        ])->validate();

        if ($run->payroll_status !== PayrollStatus::Finalized) {
            throw ValidationException::withMessages([
                'payroll_status' => 'فقط دوره‌ای که نهایی شده باشد قابل بازگشایی است.',
            ]);
        }

        return DB::transaction(function () use ($run, $reason, $actor) {
            $previousStatus = $run->payroll_status->value;

            $run->update([
                'payroll_status' => PayrollStatus::Draft,
                // مهرهای نهایی‌سازی پاک می‌شوند چون دوره دیگر نهایی نیست؛ تاریخچه
                // اینکه یک‌بار نهایی شده بود در activity_log زیر می‌ماند.
                'finalized_at' => null,
                'finalized_by_user_id' => null,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($run)
                ->withProperties([
                    'owner_company_id' => $run->owner_company_id,
                    'period_month' => $run->period_month,
                    'previous_status' => $previousStatus,
                    'reason' => $reason,
                ])
                ->log('بازگشایی دوره حقوق');

            return $run->fresh();
        });
    }
}
