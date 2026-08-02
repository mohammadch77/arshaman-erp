<?php

use App\Livewire\HR\EmployeeForm;
use App\Livewire\HR\EmployeeIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\LinkEmployeeToUser;
use App\Modules\HR\Models\Employee;
use App\Support\Jalali;
use Carbon\Carbon;
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

it('rejects a holding_admin of company A creating an employee for company B where they have no role at all', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $holdingAdminOfA = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($holdingAdminOfA, $companyA, 'holding_admin');

    expect(fn () => app(CreateEmployee::class)->handle(hrValidEmployeeData($companyB->id), $holdingAdminOfA))
        ->toThrow(AuthorizationException::class);

    expect(Employee::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->exists())->toBeFalse();
});

it('rejects a user who is only a viewer in company B, even though they are holding_admin in company A — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($user, $companyA, 'holding_admin');
    hrGiveRole($user, $companyB, 'viewer');

    expect(fn () => app(CreateEmployee::class)->handle(hrValidEmployeeData($companyB->id), $user))
        ->toThrow(AuthorizationException::class);

    expect(Employee::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->exists())->toBeFalse();
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

it('judges contract expiry by the local calendar day, not the UTC one', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    // ۲۱:۰۰ UTC = ۰۰:۳۰ بامداد روز بعد به وقت تهران. در این لحظه، «امروز» به
    // وقت تهران یک روز جلوتر از «امروز» به وقت UTC است.
    $this->travelTo(Carbon::parse('2026-08-05 21:00:00', 'UTC'));

    // قراردادی که دقیقاً امروزِ تهران (۶ آگوست) تمام می‌شود باید هشدار بدهد.
    $endingToday = hrValidEmployeeData($company->id, '6666666666');
    $endingToday['contract_end_date'] = '2026-08-06';
    $employee = app(CreateEmployee::class)->handle($endingToday, $admin);

    expect($employee->isContractExpiringSoon())->toBeTrue();

    // و قراردادی که دیروزِ تهران تمام شده، دیگر «نزدیک» نیست.
    $expired = hrValidEmployeeData($company->id, '7777777777');
    $expired['contract_end_date'] = '2026-08-05';
    $past = app(CreateEmployee::class)->handle($expired, $admin);

    expect($past->isContractExpiringSoon())->toBeFalse();
});

it('includes a contract ending exactly thirty local days out', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->travelTo(Carbon::parse('2026-08-05 12:00:00', 'Asia/Tehran'));

    // مرز دقیق ۳۰ روز: با مقایسه لحظه‌ای (به‌جای روز-با-روز) این مورد به‌خاطر
    // ۳:۳۰ اختلاف ساعت از قلم می‌افتاد.
    $data = hrValidEmployeeData($company->id, '8888888888');
    $data['contract_end_date'] = '2026-09-04';
    $employee = app(CreateEmployee::class)->handle($data, $admin);

    expect($employee->isContractExpiringSoon())->toBeTrue();
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

it('limits day options to 30 for a 30-day month like mehr (month 7)', function () {
    $options = Jalali::dayOptions(1403, 7);

    expect($options)->toHaveCount(30);
    expect(collect($options)->pluck('id')->all())->not->toContain(31);
});

it('limits day options to 29 for esfand in a non-leap year', function () {
    // ۱۴۰۴ سال غیرکبیسه است
    $options = Jalali::dayOptions(1404, 12);

    expect($options)->toHaveCount(29);
    expect(collect($options)->pluck('id')->all())->not->toContain(30);
});

it('allows day 30 for esfand in a leap year', function () {
    // ۱۴۰۳ سال کبیسه است
    $options = Jalali::dayOptions(1403, 12);

    expect($options)->toHaveCount(30);
});

it('clamps the selected day to 30 when the month changes from a 31-day month to mehr', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    hrGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(EmployeeForm::class)
        ->set('jalaliParts.hire_date.year', 1403)
        ->set('jalaliParts.hire_date.month', 1)
        ->set('jalaliParts.hire_date.day', 31)
        ->assertSet('jalaliParts.hire_date.day', 31)
        ->set('jalaliParts.hire_date.month', 7)
        ->assertSet('jalaliParts.hire_date.day', 30);
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

it('links an employee to a user account successfully', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888880'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);

    $linked = app(LinkEmployeeToUser::class)->handle($employee, $account, $admin);

    expect($linked->user_id)->toBe($account->id);
});

it('prevents linking a user that is already linked to another employee', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employeeA = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888881'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888882'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);

    app(LinkEmployeeToUser::class)->handle($employeeA, $account, $admin);

    expect(fn () => app(LinkEmployeeToUser::class)->handle($employeeB, $account, $admin))
        ->toThrow(ValidationException::class);

    expect($employeeB->refresh()->user_id)->toBeNull();
});

it('prevents linking an already-linked user at the database constraint level even bypassing validation', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employeeA = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888883'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888884'), $admin);
    $account = User::factory()->create(['is_super_admin' => false]);

    app(LinkEmployeeToUser::class)->handle($employeeA, $account, $admin);

    expect(fn () => Employee::withoutGlobalScopes()->where('id', $employeeB->id)->update(['user_id' => $account->id]))
        ->toThrow(QueryException::class);
});

it('rejects a link attempt by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888885'), $admin);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    hrGiveRole($intruder, $company, 'operator');
    $account = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => app(LinkEmployeeToUser::class)->handle($employee, $account, $intruder))
        ->toThrow(AuthorizationException::class);

    expect($employee->refresh()->user_id)->toBeNull();
});

it('links an employee to a user from the EmployeeIndex modal and excludes already-linked users from the picker', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    hrGiveRole($admin, $company, 'holding_admin');

    $employeeA = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888886'), $admin);
    $employeeB = app(CreateEmployee::class)->handle(hrValidEmployeeData($company->id, '8888888887'), $admin);
    $linkedAccount = User::factory()->create(['is_super_admin' => false, 'full_name' => 'قبلاً وصل‌شده']);
    app(LinkEmployeeToUser::class)->handle($employeeB, $linkedAccount, $admin);
    $freeAccount = User::factory()->create(['is_super_admin' => false, 'full_name' => 'کاربر آزاد']);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(EmployeeIndex::class)
        ->call('openLinkModal', $employeeA->id)
        ->assertSet('showLinkModal', true);

    expect($component->get('unlinkedUsers')->pluck('id'))
        ->toContain($freeAccount->id)
        ->not->toContain($linkedAccount->id);

    $component->set('linkUserId', $freeAccount->id)
        ->call('link')
        ->assertHasNoErrors()
        ->assertSet('showLinkModal', false);

    expect($employeeA->refresh()->user_id)->toBe($freeAccount->id);
});
