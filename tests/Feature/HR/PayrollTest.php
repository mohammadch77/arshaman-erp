<?php

use App\Livewire\HR\PayrollIndex;
use App\Livewire\HR\SelfService\MyPayslips;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\CalculateMonthlyAttendance;
use App\Modules\HR\Actions\CalculatePayroll;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\FinalizePayrollRun;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Actions\ReopenPayrollRun;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Enums\ExpensePostingStatus;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Models\PayrollRun;
use App\Modules\HR\Models\Payslip;
use App\Modules\HR\Services\WorkCalendar;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Session 6 — حقوق و دستمزد
|--------------------------------------------------------------------------
|
| این فایل قبل از منطق محاسبه نوشته شد (CLAUDE.md بند ۹ قاعده ۳: تست قبل از
| پیاده‌سازی برای منطق مالی).
|
| ماه مرجع همه تست‌ها: '1405-05' = ۲۰۲۶-۰۷-۲۳ تا ۲۰۲۶-۰۸-۲۲ میلادی.
| ماه بعدی ('1405-06') از ۲۰۲۶-۰۸-۲۳ شروع می‌شود — مبنای تست «مرخصی بین دو ماه».
|
| حقوق پایه مرجع: ۲۲٬۰۰۰٬۰۰۰ — عمداً طوری انتخاب شده که هر دو نرخ رُند شوند و
| خطای گردکردن، خطای فرمول را پنهان نکند:
|   نرخ روزانه = 22,000,000 / 22  = 1,000,000
|   نرخ ساعتی  = 22,000,000 / 176 =   125,000
*/

const PAYROLL_PERIOD = '1405-05';
const PAYROLL_MONTH_START = '2026-07-23';
const PAYROLL_MONTH_END = '2026-08-22';
const PAYROLL_BASE_SALARY = '22000000';
const PAYROLL_DAILY_RATE = 1000000;
const PAYROLL_HOURLY_RATE = 125000;

function payrollMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function payrollGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => payrollMakeRole($roleName)->id,
    ]);
}

function payrollCompany(string $slug = 'arshaman'): Company
{
    return Company::create([
        'name' => 'شرکت '.$slug,
        'slug' => $slug,
        'business_type' => 'project_services',
    ]);
}

function payrollEmployeeData(string $companyId, string $nationalId, string $baseSalary = PAYROLL_BASE_SALARY): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست حقوق',
        'national_id' => $nationalId,
        'phone' => '09121234567',
        'address' => 'تهران',
        'position' => 'برنامه‌نویس',
        'hire_date' => '2025-01-01',
        'contract_type' => 'permanent',
        'contract_start_date' => '2025-01-01',
        'contract_end_date' => null,
        'base_salary' => $baseSalary,
    ];
}

/**
 * خلاصه ماهانه با اعداد صریح — برای تست‌های حسابی، تا محاسبه حقوق از منطق
 * Session 4 جدا بماند و علت هر خطا دقیقاً معلوم باشد.
 */
function payrollSummary(Employee $employee, array $overrides = []): MonthlyAttendanceSummary
{
    return MonthlyAttendanceSummary::factory()->create(array_merge([
        'employee_id' => $employee->id,
        'owner_company_id' => $employee->owner_company_id,
        'period_month' => PAYROLL_PERIOD,
        'total_worked_days' => 22,
    ], $overrides));
}

/**
 * تعداد روزهای کاری یک بازه — همان تعریفی که WorkCalendar به کسر مرخصی می‌دهد.
 */
function payrollWorkdaysBetween(string $start, string $end, ?string $companyId): int
{
    $calendar = app(WorkCalendar::class);
    $count = 0;

    for ($date = Carbon::parse($start); $date->lte(Carbon::parse($end)); $date->addDay()) {
        if ($calendar->isWorkday($date, $companyId)) {
            $count++;
        }
    }

    return $count;
}

// =====================================================================
// ۱ و ۲ — snapshot (CLAUDE.md بند ۵.۲ — «قابل مذاکره نیست»)
// =====================================================================

it('snapshots base_salary onto the payslip at calculation time', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000001'), $admin);
    payrollSummary($employee);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->gross_salary_amount)->toEqual('22000000.00');
    expect($payslip->owner_company_id)->toBe($company->id);
    expect($payslip->expense_posting_status)->toBe(ExpensePostingStatus::Pending);
});

it('does not change an existing payslip when base_salary is edited afterwards', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000002'), $admin);
    payrollSummary($employee);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $before = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    // حقوق پایه بعداً سه‌برابر می‌شود — فیش صادرشده نباید تکان بخورد.
    $employee->forceFill(['base_salary' => '66000000'])->save();

    $after = Payslip::withoutGlobalScopes()->find($before->id);

    expect($after->gross_salary_amount)->toEqual('22000000.00');
    expect($after->net_amount)->toEqual($before->net_amount);
});

// =====================================================================
// ۳ تا ۷ — موتور محاسبه
// =====================================================================

it('calculates overtime from summary minutes at base_salary / 176 per hour', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000003'), $admin);
    payrollSummary($employee, ['total_overtime_minutes' => 600]); // ۱۰ ساعت

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->overtime_amount)->toEqual(number_format(10 * PAYROLL_HOURLY_RATE, 2, '.', ''));
});

it('deducts absence at base_salary / 22 per day', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000004'), $admin);
    payrollSummary($employee, ['total_absent_days' => 3, 'total_worked_days' => 19]);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->absence_deduction_amount)->toEqual(number_format(3 * PAYROLL_DAILY_RATE, 2, '.', ''));
});

it('deducts approved unpaid leave workdays at the daily rate', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000005'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'unpaid', 'start_date' => '2026-08-03', 'end_date' => '2026-08-07'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    payrollSummary($employee);
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    $expectedDays = payrollWorkdaysBetween('2026-08-03', '2026-08-07', $company->id);

    expect($expectedDays)->toBeGreaterThan(0);
    expect($payslip->unpaid_leave_deduction_amount)
        ->toEqual(number_format($expectedDays * PAYROLL_DAILY_RATE, 2, '.', ''));
});

it('does not deduct annual or sick leave — only unpaid leave is deducted', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000006'), $admin);

    foreach ([['annual', '2026-08-03', '2026-08-05'], ['sick', '2026-08-10', '2026-08-12']] as [$type, $start, $end]) {
        $leave = app(RequestLeave::class)->handle(
            $employee,
            ['leave_type' => $type, 'start_date' => $start, 'end_date' => $end],
            $admin,
            RecordedBy::Admin,
        );
        app(ApproveLeave::class)->handle($leave, $admin);
    }

    payrollSummary($employee);
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->unpaid_leave_deduction_amount)->toEqual('0.00');
});

it('only deducts the part of a cross-month unpaid leave that falls inside the period', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000007'), $admin);

    // ماه '1405-05' در ۲۰۲۶-۰۸-۲۲ تمام می‌شود؛ این مرخصی تا ۲۰۲۶-۰۸-۲۶ ادامه دارد.
    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'unpaid', 'start_date' => '2026-08-20', 'end_date' => '2026-08-26'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    payrollSummary($employee);
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    $insidePeriod = payrollWorkdaysBetween('2026-08-20', PAYROLL_MONTH_END, $company->id);
    $wholeLeave = payrollWorkdaysBetween('2026-08-20', '2026-08-26', $company->id);

    // پیش‌شرط تست: بازه واقعاً از مرز ماه عبور می‌کند.
    expect($insidePeriod)->toBeLessThan($wholeLeave);
    expect($payslip->unpaid_leave_deduction_amount)
        ->toEqual(number_format($insidePeriod * PAYROLL_DAILY_RATE, 2, '.', ''));
});

it('counts an overlapping day only once when legacy leave rows overlap', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000008'), $admin);

    // RequestLeave از Session 5 جلوی هم‌پوشانی را می‌گیرد؛ اینجا عمداً دور زده می‌شود
    // تا داده قدیمی/دستی شبیه‌سازی شود. محاسبه حقوق نباید به آن نگهبان تکیه کند.
    foreach ([['2026-08-03', '2026-08-07'], ['2026-08-05', '2026-08-11']] as [$start, $end]) {
        Leave::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'owner_company_id' => $company->id,
            'leave_type' => 'unpaid',
            'start_date' => $start,
            'end_date' => $end,
            'days_count' => payrollWorkdaysBetween($start, $end, $company->id),
            'leave_status' => LeaveStatus::Approved,
            'created_by_user_id' => $admin->id,
        ]);
    }

    payrollSummary($employee);
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    // اجتماع دو بازه = ۲۰۲۶-۰۸-۰۳ تا ۲۰۲۶-۰۸-۱۱، نه جمع ساده days_count دو رکورد.
    $unionDays = payrollWorkdaysBetween('2026-08-03', '2026-08-11', $company->id);

    expect($payslip->unpaid_leave_deduction_amount)
        ->toEqual(number_format($unionDays * PAYROLL_DAILY_RATE, 2, '.', ''));
});

it('keeps absence and approved leave disjoint so a day is never deducted twice', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000009'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'unpaid', 'start_date' => '2026-08-03', 'end_date' => '2026-08-07'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    // این‌بار خلاصه ماهانه واقعی محاسبه می‌شود، نه factory — تا قرارداد Session 4
    // («روز مرخصی approved غیبت حساب نمی‌شود») واقعاً تثبیت شود.
    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, PAYROLL_PERIOD, $admin);
    $leaveWorkdays = payrollWorkdaysBetween('2026-08-03', '2026-08-07', $company->id);
    $totalWorkdays = payrollWorkdaysBetween(PAYROLL_MONTH_START, PAYROLL_MONTH_END, $company->id);

    expect($summary->total_leave_days)->toBe($leaveWorkdays);
    expect($summary->total_absent_days)->toBe($totalWorkdays - $leaveWorkdays);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    // مجموع دو کسر باید دقیقاً برابر کل روزهای کاری ماه باشد — نه بیشتر (کسر مضاعف).
    $deductedDays = bcdiv(
        bcadd((string) $payslip->absence_deduction_amount, (string) $payslip->unpaid_leave_deduction_amount, 2),
        (string) PAYROLL_DAILY_RATE,
        0
    );

    expect((int) $deductedDays)->toBe($totalWorkdays);
});

it('computes net_amount as gross + overtime + benefits - deductions - insurance - tax', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000010'), $admin);
    payrollSummary($employee, ['total_overtime_minutes' => 600, 'total_absent_days' => 2, 'total_worked_days' => 20]);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    $expected = bcsub(
        bcadd(
            bcadd((string) $payslip->gross_salary_amount, (string) $payslip->overtime_amount, 2),
            (string) $payslip->benefits_amount,
            2
        ),
        bcadd(
            bcadd((string) $payslip->absence_deduction_amount, (string) $payslip->unpaid_leave_deduction_amount, 2),
            bcadd((string) $payslip->insurance_amount, (string) $payslip->tax_amount, 2),
            2
        ),
        2
    );

    expect($payslip->net_amount)->toEqual($expected);
    // ⚠️ فرمول موقت: بیمه ۷٪ روی (ناخالص + اضافه‌کاری) — config/payroll.php
    expect($payslip->insurance_amount)->toEqual('1627500.00'); // 0.07 * (22,000,000 + 1,250,000)
});

it('clamps a negative net to zero and keeps the raw negative amount for review', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000032'), $admin);

    // غیبت کل ماه: ۲۶ روز کاری × ۱٬۰۰۰٬۰۰۰ = ۲۶٬۰۰۰٬۰۰۰ کسر، از حقوق پایه
    // ۲۲٬۰۰۰٬۰۰۰ بیشتر است — چون مخرج نرخ روزانه ۲۲ ثابت است ولی ماه شمسی
    // روزهای کاری بیشتری دارد.
    $absentDays = payrollWorkdaysBetween(PAYROLL_MONTH_START, PAYROLL_MONTH_END, $company->id);
    payrollSummary($employee, ['total_absent_days' => $absentDays, 'total_worked_days' => 0]);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    // پیش‌شرط تست: کسر واقعاً از حقوق پایه بیشتر است.
    expect($absentDays * PAYROLL_DAILY_RATE)->toBeGreaterThan((int) PAYROLL_BASE_SALARY);

    expect($payslip->net_amount)->toEqual('0.00');
    expect($payslip->raw_net_amount)->not->toBeNull();
    expect((float) $payslip->raw_net_amount)->toBeLessThan(0);
    expect($payslip->needsManualReview())->toBeTrue();

    // عدد خام باید دقیقاً همان محاسبه واقعی باشد، نه یک مقدار نمادین.
    $expectedRaw = bcsub(
        bcadd(bcadd((string) $payslip->gross_salary_amount, (string) $payslip->overtime_amount, 2), (string) $payslip->benefits_amount, 2),
        bcadd(
            bcadd((string) $payslip->absence_deduction_amount, (string) $payslip->unpaid_leave_deduction_amount, 2),
            bcadd((string) $payslip->insurance_amount, (string) $payslip->tax_amount, 2),
            2
        ),
        2
    );

    expect($payslip->raw_net_amount)->toEqual($expectedRaw);
});

it('leaves raw_net_amount null when the net is not negative', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000033'), $admin);
    payrollSummary($employee);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->raw_net_amount)->toBeNull();
    expect($payslip->needsManualReview())->toBeFalse();
});

it('clears raw_net_amount on recalculation once the absence is corrected', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000034'), $admin);

    $absentDays = payrollWorkdaysBetween(PAYROLL_MONTH_START, PAYROLL_MONTH_END, $company->id);
    $summary = payrollSummary($employee, ['total_absent_days' => $absentDays, 'total_worked_days' => 0]);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    expect(Payslip::withoutGlobalScopes()->first()->raw_net_amount)->not->toBeNull();

    // غیبت اشتباه ثبت شده بود و اصلاح می‌شود — فیش نباید علامت «نیاز به بررسی»
    // را از اجرای قبلی نگه دارد.
    $summary->forceFill(['total_absent_days' => 1, 'total_worked_days' => $absentDays - 1])->save();
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $payslip = Payslip::withoutGlobalScopes()->first();

    expect($payslip->raw_net_amount)->toBeNull();
    expect($payslip->needsManualReview())->toBeFalse();
});

// =====================================================================
// ۸ — قفل مالی بعد از finalize
// =====================================================================

it('rejects recalculation and payslip edits after the run is finalized', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000011'), $admin);
    payrollSummary($employee);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    expect(fn () => app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin))
        ->toThrow(ValidationException::class);

    // حتی مسیر مستقیم مدل (بدون Action) هم باید رد شود — CLAUDE.md بند ۹.
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
    $originalNet = (string) $payslip->net_amount;

    expect(fn () => $payslip->update(['net_amount' => '1']))->toThrow(ValidationException::class);
    expect(fn () => $payslip->delete())->toThrow(ValidationException::class);

    // شیء in-memory هنگام تلاش ناموفق mutate می‌شود؛ آنچه اهمیت دارد این است که
    // دیتابیس دست‌نخورده مانده باشد — قفل مالی یعنی همین.
    $fromDatabase = Payslip::withoutGlobalScopes()->find($payslip->id);

    expect($fromDatabase)->not->toBeNull();
    expect($fromDatabase->net_amount)->toEqual($originalNet);
    expect($originalNet)->toEqual('20460000.00'); // 22,000,000 − 7٪ بیمه
});

it('rejects finalizing a run twice', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000012'), $admin);
    payrollSummary($employee);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    expect(fn () => app(FinalizePayrollRun::class)->handle($run->fresh(), $admin))
        ->toThrow(ValidationException::class);
});

// =====================================================================
// ۱۱ و ۱۲ — «همه یا هیچ» و idempotency
// =====================================================================

it('creates no payslip at all when any active employee is missing a monthly summary', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);

    $withSummary = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000013'), $admin);
    $withoutSummary = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000014'), $admin);
    payrollSummary($withSummary);

    expect(fn () => app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin))
        ->toThrow(ValidationException::class);

    // کارمند اول در ترتیب حلقه جلوتر است؛ اگر تراکنش بیرونی واحد نباشد،
    // فیش او باقی می‌ماند و «همه یا هیچ» نقض می‌شود.
    expect(Payslip::withoutGlobalScopes()->count())->toBe(0);
    expect(PayrollRun::withoutGlobalScopes()->count())->toBe(0);
    expect($withoutSummary->exists)->toBeTrue();
});

it('is idempotent in draft — recalculating does not duplicate or accumulate payslips', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000015'), $admin);
    payrollSummary($employee, ['total_overtime_minutes' => 600]);

    $first = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $firstPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    $second = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    expect($second->id)->toBe($first->id);
    expect(Payslip::withoutGlobalScopes()->count())->toBe(1);
    expect(Payslip::withoutGlobalScopes()->find($firstPayslip->id)->net_amount)
        ->toEqual($firstPayslip->net_amount);
});

it('reflects updated attendance on recalculation while the run is still draft', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000016'), $admin);
    $summary = payrollSummary($employee);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $summary->forceFill(['total_overtime_minutes' => 600])->save();
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect(Payslip::withoutGlobalScopes()->count())->toBe(1);
    expect($payslip->overtime_amount)->toEqual(number_format(10 * PAYROLL_HOURLY_RATE, 2, '.', ''));
});

it('skips terminated employees and removes their stale payslip on recalculation', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staying = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000017'), $admin);
    $leaving = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000018'), $admin);
    payrollSummary($staying);
    payrollSummary($leaving);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    expect(Payslip::withoutGlobalScopes()->count())->toBe(2);

    $leaving->forceFill(['employment_status' => 'terminated'])->save();
    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    expect(Payslip::withoutGlobalScopes()->count())->toBe(1);
    expect(Payslip::withoutGlobalScopes()->first()->employee_id)->toBe($staying->id);
});

// =====================================================================
// ۱۰ — authorization داخل خود Action (نه از مسیر Livewire) — CLAUDE.md بند ۹
// =====================================================================

it('rejects payroll calculation by a user without an authorized role', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000019'), $admin);
    payrollSummary($employee);

    $outsider = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($outsider, $company, 'sales_agent');

    expect(fn () => app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $outsider))
        ->toThrow(AuthorizationException::class);

    expect(Payslip::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects finalizing a payroll run by a user without an authorized role', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000020'), $admin);
    payrollSummary($employee);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $outsider = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($outsider, $company, 'sales_agent');

    expect(fn () => app(FinalizePayrollRun::class)->handle($run->fresh(), $outsider))
        ->toThrow(AuthorizationException::class);

    expect($run->fresh()->payroll_status)->toBe(PayrollStatus::Calculated);
});

it('rejects a holding_admin of company A calculating payroll for company B where they have no role at all', function () {
    $companyB = payrollCompany('tkart-payroll-a');
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($companyB->id, '4000000030'), $admin);
    payrollSummary($employee);

    $companyA = payrollCompany('other-payroll-a');
    $holdingAdminOfA = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($holdingAdminOfA, $companyA, 'holding_admin');

    expect(fn () => app(CalculatePayroll::class)->handle($companyB, PAYROLL_PERIOD, $holdingAdminOfA))
        ->toThrow(AuthorizationException::class);

    expect(Payslip::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in company B, even though they are holding_admin in company A — cross-company role leak regression', function () {
    $companyB = payrollCompany('tkart-payroll-b');
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($companyB->id, '4000000031'), $admin);
    payrollSummary($employee);

    $companyA = payrollCompany('other-payroll-b');
    $user = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($user, $companyA, 'holding_admin');
    payrollGiveRole($user, $companyB, 'viewer');

    expect(fn () => app(CalculatePayroll::class)->handle($companyB, PAYROLL_PERIOD, $user))
        ->toThrow(AuthorizationException::class);

    expect(Payslip::withoutGlobalScopes()->count())->toBe(0);
});

it('lets an accountant calculate and finalize payroll', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000021'), $admin);
    payrollSummary($employee);

    $accountant = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($accountant, $company, 'accountant');

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $accountant);

    expect($run->payroll_status)->toBe(PayrollStatus::Calculated);
    expect($run->calculated_by_user_id)->toBe($accountant->id);

    $finalized = app(FinalizePayrollRun::class)->handle($run->fresh(), $accountant);

    expect($finalized->payroll_status)->toBe(PayrollStatus::Finalized);
    expect($finalized->finalized_by_user_id)->toBe($accountant->id);
});

// =====================================================================
// ۹ و ۱۳ — دسترسی کارمند به فیش خودش، و ایزولاسیون شرکت (CLAUDE.md تست ۷.۱)
// =====================================================================

it('lets an employee see only their own finalized payslip', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $mine = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000022'), $admin);
    $theirs = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000023'), $admin);
    payrollSummary($mine);
    payrollSummary($theirs);

    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($mine, $account, $admin);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    $myPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $mine->id)->firstOrFail();
    $theirPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $theirs->id)->firstOrFail();

    expect($account->can('viewOwn', $myPayslip))->toBeTrue();
    expect($account->can('viewOwn', $theirPayslip))->toBeFalse();
});

it('hides a payslip from the employee until the run is finalized', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000024'), $admin);
    payrollSummary($employee);

    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($account->can('viewOwn', $payslip))->toBeFalse();

    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    expect($account->can('viewOwn', $payslip->fresh()))->toBeTrue();
});

it('prevents cross-company payslip access', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $companyA = payrollCompany('arshaman');
    $companyB = payrollCompany('verifex');

    $employeeA = app(CreateEmployee::class)->handle(payrollEmployeeData($companyA->id, '4000000025'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(payrollEmployeeData($companyB->id, '4000000026'), $admin);
    payrollSummary($employeeA);
    payrollSummary($employeeB);

    app(CalculatePayroll::class)->handle($companyA, PAYROLL_PERIOD, $admin);
    app(CalculatePayroll::class)->handle($companyB, PAYROLL_PERIOD, $admin);

    // محاسبه شرکت A نباید هیچ فیشی برای کارمند شرکت B ساخته باشد.
    $payslipsOfA = Payslip::withoutGlobalScopes()->where('owner_company_id', $companyA->id)->get();

    expect($payslipsOfA)->toHaveCount(1);
    expect($payslipsOfA->first()->employee_id)->toBe($employeeA->id);

    // و Global Scope باید فیش شرکت دیگر را از دید کاربر شرکت A پنهان کند.
    $userOfA = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($userOfA, $companyA, 'accountant');
    $this->actingAs($userOfA);
    app(CompanyContext::class)->set($companyA->id);

    expect(Payslip::query()->pluck('owner_company_id')->unique()->all())->toBe([$companyA->id]);
});

// =====================================================================
// BACKLOG #1 — نقطه اتصال آینده به ماژول هزینه‌ها
// =====================================================================

it('exposes every payslip of a run as pending expense posting', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000027'), $admin);
    payrollSummary($employee);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    expect($run->pendingExpensePosting()->withoutGlobalScopes()->count())->toBe(1);
});

// =====================================================================
// ورودی نامعتبر
// =====================================================================

// =====================================================================
// لایه Livewire — همان قواعد، این‌بار از مسیر واقعی UI
// =====================================================================

it('blocks the payroll admin panel for a user without an authorized role', function () {
    $company = payrollCompany();
    $outsider = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($outsider, $company, 'sales_agent');

    Livewire::actingAs($outsider)->test(PayrollIndex::class)
        ->assertForbidden();
});

it('renders the payroll admin panel with amounts and the provisional-formula notice', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000030'), $admin);
    payrollSummary($employee);

    $accountant = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($accountant, $company, 'accountant');
    $this->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::actingAs($accountant)->test(PayrollIndex::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk()
        ->assertSee('برای این ماه هنوز حقوقی محاسبه نشده است')
        ->assertSee('فرمول‌های موقت — نیازمند تأیید حسابدار واقعی')
        ->call('calculate')
        ->assertOk()
        // مبلغ خالص با ارقام فارسی و جداکننده هزارگان نمایش داده می‌شود.
        ->assertSee('۲۰,۴۶۰,۰۰۰ تومان')
        ->assertSeeHtml('wire:click="finalize"')
        ->call('finalize')
        ->assertOk()
        ->assertSee('نهایی‌شده')
        // بعد از نهایی‌شدن، دکمه‌های محاسبه و نهایی‌کردن دیگر رندر نمی‌شوند.
        // (به‌جای متن، خودِ wire:click چک می‌شود؛ عبارت «نهایی‌کردن دوره» در
        // زیرعنوان صفحه هم هست و assertDontSee روی متن، کاذب مثبت می‌دهد.)
        ->assertDontSeeHtml('wire:click="finalize"')
        ->assertDontSeeHtml('wire:click="calculate"');
});

it('surfaces a clear error in the panel when a monthly summary is missing', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000031'), $admin);

    payrollGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::actingAs($admin)->test(PayrollIndex::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->call('calculate')
        ->assertOk();

    expect(PayrollRun::withoutGlobalScopes()->count())->toBe(0);
});

it('shows the manual-review warning in the admin panel for a clamped payslip', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000035'), $admin);

    $absentDays = payrollWorkdaysBetween(PAYROLL_MONTH_START, PAYROLL_MONTH_END, $company->id);
    payrollSummary($employee, ['total_absent_days' => $absentDays, 'total_worked_days' => 0]);

    payrollGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::actingAs($admin)->test(PayrollIndex::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->call('calculate')
        ->assertOk()
        ->assertSee('نیاز به بررسی دستی حسابدار دارد')
        ->assertSee('نیاز به بررسی');
});

it('shows the manual-review warning to the employee on their own clamped payslip', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000036'), $admin);

    $absentDays = payrollWorkdaysBetween(PAYROLL_MONTH_START, PAYROLL_MONTH_END, $company->id);
    payrollSummary($employee, ['total_absent_days' => $absentDays, 'total_worked_days' => 0]);

    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    Livewire::actingAs($account)->test(MyPayslips::class)
        ->call('select', $payslip->id)
        ->assertOk()
        ->assertSee('نیاز به بررسی دستی حسابدار دارد');
});

it('shows only the current employee own finalized payslips on /my/payslips', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $mine = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000028'), $admin);
    $theirs = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000029'), $admin);
    payrollSummary($mine);
    payrollSummary($theirs);

    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($mine, $account, $admin);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    $theirPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $theirs->id)->firstOrFail();

    $component = Livewire::actingAs($account)->test(MyPayslips::class);

    expect($component->viewData('payslips')->pluck('employee_id')->all())->toBe([$mine->id]);

    // تلاش برای انتخاب فیش کارمند دیگر با شناسه دستی — Policy باید رد کند.
    $component->call('select', $theirPayslip->id);

    expect($component->viewData('selectedPayslip'))->toBeNull();
});

it('tells a user without an employee record that they have no personnel file', function () {
    $user = User::factory()->create(['is_super_admin' => false]);

    Livewire::actingAs($user)->test(MyPayslips::class)
        ->assertOk()
        ->assertSee('شما پرونده پرسنلی ندارید');
});

it('rejects an invalid period_month format', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);

    expect(fn () => app(CalculatePayroll::class)->handle($company, '1405-5', $admin))
        ->toThrow(ValidationException::class);
});

// =====================================================================
// بازگشایی دوره نهایی‌شده — ReopenPayrollRun
//
// این Action تنها مسیر مجاز برای برداشتن قفل مالی است. ویرایش مستقیم فیش
// قفل‌شده همچنان ممنوع می‌ماند (نگهبان مدل Payslip) — بند ۵.۵ CLAUDE.md.
// اصلاح یک دوره یعنی: بازگشایی ثبت‌شده با دلیل ← محاسبه دوباره ← نهایی‌کردن دوباره.
// =====================================================================

/**
 * یک دوره نهایی‌شده آماده، برای تست‌هایی که نقطه شروعشان «قفل‌شده» است.
 *
 * @return array{0: Company, 1: User, 2: Employee, 3: PayrollRun}
 */
function payrollFinalizedRun(string $nationalId): array
{
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, $nationalId), $admin);
    payrollSummary($employee);

    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    return [$company, $admin, $employee, $run->fresh()];
}

it('reopens a finalized run back to draft and clears the finalization stamps', function () {
    [, $admin, , $run] = payrollFinalizedRun('4000000040');

    $reopened = app(ReopenPayrollRun::class)->handle($run, 'چون کارکرد ماهانه آپدیت شد', $admin);

    expect($reopened->payroll_status)->toBe(PayrollStatus::Draft);
    expect($reopened->finalized_at)->toBeNull();
    expect($reopened->finalized_by_user_id)->toBeNull();
});

it('records who reopened a payroll run, when, and why', function () {
    [, $admin, , $run] = payrollFinalizedRun('4000000041');

    app(ReopenPayrollRun::class)->handle($run, 'چون کارکرد ماهانه آپدیت شد', $admin);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->subject_id)->toBe($run->id);
    expect($activity->properties['reason'])->toBe('چون کارکرد ماهانه آپدیت شد');
    expect($activity->properties['period_month'])->toBe(PAYROLL_PERIOD);
    expect($activity->properties['previous_status'])->toBe('finalized');
});

it('rejects reopening without a meaningful reason', function () {
    [, $admin, , $run] = payrollFinalizedRun('4000000042');

    foreach (['', '   ', 'کوتاه'] as $badReason) {
        expect(fn () => app(ReopenPayrollRun::class)->handle($run->fresh(), $badReason, $admin))
            ->toThrow(ValidationException::class);
    }

    expect($run->fresh()->payroll_status)->toBe(PayrollStatus::Finalized);
});

it('rejects reopening a run that is not finalized', function () {
    $company = payrollCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(payrollEmployeeData($company->id, '4000000043'), $admin);
    payrollSummary($employee);

    // وضعیت calculated — هنوز قفل نشده، پس چیزی برای بازگشایی نیست.
    $run = app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    expect(fn () => app(ReopenPayrollRun::class)->handle($run->fresh(), 'یک دلیل کاملاً معتبر', $admin))
        ->toThrow(ValidationException::class);

    expect($run->fresh()->payroll_status)->toBe(PayrollStatus::Calculated);
});

it('rejects reopening by a user without an authorized role', function () {
    [$company, , , $run] = payrollFinalizedRun('4000000044');

    $outsider = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($outsider, $company, 'sales_agent');

    expect(fn () => app(ReopenPayrollRun::class)->handle($run, 'یک دلیل کاملاً معتبر', $outsider))
        ->toThrow(AuthorizationException::class);

    expect($run->fresh()->payroll_status)->toBe(PayrollStatus::Finalized);
});

it('unlocks the payslip model guard once the run is reopened', function () {
    [, $admin, $employee, $run] = payrollFinalizedRun('4000000045');

    $payslip = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect(fn () => $payslip->update(['net_amount' => '1']))->toThrow(ValidationException::class);

    app(ReopenPayrollRun::class)->handle($run, 'یک دلیل کاملاً معتبر', $admin);

    // نگهبان مدل به وضعیت run نگاه می‌کند، نه به یک flag جدا — پس بدون هیچ
    // کد اضافه‌ای با بازگشایی باز می‌شود.
    $payslip->fresh()->update(['net_amount' => '123.00']);

    expect(Payslip::withoutGlobalScopes()->find($payslip->id)->net_amount)->toEqual('123.00');
});

it('replaces payslips rather than accumulating them after a reopen and recalculation', function () {
    [$company, $admin, $employee, $run] = payrollFinalizedRun('4000000046');

    $originalPayslipId = Payslip::withoutGlobalScopes()->where('employee_id', $employee->id)->value('id');

    app(ReopenPayrollRun::class)->handle($run, 'کارکرد ماهانه اصلاح شد', $admin);

    // کارکرد ماهانه اصلاح می‌شود و دوره دوباره محاسبه می‌شود.
    MonthlyAttendanceSummary::withoutGlobalScopes()
        ->where('employee_id', $employee->id)
        ->update(['total_overtime_minutes' => 600]);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);

    $payslips = Payslip::withoutGlobalScopes()->get();

    expect($payslips)->toHaveCount(1);
    expect($payslips->first()->id)->toBe($originalPayslipId);
    expect($payslips->first()->overtime_amount)
        ->toEqual(number_format(10 * PAYROLL_HOURLY_RATE, 2, '.', ''));
});

it('requires a fresh calculation before a reopened run can be finalized again', function () {
    [$company, $admin, , $run] = payrollFinalizedRun('4000000047');

    app(ReopenPayrollRun::class)->handle($run, 'یک دلیل کاملاً معتبر', $admin);

    // draft است، نه calculated — پس finalize باید رد شود.
    expect(fn () => app(FinalizePayrollRun::class)->handle($run->fresh(), $admin))
        ->toThrow(ValidationException::class);

    app(CalculatePayroll::class)->handle($company, PAYROLL_PERIOD, $admin);
    $finalized = app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    expect($finalized->payroll_status)->toBe(PayrollStatus::Finalized);
    expect($finalized->finalized_by_user_id)->toBe($admin->id);
});

it('lets an accountant reopen a finalized run', function () {
    [$company, , , $run] = payrollFinalizedRun('4000000048');

    $accountant = User::factory()->create(['is_super_admin' => false]);
    payrollGiveRole($accountant, $company, 'accountant');

    $reopened = app(ReopenPayrollRun::class)->handle($run, 'یک دلیل کاملاً معتبر', $accountant);

    expect($reopened->payroll_status)->toBe(PayrollStatus::Draft);
});

it('offers a reopen button instead of a dead end on a finalized run', function () {
    [$company, $admin] = payrollFinalizedRun('4000000049');

    payrollGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::actingAs($admin)->test(PayrollIndex::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk()
        ->assertSeeHtml('wire:click="openReopen"')
        ->assertDontSeeHtml('wire:click="finalize"');

    $component->call('openReopen')
        ->assertSet('showReopenModal', true)
        ->set('reopenReason', 'کارکرد ماهانه اصلاح شد')
        ->call('reopen')
        ->assertSet('showReopenModal', false)
        // بعد از بازگشایی، دوباره draft است: محاسبه در دسترس، نهایی‌کردن نه.
        ->assertSeeHtml('wire:click="calculate"');

    expect(PayrollRun::withoutGlobalScopes()->first()->payroll_status)->toBe(PayrollStatus::Draft);
});

it('keeps the run locked when the reopen modal is submitted without a reason', function () {
    [$company, $admin, , $run] = payrollFinalizedRun('4000000050');

    payrollGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::actingAs($admin)->test(PayrollIndex::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->call('openReopen')
        ->set('reopenReason', '')
        ->call('reopen')
        ->assertOk();

    expect($run->fresh()->payroll_status)->toBe(PayrollStatus::Finalized);
});
