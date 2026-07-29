<?php

use App\Livewire\HR\AttendanceIndex;
use App\Livewire\HR\SelfService\MyAttendance;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Actions\RecordAttendance;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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

it('lets an admin record attendance for any employee and calculates late/overtime correctly', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000001'), $admin);

    $attendance = app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_in_at' => '2026-07-20 08:15:00', 'check_out_at' => '2026-07-20 16:30:00'],
        $admin,
        RecordedBy::Admin,
    );

    expect($attendance->late_minutes)->toBe(15);
    expect($attendance->overtime_minutes)->toBe(30);
    expect($attendance->recorded_by)->toBe(RecordedBy::Admin);
    expect($attendance->owner_company_id)->toBe($company->id);
});

it('does not count late or overtime minutes for exact on-time check-in/out', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000002'), $admin);

    $attendance = app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_in_at' => '2026-07-20 08:00:00', 'check_out_at' => '2026-07-20 16:00:00'],
        $admin,
        RecordedBy::Admin,
    );

    expect($attendance->late_minutes)->toBe(0);
    expect($attendance->overtime_minutes)->toBe(0);
});

it('updates the same attendance row on a later call for the same employee/date instead of duplicating it', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000003'), $admin);

    app(RecordAttendance::class)->handle($employee, '2026-07-20', ['check_in_at' => '2026-07-20 08:00:00'], $admin, RecordedBy::Admin);
    app(RecordAttendance::class)->handle($employee, '2026-07-20', ['check_out_at' => '2026-07-20 16:45:00'], $admin, RecordedBy::Admin);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->count())->toBe(1);

    $attendance = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->first();
    expect($attendance->check_in_at->format('H:i'))->toBe('08:00');
    expect($attendance->overtime_minutes)->toBe(45);
});

it('rejects an admin-recording Action call by an unauthorized actor, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000004'), $admin);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($intruder, $company, 'operator');

    expect(fn () => app(RecordAttendance::class)->handle(
        $employee,
        '2026-07-20',
        ['check_in_at' => '2026-07-20 08:00:00'],
        $intruder,
        RecordedBy::Admin,
    ))->toThrow(AuthorizationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

it('lets an employee record their own attendance through the self-service action', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000005'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    $attendance = app(RecordAttendance::class)->handle(
        $employee,
        now()->toDateString(),
        ['check_in_at' => now()],
        $account,
        RecordedBy::SelfService,
    );

    expect($attendance->recorded_by)->toBe(RecordedBy::SelfService);
});

it('rejects a self-service attempt to record attendance for a different employee', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employeeA = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000006'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000007'), $admin);
    $accountB = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employeeB, $accountB, $admin);

    expect(fn () => app(RecordAttendance::class)->handle(
        $employeeA,
        now()->toDateString(),
        ['check_in_at' => now()],
        $accountB,
        RecordedBy::SelfService,
    ))->toThrow(AuthorizationException::class);

    expect(Attendance::withoutGlobalScopes()->where('employee_id', $employeeA->id)->exists())->toBeFalse();
});

it('rejects a self-service attempt to record attendance for a date other than today', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000008'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    expect(fn () => app(RecordAttendance::class)->handle(
        $employee,
        '2020-01-01',
        ['check_in_at' => '2020-01-01 08:00:00'],
        $account,
        RecordedBy::SelfService,
    ))->toThrow(ValidationException::class);
});

it('prevents a user without an authorized role from viewing the attendance admin panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    attendanceGiveRole($user, $company, 'operator');

    $this->actingAs($user)->get('/attendance')->assertForbidden();
});

it('allows a holding_admin to record attendance from the AttendanceIndex form', function () {
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
    expect($attendance->late_minutes)->toBe(10);
});

it('lets an employee check in and out from the MyAttendance self-service panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(attendanceValidEmployeeData($company->id, '1000000010'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);
    app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);
    attendanceGiveRole($account, $company, 'operator');

    $this->actingAs($account);
    session(['active_company_id' => $company->id]);

    Livewire::test(MyAttendance::class)
        ->assertSet('employeeId', $employee->id)
        ->call('checkIn')
        ->assertSet('checkOutAt', null)
        ->call('checkOut');

    $attendance = Attendance::withoutGlobalScopes()->where('employee_id', $employee->id)->firstOrFail();
    expect($attendance->check_in_at)->not->toBeNull();
    expect($attendance->check_out_at)->not->toBeNull();
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
