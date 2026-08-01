<?php

use App\Livewire\HR\MonthlyAttendanceReport;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CalculateMonthlyAttendance;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\RecordAttendance;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function monthlySummaryMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function monthlySummaryGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => monthlySummaryMakeRole($roleName)->id,
    ]);
}

function monthlySummaryValidEmployeeData(string $companyId, string $nationalId): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست جمع ماهانه',
        'national_id' => $nationalId,
        'phone' => '09121234567',
        'address' => 'تهران',
        'position' => 'برنامه‌نویس',
        'hire_date' => '2025-01-01',
        'contract_type' => 'permanent',
        'contract_start_date' => '2025-01-01',
        'contract_end_date' => null,
        'base_salary' => '500000000',
    ];
}

/**
 * تعداد روزهای کاری (غیرجمعه، بدون تعطیل رسمی) بین بازه یک ماه شمسی مشخص —
 * برای مقایسه با خروجی Action در تست، بدون سیدشدن تعطیلات رسمی.
 */
function monthlySummaryCountWorkdays(int $year, int $month, ?string $companyId): int
{
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $end = Carbon::parse(Jalali::toGregorian($year, $month, Jalali::daysInMonth($year, $month)));
    $calendar = app(WorkCalendar::class);

    $count = 0;
    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
        if ($calendar->isWorkday($date, $companyId)) {
            $count++;
        }
    }

    return $count;
}

it('marks a workday without a recorded attendance as an absence', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000001'), $admin);

    $year = 1404;
    $month = 1;
    $totalWorkdays = monthlySummaryCountWorkdays($year, $month, $company->id);

    // یک روز کاری مشخص از همان ماه که حضور دارد ثبت می‌شود.
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $calendar = app(WorkCalendar::class);
    $presentDate = $start->copy();
    while (! $calendar->isWorkday($presentDate, $company->id)) {
        $presentDate->addDay();
    }

    app(RecordAttendance::class)->handle(
        $employee,
        $presentDate->toDateString(),
        ['check_in_at' => $presentDate->toDateString().' 08:00:00', 'check_out_at' => $presentDate->toDateString().' 16:00:00'],
        $admin,
    );

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    expect($summary->total_worked_days)->toBe(1);
    expect($summary->total_absent_days)->toBe($totalWorkdays - 1);
    expect($summary->total_leave_days)->toBe(0);
    expect($summary->owner_company_id)->toBe($company->id);
});

it('does not duplicate the summary row when recalculated for the same employee/month', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000002'), $admin);

    app(CalculateMonthlyAttendance::class)->handle($employee, '1404-01', $admin);
    app(CalculateMonthlyAttendance::class)->handle($employee, '1404-01', $admin);

    expect(
        MonthlyAttendanceSummary::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('period_month', '1404-01')
            ->count()
    )->toBe(1);
});

it('recalculates and replaces totals on a later call instead of accumulating', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000003'), $admin);

    $year = 1404;
    $month = 1;
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $calendar = app(WorkCalendar::class);
    $presentDate = $start->copy();
    while (! $calendar->isWorkday($presentDate, $company->id)) {
        $presentDate->addDay();
    }

    app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    app(RecordAttendance::class)->handle(
        $employee,
        $presentDate->toDateString(),
        ['check_in_at' => $presentDate->toDateString().' 08:00:00'],
        $admin,
    );

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    expect($summary->total_worked_days)->toBe(1);
});

it('sums every punch of a day when several exist, not just the last one', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000010'), $admin);

    $year = 1404;
    $month = 1;
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $calendar = app(WorkCalendar::class);
    $day = $start->copy();
    while (! $calendar->isWorkday($day, $company->id)) {
        $day->addDay();
    }

    // ۰۸:۰۰–۱۲:۰۰ و ۱۳:۰۰–۱۸:۰۰ = ۹ ساعت ⇒ ۶۰ دقیقه اضافه‌کاری برای آن روز.
    foreach ([['08:00', '12:00'], ['13:00', '18:00']] as [$in, $out]) {
        app(RecordAttendance::class)->handle(
            $employee,
            $day->toDateString(),
            [
                'check_in_at' => $day->toDateString().' '.$in.':00',
                'check_out_at' => $day->toDateString().' '.$out.':00',
            ],
            $admin,
        );
    }

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    // رگرسیون: پیش از این keyBy() روی تاریخ فقط **آخرین** تردد هر روز را نگه
    // می‌داشت و تردد صبح بی‌سروصدا از محاسبه حذف می‌شد.
    expect($summary->total_worked_days)->toBe(1);
    expect($summary->total_overtime_minutes)->toBe(60);
    expect($summary->total_late_minutes)->toBe(0);
});

it('does not count a shortfall for a day that still has an open punch', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000011'), $admin);

    $year = 1404;
    $month = 1;
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $calendar = app(WorkCalendar::class);
    $day = $start->copy();
    while (! $calendar->isWorkday($day, $company->id)) {
        $day->addDay();
    }

    app(RecordAttendance::class)->handle(
        $employee,
        $day->toDateString(),
        ['check_in_at' => $day->toDateString().' 08:00:00'],
        $admin,
    );

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    // روز به‌عنوان «کارکرد» شمرده می‌شود ولی کسری نمی‌گیرد — کارکردش هنوز تمام
    // نشده و ۴۸۰ دقیقه کسری دادن به آن، حدس است.
    expect($summary->total_worked_days)->toBe(1);
    expect($summary->total_late_minutes)->toBe(0);
    expect($summary->total_overtime_minutes)->toBe(0);
});

it('rejects a monthly calculation Action call by an actor without an authorized role', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000004'), $admin);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    monthlySummaryGiveRole($intruder, $company, 'operator');

    expect(fn () => app(CalculateMonthlyAttendance::class)->handle($employee, '1404-01', $intruder))
        ->toThrow(AuthorizationException::class);

    expect(
        MonthlyAttendanceSummary::withoutGlobalScopes()->where('employee_id', $employee->id)->exists()
    )->toBeFalse();
});

it('prevents a user without an authorized role from viewing the monthly attendance report panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    monthlySummaryGiveRole($user, $company, 'operator');

    $this->actingAs($user)->get('/attendance/monthly-summary')->assertForbidden();
});

it('lets a holding_admin calculate and view the monthly summary from the report panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    monthlySummaryGiveRole($admin, $company, 'holding_admin');
    $employee = app(CreateEmployee::class)->handle(monthlySummaryValidEmployeeData($company->id, '2000000005'), $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(MonthlyAttendanceReport::class)
        ->set('filterEmployeeId', $employee->id)
        ->set('year', 1404)
        ->set('month', 1)
        ->call('calculate')
        ->assertHasNoErrors();

    expect(
        MonthlyAttendanceSummary::where('employee_id', $employee->id)->where('period_month', '1404-01')->exists()
    )->toBeTrue();
});
