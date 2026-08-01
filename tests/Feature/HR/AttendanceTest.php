<?php

use App\Livewire\HR\AttendanceIndex;
use App\Livewire\HR\SelfService\MyAttendance;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Actions\PunchAttendance;
use App\Modules\HR\Actions\RecordAttendance;
use App\Modules\HR\Enums\PunchDirection;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceCalculator;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| حضور و غیاب — مدل «تردد»
|--------------------------------------------------------------------------
|
| هر ردیف attendances یک تردد است (یک ورود و یک خروج)، نه یک روز. کارمند
| می‌تواند در یک روز چند بار ورود/خروج بزند، پس چند ردیف در یک روز مجاز است.
|
| کسری و اضافه‌کاری فقط در سطح **روز** معنا دارند: مجموع کارکرد همه ترددهای آن
| روز در برابر روز کاری استاندارد. AttendanceCalculator این کار را می‌کند.
|
| پنل خودِ کارمند هیچ ورودی تاریخ/ساعتی ندارد — فقط دو دکمه که زمانشان را از
| سرور می‌گیرند (PunchAttendance). ثبت دستی برای هر تاریخی کار ادمین است
| (RecordAttendance) و محدودیت زمانی ندارد.
*/

function attendanceMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function attendanceGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => attendanceMakeRole($roleName)->id,
    ]);
}

function attendanceValidEmployeeData(string $companyId, string $nationalId): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست حضور',
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
 * شرکت + ادمین + کارمند. slug از کد ملی ساخته می‌شود چون بعضی تست‌ها این helper
 * را چند بار در یک تست صدا می‌زنند و slug شرکت یکتاست.
 *
 * @return array{0: Company, 1: User, 2: Employee}
 */
function attendanceEmployee(string $nationalId): array
{
    $company = Company::create([
        'name' => 'آرشامان',
        'slug' => 'arshaman-'.$nationalId,
        'business_type' => 'project_services',
    ]);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, $nationalId), $admin);

    return [$company, $admin, $employee];
}

/**
 * کارمندی که حساب کاربری متصل دارد و می‌تواند از پنل خودش تردد بزند.
 *
 * @return array{0: Company, 1: User, 2: Employee, 3: User}
 */
function attendanceSelfServiceEmployee(string $nationalId): array
{
    [$company, $admin, $employee] = attendanceEmployee($nationalId);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    return [$company, $admin, $employee, $account];
}

/**
 * یک تردد دستی توسط ادمین. ساعت null یعنی آن سرِ تردد ثبت نشده.
 */
function attendancePunch(Employee $employee, User $admin, string $date, ?string $in, ?string $out): Attendance
{
    $times = [];

    if ($in !== null) {
        $times['check_in_at'] = $date.' '.$in.':00';
    }

    if ($out !== null) {
        $times['check_out_at'] = $date.' '.$out.':00';
    }

    return app(RecordAttendance::class)->handle($employee, $date, $times, $admin);
}

/**
 * کسری و اضافه‌کاری یک روز — از همان سرویسی که محاسبه ماهانه استفاده می‌کند.
 *
 * @return array{0: int, 1: int} [کسری، اضافه‌کاری]
 */
function attendanceDayMinutes(Employee $employee, string $date): array
{
    $punches = Attendance::withoutGlobalScopes()
        ->where('employee_id', $employee->id)
        ->whereDate('attendance_date', $date)
        ->get();

    return app(AttendanceCalculator::class)->minutesForDay($punches);
}

// =====================================================================
// محاسبه در سطح روز
// =====================================================================

it('records a shortfall when the day total is under a standard workday', function () {
    [, $admin, $employee] = attendanceEmployee('1000000040');
    attendancePunch($employee, $admin, '2026-07-20', '09:00', '16:00'); // ۷ ساعت

    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([60, 0]);
});

it('records overtime when the day total exceeds a standard workday', function () {
    [, $admin, $employee] = attendanceEmployee('1000000041');
    attendancePunch($employee, $admin, '2026-07-20', '08:00', '18:00'); // ۱۰ ساعت

    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 120]);
});

it('treats a full workday as neither shortfall nor overtime regardless of start time', function () {
    [, $admin, $employee] = attendanceEmployee('1000000042');
    // ۱۰ صبح تا ۶ عصر هم هشت ساعت است — دیر آمدن به‌تنهایی جریمه نیست.
    attendancePunch($employee, $admin, '2026-07-20', '10:00', '18:00');

    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 0]);
});

it('sums several punches in the same day before comparing to the standard workday', function () {
    [, $admin, $employee] = attendanceEmployee('1000000043');

    // ۰۸:۰۰–۱۲:۰۰ و ۱۳:۳۰–۱۷:۳۰ = ۴ + ۴ = ۸ ساعت ⇒ نه کسری، نه اضافه‌کاری.
    attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:00');
    attendancePunch($employee, $admin, '2026-07-20', '13:30', '17:30');

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(2);
    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 0]);
});

it('does not penalise a day that is split into several punches', function () {
    [, $admin, $employee] = attendanceEmployee('1000000044');

    // اگر هر تردد جدا با ۴۸۰ دقیقه مقایسه می‌شد، هرکدام ۲۴۰ دقیقه کسری می‌گرفت
    // و این روزِ کاملاً کارشده جمعاً ۴۸۰ دقیقه کسری می‌خورد.
    attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:00');
    attendancePunch($employee, $admin, '2026-07-20', '13:00', '17:00');

    [$shortfall, $overtime] = attendanceDayMinutes($employee, '2026-07-20');

    expect($shortfall)->toBe(0);
    expect($overtime)->toBe(0);
});

it('never reports both a shortfall and overtime for the same day', function () {
    $cases = [
        ['1000000045', [['08:15', '16:30']]],
        ['1000000046', [['09:45', '15:00']]],
        ['1000000047', [['07:00', '12:00'], ['13:00', '19:30']]],
        ['1000000048', [['10:00', '18:00']]],
    ];

    foreach ($cases as [$nationalId, $punches]) {
        [, $admin, $employee] = attendanceEmployee($nationalId);

        foreach ($punches as [$in, $out]) {
            attendancePunch($employee, $admin, '2026-07-20', $in, $out);
        }

        [$shortfall, $overtime] = attendanceDayMinutes($employee, '2026-07-20');

        expect($shortfall * $overtime)->toBe(0);
    }
});

it('computes nothing for a day that still has an open punch', function () {
    [, $admin, $employee] = attendanceEmployee('1000000049');

    attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:00');
    attendancePunch($employee, $admin, '2026-07-20', '13:00', null); // هنوز باز

    // کارکرد روز تمام نشده؛ هر عددی حدس است. بدون این قاعده، کارمندی که همین
    // الان سرِ کار است برای امروز یک روز کامل کسری می‌گرفت.
    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 0]);
});

it('handles a shift that ends after midnight as one punch', function () {
    [, $admin, $employee] = attendanceEmployee('1000000050');

    // ۲۲:۰۰ تا ۰۷:۰۰ روز بعد = ۹ ساعت ⇒ ۶۰ دقیقه اضافه‌کاری، نه عدد منفی.
    app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_in_at' => '2026-07-20 22:00:00', 'check_out_at' => '2026-07-21 07:00:00'],
        $admin,
    );

    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 60]);
});

it('respects a configured non-default workday length', function () {
    config(['hr.standard_workday_minutes' => 360]); // شش ساعت

    [, $admin, $employee] = attendanceEmployee('1000000051');
    attendancePunch($employee, $admin, '2026-07-20', '08:00', '16:00');

    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([0, 120]);
});

it('exposes a signed day balance that is positive for overtime and negative for a shortfall', function () {
    $calculator = app(AttendanceCalculator::class);

    [, $admin, $over] = attendanceEmployee('1000000052');
    attendancePunch($over, $admin, '2026-07-20', '08:00', '18:00');

    [, $admin2, $under] = attendanceEmployee('1000000053');
    attendancePunch($under, $admin2, '2026-07-20', '09:00', '16:00');

    $punchesOf = fn (Employee $e) => Attendance::withoutGlobalScopes()->where('employee_id', $e->id)->get();

    expect($calculator->balanceForDay($punchesOf($over)))->toBe(120);
    expect($calculator->balanceForDay($punchesOf($under)))->toBe(-60);
});

it('reports a single punch duration but never a negative one', function () {
    [, $admin, $employee] = attendanceEmployee('1000000054');

    $normal = attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:30');
    expect($normal->duration_minutes)->toBe(270);

    $open = attendancePunch($employee, $admin, '2026-07-21', '08:00', null);
    expect($open->duration_minutes)->toBeNull();
    expect($open->isOpen())->toBeTrue();

    // خروج قبل از ورود، داده نامعتبر است — نه مدت منفی.
    $invalid = attendancePunch($employee, $admin, '2026-07-22', '12:00', '08:00');
    expect($invalid->duration_minutes)->toBe(0);
});

// =====================================================================
// مسیر ادمین — ثبت و ویرایش دستی
// =====================================================================

it('lets an admin record a punch for any employee on any date', function () {
    [$company, $admin, $employee] = attendanceEmployee('1000000001');

    $attendance = attendancePunch($employee, $admin, '2026-07-20', '08:15', '16:30');

    expect($attendance->recorded_by)->toBe(RecordedBy::Admin);
    expect($attendance->owner_company_id)->toBe($company->id);
    expect($attendance->duration_minutes)->toBe(495);
});

it('lets an admin add a second punch on a date that already has one', function () {
    [, $admin, $employee] = attendanceEmployee('1000000002');

    $first = attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:00');
    $second = attendancePunch($employee, $admin, '2026-07-20', '13:00', '17:00');

    // رگرسیون: قبلاً UNIQUE(employee_id, attendance_date) این را غیرممکن می‌کرد.
    expect($second->id)->not->toBe($first->id);
    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(2);
});

it('edits the targeted punch instead of guessing one from the date', function () {
    [, $admin, $employee] = attendanceEmployee('1000000003');

    $morning = attendancePunch($employee, $admin, '2026-07-20', '08:00', '12:00');
    $afternoon = attendancePunch($employee, $admin, '2026-07-20', '13:00', '17:00');

    app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_out_at' => '2026-07-20 18:00:00'],
        $admin,
        $afternoon,
    );

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(2);
    expect($morning->fresh()->check_out_at->format('H:i'))->toBe('12:00');
    expect($afternoon->fresh()->check_out_at->format('H:i'))->toBe('18:00');
    expect($afternoon->fresh()->updated_by_user_id)->toBe($admin->id);
});

it('rejects an admin-recording Action call by an unauthorized actor, even bypassing Livewire entirely', function () {
    [$company, $admin, $employee] = attendanceEmployee('1000000004');
    $intruder = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($intruder, $company, 'operator');

    expect(fn () => app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_in_at' => '2026-07-20 08:00:00'],
        $intruder,
    ))->toThrow(AuthorizationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

// =====================================================================
// PunchAttendance — تردد خودِ کارمند، زمان فقط از سرور
// =====================================================================

it('stamps the punch with the server clock, not anything the caller supplies', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000060');

    $this->travelTo(Carbon::parse('2026-08-05 09:17:33'));

    $punch = app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    // امضای Action اصلاً پارامتر زمان ندارد — این تست فقط آن تضمین ساختاری را
    // تثبیت می‌کند.
    expect($punch->check_in_at->format('Y-m-d H:i:s'))->toBe('2026-08-05 09:17:33');
    expect($punch->attendance_date->toDateString())->toBe('2026-08-05');
    expect($punch->recorded_by)->toBe(RecordedBy::SelfService);
    expect($punch->created_by_user_id)->toBe($account->id);
});

it('buckets a punch into the local working day, not the UTC one', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000073');

    // ۲۲:۳۰ UTC = ۰۲:۰۰ بامداد روز بعد به وقت تهران.
    // روز کاری یک مفهوم محلی است: این تردد باید زیر ۶ آگوست ثبت شود، نه ۵.
    // بدون این تبدیل، ورودهای بامدادی به جمع ماهانه و حقوق روز اشتباه می‌رفتند.
    $this->travelTo(Carbon::parse('2026-08-05 22:30:00', 'UTC'));

    $punch = app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    expect($punch->attendance_date->toDateString())->toBe('2026-08-06');
    // ولی خودِ لحظه همچنان UTC ذخیره می‌شود — بند ۳ CLAUDE.md.
    expect($punch->check_in_at->utc()->format('Y-m-d H:i'))->toBe('2026-08-05 22:30');
});

it('renders stored UTC instants in the display timezone', function () {
    [, $admin, $employee] = attendanceEmployee('1000000074');

    // ۰۴:۳۰ UTC = ۰۸:۰۰ صبح تهران.
    app(RecordAttendance::class)->handle(
        $employee,
        '2026-08-05',
        ['check_in_at' => '2026-08-05 04:30:00', 'check_out_at' => '2026-08-05 12:30:00'],
        $admin,
    );

    $punch = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect(Jalali::toDisplayTime($punch->check_in_at))->toBe('۰۸:۰۰');
    expect(Jalali::toDisplayTime($punch->check_out_at))->toBe('۱۶:۰۰');
});

it('rejects a second check-in while a punch is still open', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000061');

    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    expect(fn () => app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account))
        ->toThrow(ValidationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('rejects a check-out when there is no open punch', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000062');

    expect(fn () => app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account))
        ->toThrow(ValidationException::class);

    // و بعد از بستن یک تردد، خروج دوم هم رد می‌شود.
    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);
    app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);

    expect(fn () => app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account))
        ->toThrow(ValidationException::class);
});

it('supports several in/out cycles in one day', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000063');

    $this->travelTo(Carbon::parse('2026-08-05 08:00:00'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    $this->travelTo(Carbon::parse('2026-08-05 12:00:00'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);

    $this->travelTo(Carbon::parse('2026-08-05 13:30:00'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    $this->travelTo(Carbon::parse('2026-08-05 17:30:00'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);

    $punches = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->get();

    expect($punches)->toHaveCount(2);
    expect(attendanceDayMinutes($employee, '2026-08-05'))->toBe([0, 0]); // ۴ + ۴ = ۸ ساعت
});

it('closes yesterday open punch on an after-midnight check-out and keeps the start date', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000064');

    // ساعت‌ها صریح به وقت تهران، چون «کدام روز کاری» یک مفهوم محلی است.
    $this->travelTo(Carbon::parse('2026-08-05 22:00:00', 'Asia/Tehran'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    $this->travelTo(Carbon::parse('2026-08-06 07:00:00', 'Asia/Tehran'));
    $closed = app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);
    // یک شیفت = یک رکورد، متعلق به روزی که شروع شده — وگرنه یک شیفت شبانه بین
    // دو روز (و گاهی دو ماه) تکه‌تکه می‌شد.
    expect($closed->attendance_date->toDateString())->toBe('2026-08-05');
    expect($closed->duration_minutes)->toBe(540);
    expect(attendanceDayMinutes($employee, '2026-08-05'))->toBe([0, 60]);
});

it('blocks tomorrow check-in while a forgotten punch is still open', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000065');

    $this->travelTo(Carbon::parse('2026-08-05 08:00:00'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    // خروج فراموش شده. تردد باز عمداً خودکار بسته نمی‌شود — بستن خودکار یعنی
    // ساختن ساعت خروجی که هرگز اتفاق نیفتاده، و آن عدد وارد محاسبه حقوق می‌شود.
    $this->travelTo(Carbon::parse('2026-08-06 08:00:00'));

    expect(fn () => app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account))
        ->toThrow(ValidationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('rejects a punch for a different employee', function () {
    [$company, $admin, $employeeA] = attendanceEmployee('1000000066');
    $employeeB = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000067'), $admin);
    $accountB = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employeeB, $accountB, $admin);

    expect(fn () => app(PunchAttendance::class)->handle($employeeA, PunchDirection::In, $accountB))
        ->toThrow(AuthorizationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employeeA->id)->exists())->toBeFalse();
});

it('lets the database reject a second open punch even when the Action is bypassed', function () {
    [$company, , $employee] = attendanceEmployee('1000000068');

    Attendance::withoutGlobalScopes()->create([
        'employee_id' => $employee->id,
        'owner_company_id' => $company->id,
        'attendance_date' => '2026-08-05',
        'check_in_at' => '2026-08-05 08:00:00',
        'recorded_by' => RecordedBy::SelfService,
    ]);

    // ایندکس یکتای uq_attendance_single_open_punch روی ستون تولیدشده — لایه دوم
    // در برابر مسابقه دو کلیک سریع، مستقل از گارد اپلیکیشن (CLAUDE.md بند ۳).
    expect(fn () => Attendance::withoutGlobalScopes()->create([
        'employee_id' => $employee->id,
        'owner_company_id' => $company->id,
        'attendance_date' => '2026-08-06',
        'check_in_at' => '2026-08-06 08:00:00',
        'recorded_by' => RecordedBy::SelfService,
    ]))->toThrow(QueryException::class);
});

it('allows many closed punches for the same employee', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000069');

    // ایندکس یکتا فقط ردیف‌های **باز** را محدود می‌کند؛ ردیف‌های بسته آزادند.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05'] as $day) {
        $this->travelTo(Carbon::parse($day.' 08:00:00'));
        app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

        $this->travelTo(Carbon::parse($day.' 16:00:00'));
        app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);
    }

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(3);
});

// =====================================================================
// پنل خودِ کارمند
// =====================================================================

it('renders no date or time input at all on the self-service panel', function () {
    [, , , $account] = attendanceSelfServiceEmployee('1000000070');

    $component = Livewire::actingAs($account)->test(MyAttendance::class)->assertOk();

    // هیچ راهی برای فرستادن زمان دلخواه از سمت کاربر وجود ندارد.
    $component->assertDontSeeHtml('type="time"');
    $component->assertDontSeeHtml('jalaliParts');
});

it('lets an employee check in and out from the self-service panel', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000071');

    $component = Livewire::actingAs($account)->test(MyAttendance::class)
        ->assertSet('employeeId', $employee->id)
        ->assertSet('hasOpenPunch', false)
        ->call('checkIn')
        ->assertSet('hasOpenPunch', true)
        ->call('checkOut')
        ->assertSet('hasOpenPunch', false);

    $punch = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($punch->check_in_at)->not->toBeNull();
    expect($punch->check_out_at)->not->toBeNull();
    expect($punch->recorded_by)->toBe(RecordedBy::SelfService);

    $component->call('checkOut')->assertOk();

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('shows today punch log with the running total', function () {
    [, , $employee, $account] = attendanceSelfServiceEmployee('1000000072');

    // ساعت‌ها به وقت تهران وارد می‌شوند و باید دقیقاً به همان شکل هم دیده شوند —
    // این تست هم‌زمان نگهبان تبدیل منطقه زمانی در لایه نمایش است.
    foreach ([['08:00', PunchDirection::In], ['12:00', PunchDirection::Out],
        ['13:00', PunchDirection::In], ['17:00', PunchDirection::Out]] as [$time, $direction]) {
        $this->travelTo(Carbon::parse('2026-08-05 '.$time.':00', 'Asia/Tehran'));
        app(PunchAttendance::class)->handle($employee, $direction, $account);
    }

    Livewire::actingAs($account)->test(MyAttendance::class)
        ->assertOk()
        ->assertSee('۰۸:۰۰')
        ->assertSee('۱۲:۰۰')
        ->assertSee('۱۳:۰۰')
        ->assertSee('۱۷:۰۰')
        ->assertSee('۸ ساعت'); // جمع کارکرد امروز
});

it('shows a friendly message instead of a server error when the logged-in user has no linked employee record', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => true]);
    attendanceGiveRole($user, $company, 'holding_admin');

    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(MyAttendance::class)
        ->assertSet('employeeId', null)
        ->assertSee('شما پرونده پرسنلی ندارید');
});

// =====================================================================
// پنل ادمین
// =====================================================================

it('prevents a user without an authorized role from viewing the attendance admin panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($user, $company, 'operator');

    $this->actingAs($user)->get('/attendance')->assertForbidden();
});

it('allows a holding_admin to record a punch from the AttendanceIndex form', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($admin, $company, 'holding_admin');
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000009'), $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(AttendanceIndex::class)
        ->call('openForm')
        ->set('formEmployeeId', $employee->id)
        ->set('attendance_date', '2026-07-20')
        ->set('check_in_time', '08:10')
        ->set('check_out_time', '16:00')
        ->call('save')
        ->assertHasNoErrors();

    $attendance = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    expect($attendance->duration_minutes)->toBe(470);
    expect(attendanceDayMinutes($employee, '2026-07-20'))->toBe([10, 0]);
});

it('round-trips admin form times through the display timezone', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($admin, $company, 'holding_admin');
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000075'), $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(AttendanceIndex::class)
        ->call('openForm')
        ->set('formEmployeeId', $employee->id)
        ->set('attendance_date', '2026-08-05')
        ->set('check_in_time', '08:00')
        ->set('check_out_time', '16:00')
        ->call('save')
        ->assertHasNoErrors();

    $punch = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();

    // ذخیره UTC (۰۸:۰۰ تهران = ۰۴:۳۰ UTC) …
    expect($punch->check_in_at->utc()->format('H:i'))->toBe('04:30');
    // … ولی همان عددی که ادمین نوشت باید در فرم ویرایش برگردد.
    Livewire::test(AttendanceIndex::class)
        ->call('edit', $punch->id)
        ->assertSet('check_in_time', '08:00')
        ->assertSet('check_out_time', '16:00');
});

it('lets an admin edit a self-recorded punch and keeps the original recorder', function () {
    [$company, $admin, $employee, $account] = attendanceSelfServiceEmployee('1000000032');

    $this->travelTo(Carbon::parse('2026-08-05 09:00:00', 'Asia/Tehran'));
    $punch = app(PunchAttendance::class)->handle($employee, PunchDirection::In, $account);

    $this->travelTo(Carbon::parse('2026-08-05 17:00:00', 'Asia/Tehran'));
    app(PunchAttendance::class)->handle($employee, PunchDirection::Out, $account);

    attendanceGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(AttendanceIndex::class)
        ->call('edit', $punch->id)
        ->assertSet('editingAttendanceId', $punch->id)
        ->assertSet('check_in_time', '09:00')
        ->set('check_out_time', '18:00')
        ->call('save')
        ->assertSet('showForm', false);

    $updated = Attendance::withoutGlobalScopes()->find($punch->id);

    // ادمین ۱۸:۰۰ وارد کرد؛ ذخیره UTC است ولی به وقت محلی باید همان ۱۸:۰۰ باشد.
    expect(Jalali::local($updated->check_out_at)->format('H:i'))->toBe('18:00');
    expect($updated->check_out_at->utc()->format('H:i'))->toBe('14:30');
    // اصل ثبت‌کننده حفظ می‌شود، ولی ویرایش‌کننده ثبت می‌شود.
    expect($updated->recorded_by)->toBe(RecordedBy::SelfService);
    expect($updated->updated_by_user_id)->toBe($admin->id);
});
