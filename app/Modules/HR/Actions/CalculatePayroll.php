<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\EmploymentStatus;
use App\Modules\HR\Enums\ExpensePostingStatus;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Jalali;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * محاسبه حقوق یک شرکت در یک ماه شمسی.
 *
 * ⚠️ فرمول‌های بیمه، مالیات و مخرج نرخ روزانه موقت‌اند و نیازمند تأیید حسابدار
 * واقعی کارفرما — نگاه کن config/payroll.php و docs/PROJECT_02_HR.md بند ۴ «نکات حیاتی».
 *
 * تضمین «همه یا هیچ»: کل دوره (ساخت payroll_run + همه فیش‌ها) داخل یک تراکنش
 * بیرونی واحد است و هیچ try/catch داخل حلقه وجود ندارد. اگر حتی یک کارمند فعال
 * خلاصه ماهانه نداشته باشد، هیچ فیشی — حتی برای کارمندان جلوتر در ترتیب — نوشته
 * نمی‌شود. علاوه بر آن، نبود خلاصه ماهانه *قبل* از شروع نوشتن بررسی می‌شود تا
 * پیام خطا فهرست کامل کارمندان ناقص را بدهد، نه فقط اولین مورد.
 */
class CalculatePayroll
{
    /**
     * @param  string  $periodMonth  شمسی مثل '1405-04'
     */
    public function handle(Company $company, string $periodMonth, User $actor): PayrollRun
    {
        // authorize داخل خود Action، نه فقط در کامپوننت Livewire — CLAUDE.md بند ۹.
        Gate::forUser($actor)->authorize('calculate', PayrollRun::class);

        Validator::make(['period_month' => $periodMonth], [
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ])->validate();

        [$year, $month] = array_map('intval', explode('-', $periodMonth));
        $periodStart = Carbon::parse(Jalali::toGregorian($year, $month, 1));
        $periodEnd = Carbon::parse(Jalali::toGregorian($year, $month, Jalali::daysInMonth($year, $month)));

        return DB::transaction(function () use ($company, $periodMonth, $periodStart, $periodEnd, $actor) {
            $run = PayrollRun::withoutGlobalScopes()
                ->where('owner_company_id', $company->id)
                ->where('period_month', $periodMonth)
                ->lockForUpdate()
                ->first();

            // قفل مالی — CLAUDE.md بند ۵.۵. بازمحاسبه یک دوره نهایی‌شده ممنوع است؛
            // اصلاح فقط با یک دوره جدید، نه با بازنویسی تاریخ.
            if ($run?->isLocked()) {
                throw ValidationException::withMessages([
                    'period_month' => 'این دوره حقوق نهایی شده است و قابل بازمحاسبه نیست.',
                ]);
            }

            $run ??= PayrollRun::withoutGlobalScopes()->create([
                'owner_company_id' => $company->id,
                'period_month' => $periodMonth,
                'payroll_status' => PayrollStatus::Draft,
            ]);

            $employees = Employee::withoutGlobalScopes()
                ->where('owner_company_id', $company->id)
                ->where('employment_status', EmploymentStatus::Active)
                ->orderBy('full_name')
                ->get();

            $summaries = MonthlyAttendanceSummary::withoutGlobalScopes()
                ->whereIn('employee_id', $employees->pluck('id'))
                ->where('period_month', $periodMonth)
                ->get()
                ->keyBy('employee_id');

            $this->guardAgainstMissingSummaries($employees, $summaries, $periodMonth);

            $unpaidLeaveDays = $this->unpaidLeaveWorkdaysPerEmployee(
                $employees, $company->id, $periodStart, $periodEnd
            );

            foreach ($employees as $employee) {
                $this->writePayslip(
                    $run,
                    $employee,
                    $summaries->get($employee->id),
                    $unpaidLeaveDays[$employee->id] ?? 0,
                );
            }

            // فیش کارمندانی که دیگر فعال نیستند از این دوره پاک می‌شود، وگرنه یک
            // بازمحاسبه بعد از ترک خدمت، فیش کهنه را جا می‌گذارد و جمع دوره غلط می‌شود.
            Payslip::withoutGlobalScopes()
                ->where('payroll_run_id', $run->id)
                ->whereNotIn('employee_id', $employees->pluck('id'))
                ->get()
                ->each
                ->delete();

            $run->update([
                'payroll_status' => PayrollStatus::Calculated,
                'calculated_at' => now(),
                'calculated_by_user_id' => $actor->id,
            ]);

            return $run->fresh();
        });
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<string, MonthlyAttendanceSummary>  $summaries
     */
    protected function guardAgainstMissingSummaries(
        Collection $employees,
        Collection $summaries,
        string $periodMonth
    ): void {
        $missing = $employees->reject(fn (Employee $employee) => $summaries->has($employee->id));

        if ($missing->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'period_month' => sprintf(
                'کارکرد ماهانه %s برای این کارمندان محاسبه نشده است: %s. ابتدا گزارش کارکرد ماهانه همین ماه را برایشان محاسبه کنید.',
                $periodMonth,
                $missing->pluck('full_name')->implode('، '),
            ),
        ]);
    }

    /**
     * تعداد روزهای کاریِ مرخصی بدون‌حقوقِ تأییدشده هر کارمند، فقط داخل همین ماه.
     *
     * دو نکته‌ای که این متد عمداً رعایت می‌کند:
     *
     * ۱. `leaves.days_count` مستقیم استفاده نمی‌شود. آن ستون کل بازه مرخصی را
     *    می‌شمارد و یک مرخصی می‌تواند از مرز ماه عبور کند؛ استفاده خام از آن،
     *    روزهای ماه بعد را در حقوق این ماه کسر می‌کند. پس بازه با مرز ماه قطع
     *    می‌شود و روزهای کاری داخل ماه دوباره از WorkCalendar شمرده می‌شوند.
     *
     * ۲. به‌جای جمع‌زدن روزها، یک مجموعه یکتای تاریخ ساخته می‌شود. RequestLeave
     *    (Session 5) امروز جلوی هم‌پوشانی مرخصی‌ها را می‌گیرد، ولی محاسبه مالی
     *    نباید به آن نگهبان تکیه کند: اگر داده قدیمی یا دستی هم‌پوشان وجود داشته
     *    باشد، این مجموعه هر روز را فقط یک‌بار می‌شمارد و کسر مضاعف رخ نمی‌دهد.
     *
     * ۳. مرخصی ساعتی هرگز از این مسیر عبور نمی‌کند. این متد روز-محور است و یک
     *    مرخصی دو ساعته را یک **روز کامل** کسر می‌کرد. فیلتر نوع از قبل فقط
     *    Unpaid را می‌گیرد و Hourly نوع جداگانه‌ای است، ولی استثنای صریح گذاشته
     *    شده تا اگر بعداً کسی مرخصی ساعتی را بدون‌حقوق کرد، این کسر اشتباه
     *    بی‌صدا فعال نشود.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<string, int> employee_id => تعداد روز
     */
    protected function unpaidLeaveWorkdaysPerEmployee(
        Collection $employees,
        string $companyId,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        // withoutGlobalScope('owner_company') و نه withoutGlobalScopes() — دومی
        // scope حذف نرم را هم برمی‌داشت و یک مرخصی حذف‌شده از حقوق کسر می‌شد.
        $leavesByEmployee = Leave::withoutGlobalScope('owner_company')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('leave_type', LeaveType::Unpaid)
            ->where('leave_type', '!=', LeaveType::Hourly)
            ->where('leave_status', LeaveStatus::Approved)
            // whereDate چون ستون تاریخ به‌شکل کامل datetime ذخیره می‌شود؛ با
            // مقایسه رشته‌ای خام، مرخصی بدون‌حقوقی که دقیقاً روزِ آخر ماه شروع
            // می‌شد از کسر حقوق همان ماه جا می‌ماند.
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get()
            ->groupBy('employee_id');

        $calendar = app(WorkCalendar::class);
        $result = [];

        foreach ($leavesByEmployee as $employeeId => $leaves) {
            $days = [];

            foreach ($leaves as $leave) {
                $from = $leave->start_date->greaterThan($periodStart) ? $leave->start_date->copy() : $periodStart->copy();
                $to = $leave->end_date->lessThan($periodEnd) ? $leave->end_date->copy() : $periodEnd->copy();

                for ($date = $from; $date->lte($to); $date->addDay()) {
                    if ($calendar->isWorkday($date, $companyId)) {
                        $days[$date->toDateString()] = true;
                    }
                }
            }

            $result[$employeeId] = count($days);
        }

        return $result;
    }

    /**
     * یک فیش. Idempotent: updateOrCreate روی (payroll_run_id, employee_id) —
     * بازمحاسبه مقدار را بازنویسی می‌کند، نه اینکه به مقدار قبلی اضافه کند.
     */
    protected function writePayslip(
        PayrollRun $run,
        Employee $employee,
        MonthlyAttendanceSummary $summary,
        int $unpaidLeaveDays
    ): void {
        // snapshot — کپی لحظه محاسبه، نه reference زنده. CLAUDE.md بند ۵.۲.
        $gross = Money::round((string) $employee->base_salary);

        $hourlyRate = Money::divide($gross, (string) config('payroll.standard_monthly_hours'));
        $dailyRate = Money::divide($gross, (string) config('payroll.standard_monthly_days'));

        $overtime = Money::round(Money::multiply(
            Money::divide((string) $summary->total_overtime_minutes, '60'),
            $hourlyRate
        ));

        $absenceDeduction = Money::round(
            Money::multiply((string) $summary->total_absent_days, $dailyRate)
        );

        $unpaidLeaveDeduction = Money::round(
            Money::multiply((string) $unpaidLeaveDays, $dailyRate)
        );

        $benefits = Money::round((string) config('payroll.benefits_amount'));

        // ⚠️ موقت — درصد ثابت روی (ناخالص + اضافه‌کاری). config/payroll.php
        $insurance = Money::round(Money::multiply(
            Money::add($gross, $overtime),
            (string) config('payroll.insurance_employee_rate')
        ));

        $tax = $this->provisionalTax($gross, $overtime, $benefits, $insurance);

        // net از روی مبالغ *گردشده* جمع می‌شود، نه از مقادیر خام میانی — تا جمع
        // دستیِ همان اعدادی که روی فیش چاپ می‌شوند دقیقاً به خالص برسد.
        $additions = Money::add(Money::add($gross, $overtime), $benefits);
        $deductions = Money::add(
            Money::add($absenceDeduction, $unpaidLeaveDeduction),
            Money::add($insurance, $tax)
        );
        $rawNet = Money::round(Money::subtract($additions, $deductions));

        // clamp در صفر: مبلغ «قابل پرداخت» منفی بی‌معناست — کارمند به شرکت
        // بدهکار نمی‌شود. ولی عدد خام منفی دور ریخته نمی‌شود؛ در raw_net_amount
        // می‌ماند تا حسابدار بداند کدام فیش نیاز به بررسی دستی دارد.
        //
        // این وضعیت واقعی است و نه لبه‌ای نادر: مخرج نرخ روزانه ۲۲ ثابت است، در
        // حالی که یک ماه شمسی معمولاً ۲۶–۲۷ روز کاری دارد؛ پس غیبت طولانی
        // می‌تواند کسر را از حقوق پایه بیشتر کند. تا وقتی حسابدار مخرج را نهایی
        // نکرده (⚠️ فرمول موقت — config/payroll.php)، این محافظ لازم است.
        $isNegative = Money::isGreaterThan('0', $rawNet);
        $net = $isNegative ? Money::round('0') : $rawNet;

        Payslip::withoutGlobalScopes()->updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            [
                'owner_company_id' => $employee->owner_company_id,
                'gross_salary_amount' => $gross,
                'overtime_amount' => $overtime,
                'absence_deduction_amount' => $absenceDeduction,
                'unpaid_leave_deduction_amount' => $unpaidLeaveDeduction,
                'insurance_amount' => $insurance,
                'tax_amount' => $tax,
                'benefits_amount' => $benefits,
                'net_amount' => $net,
                // NULL وقتی clamp رخ نداده — تا «نیاز به بررسی» با یک شرط ساده
                // روی همین ستون قابل تشخیص باشد، نه با مقایسه دو مبلغ.
                'raw_net_amount' => $isNegative ? $rawNet : null,
                'currency_id' => $employee->currency_id,
                // TODO: اتصال به Finance/Expenses — نگاه کن BACKLOG.md #1
                'expense_posting_status' => ExpensePostingStatus::Pending,
            ]
        );
    }

    /**
     * ⚠️ فرمول موقت — مالیات حقوق.
     *
     * پلکان واقعی قانون مالیات‌های مستقیم چند نرخی است؛ این نسخه فقط یک سقف
     * معافیت و یک نرخ ثابت روی مازاد دارد تا ساختار فیش کامل باشد. دقیق‌سازی
     * بعد از تأیید حسابدار واقعی کارفرما — config/payroll.php.
     */
    protected function provisionalTax(string $gross, string $overtime, string $benefits, string $insurance): string
    {
        $taxable = Money::subtract(Money::add(Money::add($gross, $overtime), $benefits), $insurance);
        $exemption = (string) config('payroll.tax.monthly_exemption_amount');

        if (! Money::isGreaterThan($taxable, $exemption)) {
            return Money::round('0');
        }

        return Money::round(Money::multiply(
            Money::subtract($taxable, $exemption),
            (string) config('payroll.tax.flat_rate_above_exemption')
        ));
    }
}
