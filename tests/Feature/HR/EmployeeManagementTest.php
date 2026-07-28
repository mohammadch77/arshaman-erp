<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Models\Employee;
use App\Livewire\HR\EmployeeForm;
use App\Livewire\HR\EmployeeIndex;
use App\Support\Jalali;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function hrMakeRole(string $name): Role
{
    return Role::create(['name' => $name, 'display_name' => $name, 'is_system' => true]);
}

function hrGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => hrMakeRole($roleName)->id,
    ]);
}

function hrValidEmployeeData(string $companyId, string $nationalId = '1234567890'): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست',
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

it('prevents a user without an authorized role from viewing the employee list', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($user, $company, 'operator');

    $this->actingAs($user)->get('/employees')->assertForbidden();
});

it('allows a holding_admin to view the employee list', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin)->get('/employees')->assertOk();
});

it('allows an accountant to view the employee list', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($accountant, $company, 'accountant');

    $this->actingAs($accountant)->get('/employees')->assertOk();
});

it('creates an employee with full contract info successfully', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $employee = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id), $admin);

    expect($employee->full_name)->toBe('کارمند تست');
    expect($employee->employment_status->value)->toBe('active');
    expect($employee->owner_company_id)->toBe($company->id);
});

it('rejects Action calls made by an unauthorized actor, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $intruder = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id), $intruder))
        ->toThrow(AuthorizationException::class);

    expect(Employee::withoutGlobalScopes()->where('owner_company_id', $company->id)->exists())->toBeFalse();
});

it('rejects duplicate national_id within the same company at the validation layer', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '1111111111'), $admin);

    expect(fn () => app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '1111111111'), $admin))
        ->toThrow(ValidationException::class);
});

it('rejects duplicate national_id at the database constraint level even bypassing validation', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '2222222222'), $admin);

    expect(fn () => Employee::withoutGlobalScopes()->create([
        ...hrValidEmployeeData($company->id, '2222222222'),
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]))->toThrow(QueryException::class);
});

it('allows the same national_id in different companies', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    app(CreateEmployee::class)->handle(hrValidEmployeeData($companyA->id, '3333333333'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(hrValidEmployeeData($companyB->id, '3333333333'), $admin);

    expect($employeeB->national_id)->toBe('3333333333');
});

it('shows a contract-expiring warning when contract_end_date is within 30 days', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    hrGiveRole($admin, $company, 'holding_admin');

    $data = hrValidEmployeeData($company->id, '4444444444');
    $data['contract_end_date'] = now()->addDays(10)->toDateString();
    $employee = app(CreateEmployee::class)->handle($data, $admin);

    expect($employee->isContractExpiringSoon())->toBeTrue();

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(EmployeeForm::class, ['employee' => $employee->id])
        ->assertSet('isContractExpiringSoon', true);
});

it('does not flag a permanent contract or a far-future end date as expiring', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $data = hrValidEmployeeData($company->id, '5555555555');
    $data['contract_end_date'] = now()->addYear()->toDateString();
    $employee = app(CreateEmployee::class)->handle($data, $admin);

    expect($employee->isContractExpiringSoon())->toBeFalse();
});

it('converts a jalali date round-trip to the correct gregorian date and back', function () {
    // ۱۴۰۳/۰۱/۰۱ (نوروز) == ۲۰۲۴-۰۳-۲۰ میلادی
    expect(Jalali::toGregorian(1403, 1, 1))->toBe('2024-03-20');
    expect(Jalali::toJalaliParts('2024-03-20'))->toBe(['year' => 1403, 'month' => 1, 'day' => 1]);
    expect(Jalali::toDisplay('2024-03-20'))->toBe('۱۴۰۳/۰۱/۰۱');
});

it('clamps an out-of-range jalali day to the last valid day of that month instead of crashing', function () {
    // مهر (ماه ۷) فقط ۳۰ روز دارد؛ روز ۳۱ باید به ۳۰ محدود شود، نه خطا بدهد.
    expect(Jalali::toGregorian(1403, 7, 31))->toBe(Jalali::toGregorian(1403, 7, 30));
});

it('creates an employee from jalali year/month/day selects entered through the form', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    hrGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(EmployeeForm::class)
        ->set('full_name', 'کارمند شمسی')
        ->set('national_id', '6666666666')
        ->set('position', 'حسابدار')
        ->set('jalaliParts.hire_date.year', 1403)
        ->set('jalaliParts.hire_date.month', 1)
        ->set('jalaliParts.hire_date.day', 1)
        ->set('contract_type', 'permanent')
        ->set('jalaliParts.contract_start_date.year', 1403)
        ->set('jalaliParts.contract_start_date.month', 1)
        ->set('jalaliParts.contract_start_date.day', 1)
        ->set('base_salary', '400000000')
        ->call('save')
        ->assertHasNoErrors();

    $employee = Employee::withoutGlobalScopes()->where('national_id', '6666666666')->firstOrFail();

    expect($employee->hire_date->toDateString())->toBe('2024-03-20');
    expect($employee->contract_start_date->toDateString())->toBe('2024-03-20');
});

it('displays the employee contract end date in full jalali format in the index list, not gregorian', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    hrGiveRole($admin, $company, 'holding_admin');

    $data = hrValidEmployeeData($company->id, '7777777777');
    $data['contract_end_date'] = '2024-03-20';
    app(CreateEmployee::class)->handle($data, $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(EmployeeIndex::class)
        ->assertSee('۱۴۰۳/۰۱/۰۱')
        ->assertDontSee('2024-03-20');
});
