<?php

use App\Livewire\HR\LeaveIndex;
use App\Livewire\HR\SelfService\MyLeaves;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\CalculateMonthlyAttendance;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\DeleteLeaveRequest;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Actions\UpdateLeaveRequest;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Services\WorkCalendar;
use App\Support\Farsi;
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

it('rejects a new leave request that overlaps an existing pending request for the same employee', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000011'), $admin);

    app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        $admin,
        RecordedBy::Admin,
    );

    expect(fn () => app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'unpaid', 'start_date' => '2026-08-03', 'end_date' => '2026-08-07'],
        $admin,
        RecordedBy::Admin,
    ))->toThrow(ValidationException::class);

    expect(Leave::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('rejects a new leave request that overlaps an existing approved request for the same employee', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000012'), $admin);

    $approved = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($approved, $admin);

    expect(fn () => app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'sick', 'start_date' => '2026-08-05', 'end_date' => '2026-08-06'],
        $admin,
        RecordedBy::Admin,
    ))->toThrow(ValidationException::class);
});

it('allows a new leave request over the same dates once the earlier request was rejected', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000013'), $admin);

    $rejected = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        $admin,
        RecordedBy::Admin,
    );
    app(RejectLeave::class)->handle($rejected, $admin);

    $second = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'sick', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        $admin,
        RecordedBy::Admin,
    );

    expect($second->id)->not->toBe($rejected->id);
    expect(Leave::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(2);
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

it('rejects a holding_admin of company A approving a leave request in company B where they have no role at all', function () {
    $companyB = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-b1', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($companyB->id, '3000000100'), $admin);
    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $companyA = Company::create(['name' => 'شرکت دیگر', 'slug' => 'company-a-b1', 'business_type' => 'project_services']);
    $holdingAdminOfA = User::factory()->create(['is_super_admin' => false]);
    leaveGiveRole($holdingAdminOfA, $companyA, 'holding_admin');

    expect(fn () => app(ApproveLeave::class)->handle($leave, $holdingAdminOfA))
        ->toThrow(AuthorizationException::class);

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Pending);
});

it('rejects a user who is only a viewer in company B, even though they are holding_admin in company A — cross-company role leak regression', function () {
    $companyB = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-b2', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($companyB->id, '3000000101'), $admin);
    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $companyA = Company::create(['name' => 'شرکت دیگر', 'slug' => 'company-a-b2', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    leaveGiveRole($user, $companyA, 'holding_admin');
    leaveGiveRole($user, $companyB, 'viewer');

    expect(fn () => app(ApproveLeave::class)->handle($leave, $user))
        ->toThrow(AuthorizationException::class);

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Pending);
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

// =====================================================================
// دلیل درخواست و دلیل رد
// =====================================================================

it('stores the rejection reason when one is given', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000030'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'reason' => 'سفر'],
        $admin,
        RecordedBy::Admin,
    );

    $rejected = app(RejectLeave::class)->handle($leave, $admin, 'در این بازه پروژه تحویل داریم.');

    expect($rejected->leave_status)->toBe(LeaveStatus::Rejected);
    expect($rejected->rejection_reason)->toBe('در این بازه پروژه تحویل داریم.');
});

it('leaves rejection_reason null when rejecting without one', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000031'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    // هم فراخوان بدون پارامتر (سازگاری با کد قبلی) و هم رشته فقط-فاصله باید
    // به null برسند، وگرنه UI بین «دلیلی نیست» و «رشته خالی» گیج می‌شود.
    $rejected = app(RejectLeave::class)->handle($leave, $admin);

    expect($rejected->rejection_reason)->toBeNull();
});

it('normalises a whitespace-only rejection reason to null', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000032'), $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $rejected = app(RejectLeave::class)->handle($leave, $admin, '    ');

    expect($rejected->rejection_reason)->toBeNull();
});

it('shows the rejection reason to the employee only when one exists', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000033'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $withReason = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );
    app(RejectLeave::class)->handle($withReason, $admin, 'در این بازه پروژه تحویل داریم.');

    $withoutReason = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02'],
        $admin,
        RecordedBy::Admin,
    );
    app(RejectLeave::class)->handle($withoutReason, $admin);

    Livewire::actingAs($account)->test(MyLeaves::class)
        ->assertOk()
        ->assertSee('در این بازه پروژه تحویل داریم.');
});

it('does not render a rejection reason block for an approved leave', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000034'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    Livewire::actingAs($account)->test(MyLeaves::class)
        ->assertOk()
        ->assertSee('تأییدشده')
        ->assertDontSee('دلیل رد');
});

// =====================================================================
// فیلترهای پنل ادمین
// =====================================================================

it('defaults the admin leave list to all employees and all statuses', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employeeA = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000035'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000036'), $admin);

    $pending = app(RequestLeave::class)->handle(
        $employeeA,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $approved = app(RequestLeave::class)->handle(
        $employeeB,
        ['leave_type' => 'annual', 'start_date' => '2026-08-05', 'end_date' => '2026-08-06'],
        $admin,
        RecordedBy::Admin,
    );
    app(ApproveLeave::class)->handle($approved, $admin);

    leaveGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::actingAs($admin)->test(LeaveIndex::class)
        ->assertSet('filterEmployeeId', '')
        ->assertSet('filterStatus', '');

    // هر دو کارمند و هر دو وضعیت باید بدون دست‌زدن به فیلترها دیده شوند.
    $ids = $component->viewData('leaves')->pluck('id')->all();

    expect($ids)->toContain($pending->id);
    expect($ids)->toContain($approved->id);
});

// =====================================================================
// ویرایش و حذف درخواست توسط خودِ کارمند — فقط تا قبل از تصمیم مدیر
// =====================================================================

/**
 * یک کارمند با حساب کاربری متصل + یک درخواست مرخصی در انتظار.
 *
 * @return array{0: Company, 1: User, 2: Employee, 3: User, 4: Leave}
 */
function leavePendingRequest(string $nationalId, string $slug = 'arshaman'): array
{
    $company = Company::create([
        'name' => 'آرشامان',
        'slug' => $slug.'-'.$nationalId,
        'business_type' => 'project_services',
    ]);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, $nationalId), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-03', 'end_date' => '2026-08-05', 'reason' => 'سفر'],
        $account,
        RecordedBy::SelfService,
    );

    return [$company, $admin, $employee, $account, $leave];
}

it('lets an employee edit their own pending leave request', function () {
    [, , , $account, $leave] = leavePendingRequest('3000000040');

    $updated = app(UpdateLeaveRequest::class)->handle($leave, [
        'leave_type' => 'sick',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
        'reason' => 'بیماری',
    ], $account);

    expect($updated->leave_type)->toBe(LeaveType::Sick);
    expect($updated->start_date->toDateString())->toBe('2026-08-10');
    expect($updated->reason)->toBe('بیماری');
    expect($updated->updated_by_user_id)->toBe($account->id);
    // days_count باید بازمحاسبه شود، نه اینکه مقدار قبلی بماند.
    expect($updated->days_count)->toBe(2);
});

it('does not let a leave request collide with its own previous dates when edited', function () {
    [, , , $account, $leave] = leavePendingRequest('3000000041');

    // بازه دست‌نخورده می‌ماند و فقط دلیل عوض می‌شود — نباید با نسخه قبلی خودش
    // تداخل بگیرد.
    $updated = app(UpdateLeaveRequest::class)->handle($leave, [
        'leave_type' => 'annual',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-05',
        'reason' => 'دلیل تازه',
    ], $account);

    expect($updated->reason)->toBe('دلیل تازه');
});

it('still rejects an edit that collides with a different live leave request', function () {
    [, $admin, $employee, $account, $leave] = leavePendingRequest('3000000042');

    app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-20', 'end_date' => '2026-08-22'],
        $admin,
        RecordedBy::Admin,
    );

    expect(fn () => app(UpdateLeaveRequest::class)->handle($leave, [
        'leave_type' => 'annual',
        'start_date' => '2026-08-21',
        'end_date' => '2026-08-23',
    ], $account))->toThrow(ValidationException::class);
});

it('rejects editing a leave request that was already approved', function () {
    [, $admin, , $account, $leave] = leavePendingRequest('3000000043');

    app(ApproveLeave::class)->handle($leave, $admin);

    expect(fn () => app(UpdateLeaveRequest::class)->handle($leave->fresh(), [
        'leave_type' => 'sick',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
    ], $account))->toThrow(AuthorizationException::class);

    expect($leave->fresh()->leave_type)->toBe(LeaveType::Annual);
});

it('rejects editing a leave request that was already rejected', function () {
    [, $admin, , $account, $leave] = leavePendingRequest('3000000044');

    app(RejectLeave::class)->handle($leave, $admin, 'در این بازه پروژه تحویل داریم.');

    expect(fn () => app(UpdateLeaveRequest::class)->handle($leave->fresh(), [
        'leave_type' => 'sick',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
    ], $account))->toThrow(AuthorizationException::class);
});

it('rejects editing another employee leave request', function () {
    [, , , , $leave] = leavePendingRequest('3000000045');
    $stranger = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => app(UpdateLeaveRequest::class)->handle($leave, [
        'leave_type' => 'sick',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-11',
    ], $stranger))->toThrow(AuthorizationException::class);
});

it('soft deletes a pending leave request and hides it everywhere', function () {
    [, $admin, $employee, $account, $leave] = leavePendingRequest('3000000046');

    app(DeleteLeaveRequest::class)->handle($leave, $account);

    // نرم حذف شده، نه فیزیکی — بند ۳ CLAUDE.md.
    expect(Leave::withoutGlobalScopes()->withTrashed()->find($leave->id))->not->toBeNull();
    expect(Leave::withoutGlobalScope('owner_company')->find($leave->id))->toBeNull();

    // و مهم‌تر: دیگر مانع ثبت درخواست جدید روی همان بازه نیست.
    $replacement = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-03', 'end_date' => '2026-08-05'],
        $admin,
        RecordedBy::Admin,
    );

    expect($replacement->id)->not->toBe($leave->id);
});

it('rejects deleting a leave request that was already approved', function () {
    [, $admin, , $account, $leave] = leavePendingRequest('3000000047');

    app(ApproveLeave::class)->handle($leave, $admin);

    expect(fn () => app(DeleteLeaveRequest::class)->handle($leave->fresh(), $account))
        ->toThrow(AuthorizationException::class);

    expect(Leave::withoutGlobalScope('owner_company')->find($leave->id))->not->toBeNull();
});

it('rejects deleting another employee leave request', function () {
    [, , , , $leave] = leavePendingRequest('3000000048');
    $stranger = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => app(DeleteLeaveRequest::class)->handle($leave, $stranger))
        ->toThrow(AuthorizationException::class);
});

it('keeps a deleted leave out of the monthly summary and payroll deduction', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000049'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'unpaid', 'start_date' => '2026-08-03', 'end_date' => '2026-08-05'],
        $account,
        RecordedBy::SelfService,
    );
    app(ApproveLeave::class)->handle($leave, $admin);

    $withLeave = app(CalculateMonthlyAttendance::class)->handle($employee, '1405-05', $admin);
    $leaveDaysBefore = $withLeave->total_leave_days;

    expect($leaveDaysBefore)->toBeGreaterThan(0);

    // حذف نرم؛ چون همه مصرف‌کننده‌ها withoutGlobalScope('owner_company') می‌زنند
    // و نه withoutGlobalScopes()، رکورد حذف‌شده دیگر دیده نمی‌شود.
    $leave->delete();

    $afterDelete = app(CalculateMonthlyAttendance::class)->handle($employee, '1405-05', $admin);

    expect($afterDelete->total_leave_days)->toBe(0);
});

// =====================================================================
// مرخصی ساعتی
// =====================================================================

it('stores an hourly leave with hours and zero days', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000050'), $admin);

    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-03',
        'start_time' => '09:00',
        'end_time' => '11:30',
    ], $admin, RecordedBy::Admin);

    expect($leave->leave_type)->toBe(LeaveType::Hourly);
    expect($leave->days_count)->toBe(0);
    expect($leave->hours_count)->toEqual('2.50');
    expect($leave->start_time)->toBe('09:00');
});

it('requires both times and a single day for an hourly leave', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000051'), $admin);

    // بدون ساعت
    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);

    // بازه چندروزه
    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-04',
        'start_time' => '09:00', 'end_time' => '11:00',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);

    // پایان قبل از شروع
    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '11:00', 'end_time' => '09:00',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);
});

it('allows two hourly leaves on the same day at different times', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000052'), $admin);

    app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '09:00', 'end_time' => '10:00',
    ], $admin, RecordedBy::Admin);

    $second = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '14:00', 'end_time' => '15:00',
    ], $admin, RecordedBy::Admin);

    expect($second->hours_count)->toEqual('1.00');
    expect(Leave::withoutGlobalScope('owner_company')->where('employee_id', $employee->id)->count())->toBe(2);
});

it('rejects two hourly leaves whose times actually overlap', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000053'), $admin);

    app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '09:00', 'end_time' => '12:00',
    ], $admin, RecordedBy::Admin);

    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '11:00', 'end_time' => '13:00',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);
});

it('rejects a second full-day leave on the exact same single day', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000060'), $admin);

    app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'annual', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
    ], $admin, RecordedBy::Admin);

    // رگرسیون: ستون‌های تاریخ به‌شکل کامل datetime ذخیره می‌شوند، پس مقایسه
    // رشته‌ای خام برای «کوچک‌تر یا مساوی» روی یک روز واحد شکست می‌خورد و این
    // درخواست دوم بی‌سروصدا پذیرفته می‌شد. با whereDate درست شد.
    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'sick', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);

    expect(Leave::withoutGlobalScope('owner_company')->where('employee_id', $employee->id)->count())->toBe(1);
});

it('counts a leave that starts on the last day of the period', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000061'), $admin);

    // ماه ۱۴۰۵-۰۵ در ۲۰۲۶-۰۸-۲۲ تمام می‌شود؛ مرخصی دقیقاً همان روز شروع می‌شود.
    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'annual', 'start_date' => '2026-08-22', 'end_date' => '2026-08-25',
    ], $admin, RecordedBy::Admin);
    app(ApproveLeave::class)->handle($leave, $admin);

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, '1405-05', $admin);

    // رگرسیون همان باگ مرز: قبلاً این مرخصی از قلم می‌افتاد و ۲۲ مرداد «غیبت»
    // شمرده می‌شد.
    expect($summary->total_leave_days)->toBeGreaterThan(0);
});

it('still blocks an hourly leave that falls inside a full-day leave', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000054'), $admin);

    app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'annual', 'start_date' => '2026-08-03', 'end_date' => '2026-08-05',
    ], $admin, RecordedBy::Admin);

    // یک طرف تمام‌روز است، پس هم‌پوشانی تاریخ کافی است.
    expect(fn () => app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-04', 'end_date' => '2026-08-04',
        'start_time' => '09:00', 'end_time' => '10:00',
    ], $admin, RecordedBy::Admin))->toThrow(ValidationException::class);
});

it('does not count an hourly leave as a leave day in the monthly summary', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000055'), $admin);

    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '09:00', 'end_time' => '11:00',
    ], $admin, RecordedBy::Admin);
    app(ApproveLeave::class)->handle($leave, $admin);

    $summary = app(CalculateMonthlyAttendance::class)->handle($employee, '1405-05', $admin);

    // کارمند آن روز سرِ کار بوده و فقط دو ساعت مرخصی داشته — نه یک روز مرخصی.
    expect($summary->total_leave_days)->toBe(0);
});

// =====================================================================
// لایه Livewire — پنل خودِ کارمند
// =====================================================================

it('lets an employee edit and delete a pending request from MyLeaves', function () {
    [, , , $account, $leave] = leavePendingRequest('3000000070');

    $component = Livewire::actingAs($account)->test(MyLeaves::class)
        ->call('edit', $leave->id)
        ->assertSet('editingLeaveId', $leave->id)
        ->assertSet('leave_type', 'annual')
        ->set('reason', 'دلیل به‌روزشده')
        ->call('save')
        ->assertSet('showForm', false);

    expect($leave->fresh()->reason)->toBe('دلیل به‌روزشده');

    $component->call('delete', $leave->id);

    expect(Leave::withoutGlobalScope('owner_company')->find($leave->id))->toBeNull();
});

it('hides edit and delete controls once a request is approved', function () {
    [, $admin, , $account, $leave] = leavePendingRequest('3000000071');

    app(ApproveLeave::class)->handle($leave, $admin);

    $component = Livewire::actingAs($account)->test(MyLeaves::class);

    // نه دکمه‌ای رندر می‌شود و نه فراخوانی مستقیم متد کاری از پیش می‌برد.
    $component->assertDontSeeHtml('wire:click="edit(\''.$leave->id.'\')"');

    $component->call('delete', $leave->id);

    expect(Leave::withoutGlobalScope('owner_company')->find($leave->id))->not->toBeNull();
});

it('renders an hourly leave duration in human form, not as a decimal', function () {
    [$company, $admin, $employee, $account] = leavePendingRequest('3000000080');

    // ۴۶ دقیقه ⇒ hours_count = 0.77 ذخیره می‌شود، ولی کاربر نباید «۰٫۷۷» ببیند.
    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly',
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-07',
        'start_time' => '09:00',
        'end_time' => '09:46',
    ], $admin, RecordedBy::Admin);

    expect($leave->hours_count)->toEqual('0.77');

    // پنل خودِ کارمند
    Livewire::actingAs($account)->test(MyLeaves::class)
        ->assertOk()
        ->assertSee('۴۶ دقیقه')
        ->assertDontSee('۰.۷۷');

    // پنل ادمین — همان متن، از همان helper مشترک.
    leaveGiveRole($admin, $company, 'holding_admin');
    $this->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::actingAs($admin)->test(LeaveIndex::class)
        ->assertOk()
        ->assertSee('۴۶ دقیقه')
        ->assertDontSee('۰.۷۷');
});

it('renders a whole-hour leave without a trailing zero minutes', function () {
    [, $admin, $employee, $account] = leavePendingRequest('3000000081');

    app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly',
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-07',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], $admin, RecordedBy::Admin);

    Livewire::actingAs($account)->test(MyLeaves::class)
        ->assertOk()
        ->assertSee('۲ ساعت')
        ->assertDontSee('۲ ساعت و ۰ دقیقه');
});

it('uses the time picker instead of a native time input in the hourly form', function () {
    [, , , $account] = leavePendingRequest('3000000082');

    $component = Livewire::actingAs($account)->test(MyLeaves::class)
        ->call('openForm')
        ->set('leave_type', 'hourly')
        ->assertOk();

    // دیگر فیلد قابل‌تایپ نیست.
    $component->assertDontSeeHtml('type="time"');

    // و انتخابگر به همان property لایو‌وایر وصل است.
    $component->assertSeeHtml("entangle('start_time')");
    $component->assertSeeHtml("entangle('end_time')");

    // دامنه کامل رندر شده باشد: ساعت ۰۰–۲۳ و دقیقه ۰۰–۵۹، با ارقام فارسی که
    // در PHP ساخته می‌شوند (نه یک نگاشت ارقام دوم در JS).
    //
    // @js آرایه را داخل JSON.parse می‌گذارد و ارقام غیر-اسکی را دوبار escape
    // می‌کند (۲۳ → \\u06f2\\u06f3). برچسب مورد انتظار با همان تبدیل ساخته
    // می‌شود تا تست به یک رشته رمزی سخت‌کدشده تبدیل نشود.
    $html = $component->html();
    $escaped = fn (string $label) => str_replace('\u', '\\\\u', trim(json_encode($label), '"'));

    foreach (['۰۰', '۲۳', '۵۹', '۴۶'] as $label) {
        expect($html)->toContain($escaped($label));
    }

    // ۶۰ در هیچ‌کدام از دو ستون نباید باشد (ساعت تا ۲۳، دقیقه تا ۵۹).
    // برای «۲۴» نمی‌شود همین را گفت: ۲۴ یک دقیقه معتبر است و در ستون دقیقه هست.
    expect($html)->not->toContain($escaped('۶۰'));
});

it('lands a value picked through the time picker in the property as H:i', function () {
    [, , $employee, $account] = leavePendingRequest('3000000083');

    // انتخابگر دقیقاً همان کاری را می‌کند که این set انجام می‌دهد: مقدار H:i را
    // در همان property می‌نشاند. از آنجا به بعد مسیر سرور تغییری نکرده.
    Livewire::actingAs($account)->test(MyLeaves::class)
        ->call('openForm')
        ->set('leave_type', 'hourly')
        ->set('start_date', '2026-09-07')
        ->set('start_time', '09:00')
        ->set('end_time', '10:45')
        ->call('save')
        ->assertSet('showForm', false);

    $leave = Leave::withoutGlobalScope('owner_company')
        ->where('employee_id', $employee->id)
        ->where('leave_type', LeaveType::Hourly)
        ->firstOrFail();

    expect($leave->start_time)->toBe('09:00');
    expect($leave->end_time)->toBe('10:45');
    expect(Farsi::durationFromHours($leave->hours_count))->toBe('۱ ساعت و ۴۵ دقیقه');
});

it('shows hourly fields in the self-service form when the hourly type is selected', function () {
    [, , , $account] = leavePendingRequest('3000000072');

    Livewire::actingAs($account)->test(MyLeaves::class)
        ->call('openForm')
        ->set('leave_type', 'hourly')
        ->assertSet('isHourly', true)
        ->assertSee('از ساعت')
        ->assertSee('تا ساعت')
        // به‌جای متن «تا تاریخ» (که در هدر جدول هم هست و کاذب مثبت می‌دهد)،
        // نبودِ خودِ انتخاب‌گر تاریخ پایان چک می‌شود.
        ->assertDontSeeHtml('jalaliParts.end_date.year');
});

it('submits an hourly leave from the self-service form', function () {
    [, , $employee, $account] = leavePendingRequest('3000000073');

    Livewire::actingAs($account)->test(MyLeaves::class)
        ->call('openForm')
        ->set('leave_type', 'hourly')
        ->set('start_date', '2026-09-07')
        ->set('start_time', '09:00')
        ->set('end_time', '12:00')
        ->call('save')
        ->assertSet('showForm', false);

    $hourly = Leave::withoutGlobalScope('owner_company')
        ->where('employee_id', $employee->id)
        ->where('leave_type', LeaveType::Hourly)
        ->firstOrFail();

    expect($hourly->hours_count)->toEqual('3.00');
    expect($hourly->days_count)->toBe(0);
    // تاریخ پایان خودکار برابر تاریخ شروع می‌شود.
    expect($hourly->end_date->toDateString())->toBe('2026-09-07');
});

it('clears the times when an hourly leave is edited into a full-day leave', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(leaveValidEmployeeData($company->id, '3000000056'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => 'hourly', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
        'start_time' => '09:00', 'end_time' => '11:00',
    ], $account, RecordedBy::SelfService);

    $updated = app(UpdateLeaveRequest::class)->handle($leave, [
        'leave_type' => 'annual',
        'start_date' => '2026-08-03',
        'end_date' => '2026-08-04',
    ], $account);

    expect($updated->leave_type)->toBe(LeaveType::Annual);
    expect($updated->start_time)->toBeNull();
    expect($updated->end_time)->toBeNull();
    expect($updated->hours_count)->toBeNull();
    expect($updated->days_count)->toBeGreaterThan(0);
});
