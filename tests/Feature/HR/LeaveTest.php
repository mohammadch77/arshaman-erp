<?php

use App\Livewire\HR\LeaveIndex;
use App\Livewire\HR\SelfService\MyLeaves;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\CalculateMonthlyAttendance;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Jalali;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function leaveMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function leaveGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => leaveMakeRole($roleName)->id,
    ]);
}

function leaveValidEmployeeData(string $companyId, string $nationalId): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست مرخصی',
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
 * دو تاریخ متوالی که هر دو روز کاری‌اند (بدون جمعه/تعطیل) — برای تست‌هایی که
 * فقط به یک بازه دو روزه‌ی قابل‌پیش‌بینی نیاز دارند.
 */
function leaveTwoConsecutiveWorkdays(?string $companyId): array
{
    $calendar = app(WorkCalendar::class);
    $date = Carbon::parse('2026-08-01');

    while (true) {
        $next = $date->copy()->addDay();
        if ($calendar->isWorkday($date, $companyId) && $calendar->isWorkday($next, $companyId)) {
            return [$date->toDateString(), $next->toDateString()];
        }
        $date->addDay();
    }
}

it('lets an employee request their own leave and calculates days_count skipping non-workdays', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000001'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    [$start, $end] = leaveTwoConsecutiveWorkdays($company->id);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => $start, 'end_date' => $end, 'reason' => 'سفر'],
        $account,
        RecordedBy::SelfService,
    );

    expect($leave->days_count)->toBe(2);
    expect($leave->leave_status)->toBe(LeaveStatus::Pending);
    expect($leave->owner_company_id)->toBe($company->id);
});

it('does not count a friday inside the leave range in days_count', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000002'), $admin);

    // یک بازه هفت‌روزه که حتماً شامل یک جمعه است.
    $start = Carbon::parse('2026-08-01');
    $end = $start->copy()->addDays(6);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
        $admin,
        RecordedBy::Admin,
    );

    $workdaysInRange = 0;
    $calendar = app(WorkCalendar::class);
    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
        if ($calendar->isWorkday($d, $company->id)) {
            $workdaysInRange++;
        }
    }

    expect($leave->days_count)->toBe($workdaysInRange);
    expect($leave->days_count)->toBeLessThan(7);
});

it('rejects a self-service leave request for a different employee', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employeeA = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000003'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000004'), $admin);
    $accountB = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employeeB, $accountB, $admin);

    expect(fn () => app(RequestLeave::class)->handle(
        $employeeA,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $accountB,
        RecordedBy::SelfService,
    ))->toThrow(AuthorizationException::class);

    expect(Leave::withoutGlobalScopes()->where('employee_id', $employeeA->id)->exists())->toBeFalse();
});

it('prevents an employee from approving their own leave request', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000005'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);
    leaveGiveRole($account, $company, 'operator');

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $account,
        RecordedBy::SelfService,
    );

    expect(fn () => app(ApproveLeave::class)->handle($leave, $account))
        ->toThrow(AuthorizationException::class);

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Pending);
});

it('lets an authorized admin approve and reject leave requests', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000006'), $admin);

    $leaveToApprove = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );
    $leaveToReject = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'sick', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03'],
        $admin,
        RecordedBy::Admin,
    );

    app(ApproveLeave::class)->handle($leaveToApprove, $admin);
    app(RejectLeave::class)->handle($leaveToReject, $admin);

    expect($leaveToApprove->fresh()->leave_status)->toBe(LeaveStatus::Approved);
    expect($leaveToApprove->fresh()->approved_by_user_id)->toBe($admin->id);
    expect($leaveToReject->fresh()->leave_status)->toBe(LeaveStatus::Rejected);
});

it('rejects approving a leave request that is not pending anymore', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000007'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    expect(fn () => app(ApproveLeave::class)->handle($leave->fresh(), $admin))
        ->toThrow(ValidationException::class);
});

it('shows a friendly message instead of a server error when the logged-in user has no linked employee record', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => true]);
    leaveGiveRole($user, $company, 'holding_admin');

    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(MyLeaves::class)
        ->assertSet('employeeId', null)
        ->assertSee('شما پرونده پرسنلی ندارید');
});

it('lets an employee submit a leave request from the MyLeaves self-service panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000008'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);
    leaveGiveRole($account, $company, 'operator');

    $this->actingAs($account);
    session(['active_company_id' => $company->id]);

    [$year, $month, $day] = [1405, 5, 10];
    $endParts = [1405, 5, 12];

    Livewire::test(MyLeaves::class)
        ->call('openForm')
        ->set('leave_type', 'annual')
        ->set('jalaliParts.start_date.year', $year)
        ->set('jalaliParts.start_date.month', $month)
        ->set('jalaliParts.start_date.day', $day)
        ->set('jalaliParts.end_date.year', $endParts[0])
        ->set('jalaliParts.end_date.month', $endParts[1])
        ->set('jalaliParts.end_date.day', $endParts[2])
        ->call('save')
        ->assertHasNoErrors();

    expect(Leave::withoutGlobalScopes()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

it('prevents a user without an authorized role from viewing the leave admin panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    leaveGiveRole($user, $company, 'operator');

    $this->actingAs($user)->get('/leaves')->assertForbidden();
});

it('lets a holding_admin approve a pending leave from the LeaveIndex admin panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    leaveGiveRole($admin, $company, 'holding_admin');
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000009'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(LeaveIndex::class)
        ->call('approve', $leave->id)
        ->assertHasNoErrors();

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Approved);
});

it('counts an approved leave day as leave (not absence) in the monthly attendance summary', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000010'), $admin);

    $year = 1404;
    $month = 1;
    $start = Carbon::parse(Jalali::toGregorian($year, $month, 1));
    $calendar = app(WorkCalendar::class);
    $leaveDate = $start->copy();
    while (! $calendar->isWorkday($leaveDate, $company->id)) {
        $leaveDate->addDay();
    }

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => $leaveDate->toDateString(), 'end_date' => $leaveDate->toDateString()],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    $totalWorkdays = 0;
    $end = Carbon::parse(Jalali::toGregorian($year, $month, Jalali::daysInMonth($year, $month)));
    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
        if ($calendar->isWorkday($d, $company->id)) {
            $totalWorkdays++;
        }
    }

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, sprintf('%04d-%02d', $year, $month), $admin);

    expect($summary->total_leave_days)->toBe(1);
    expect($summary->total_worked_days)->toBe(0);
    expect($summary->total_absent_days)->toBe($totalWorkdays - 1);
});
