<?php

use App\Livewire\HR\PayrollExpenseReport;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\CalculatePayroll;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\FinalizePayrollRun;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Models\Payslip;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Session 7 — گزارش هزینه حقوق (تفکیک شرکت)
|--------------------------------------------------------------------------
|
| ماه مرجع همان ماه PayrollTest است تا از همان ابزارهای کمکی استفاده شود.
*/

const REPORT_PERIOD = '1405-05';
const REPORT_BASE_SALARY = '22000000';

function reportMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function reportGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => reportMakeRole($roleName)->id,
    ]);
}

function reportCompany(string $slug): Company
{
    return Company::create([
        'name' => 'شرکت '.$slug,
        'slug' => $slug,
        'business_type' => 'project_services',
    ]);
}

function reportEmployeeData(string $companyId, string $nationalId, string $baseSalary = REPORT_BASE_SALARY): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست گزارش '.$nationalId,
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

function reportSummary(Employee $employee, array $overrides = []): MonthlyAttendanceSummary
{
    return MonthlyAttendanceSummary::factory()->create(array_merge([
        'employee_id' => $employee->id,
        'owner_company_id' => $employee->owner_company_id,
        'period_month' => REPORT_PERIOD,
        'total_worked_days' => 22,
    ], $overrides));
}

/**
 * یک شرکت با یک دوره حقوق نهایی‌شده و N کارمند عادی — برای تست جمع.
 *
 * @return array{0: Company, 1: User}
 */
function reportFinalizedCompany(string $slug, array $nationalIds): array
{
    $company = reportCompany($slug);
    $admin = User::factory()->create(['is_super_admin' => true]);

    foreach ($nationalIds as $nationalId) {
        $employee = app(CreateEmployee::class)->handle(reportEmployeeData($company->id, $nationalId), $admin);
        reportSummary($employee);
    }

    $run = app(CalculatePayroll::class)->handle($company, REPORT_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    return [$company, $admin];
}

it('sums finalized net_amount per company and matches a manual sum', function () {
    [$companyA] = reportFinalizedCompany('arshaman', ['5000000001', '5000000002']);
    [$companyB] = reportFinalizedCompany('verifex', ['5000000003']);

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $component = Livewire::actingAs($superAdmin)->test(PayrollExpenseReport::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk();

    $rows = $component->viewData('rows')->keyBy(fn ($row) => $row['company']->id);

    $manualA = Payslip::withoutGlobalScopes()
        ->where('owner_company_id', $companyA->id)
        ->get()
        ->reduce(fn (string $carry, $payslip) => bcadd($carry, (string) $payslip->net_amount, 2), '0');

    expect($rows[$companyA->id]['total_net'])->toEqual($manualA);
    expect($rows[$companyA->id]['payslip_count'])->toBe(2);
    expect($rows[$companyB->id]['payslip_count'])->toBe(1);
});

it('counts payslips needing manual review separately without mixing them into the total', function () {
    $company = reportCompany('tkart');
    $admin = User::factory()->create(['is_super_admin' => true]);

    $normal = app(CreateEmployee::class)->handle(reportEmployeeData($company->id, '5000000010'), $admin);
    reportSummary($normal);

    // غیبت کل ماه: کسر از حقوق پایه بیشتر می‌شود و net صفر با clamp می‌ماند.
    $flagged = app(CreateEmployee::class)->handle(reportEmployeeData($company->id, '5000000011'), $admin);
    reportSummary($flagged, ['total_absent_days' => 30, 'total_worked_days' => 0]);

    $run = app(CalculatePayroll::class)->handle($company, REPORT_PERIOD, $admin);
    app(FinalizePayrollRun::class)->handle($run->fresh(), $admin);

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $component = Livewire::actingAs($superAdmin)->test(PayrollExpenseReport::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk();

    $rows = $component->viewData('rows')->keyBy(fn ($row) => $row['company']->id);
    $row = $rows[$company->id];

    $normalPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $normal->id)->firstOrFail();

    // فیش نیازمند بررسی (net صفر) جمع اصلی را تحت‌تأثیر قرار نمی‌دهد.
    expect($row['total_net'])->toEqual((string) $normalPayslip->net_amount);
    expect($row['review_count'])->toBe(1);
    expect($row['review_names']->all())->toBe([$flagged->full_name]);

    $flaggedPayslip = Payslip::withoutGlobalScopes()->where('employee_id', $flagged->id)->firstOrFail();
    expect($flaggedPayslip->net_amount)->toEqual('0.00');

    expect($component->instance()->totalReviewCount)->toBe(1);
});

it('excludes payroll runs that are not finalized yet', function () {
    $company = reportCompany('pixentry');
    $admin = User::factory()->create(['is_super_admin' => true]);
    $employee = app(CreateEmployee::class)->handle(reportEmployeeData($company->id, '5000000020'), $admin);
    reportSummary($employee);

    // فقط محاسبه شده، نهایی نشده.
    app(CalculatePayroll::class)->handle($company, REPORT_PERIOD, $admin);

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $component = Livewire::actingAs($superAdmin)->test(PayrollExpenseReport::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk();

    expect($component->viewData('rows'))->toHaveCount(0);
});

it('blocks the report for a user without an authorized role', function () {
    $company = reportCompany('dana');
    $outsider = User::factory()->create(['is_super_admin' => false]);
    reportGiveRole($outsider, $company, 'sales_agent');

    Livewire::actingAs($outsider)->test(PayrollExpenseReport::class)
        ->assertForbidden();
});

it('lets an accountant view the holding-wide report', function () {
    [$companyA] = reportFinalizedCompany('arshaman', ['5000000030']);

    $accountant = User::factory()->create(['is_super_admin' => false]);
    reportGiveRole($accountant, $companyA, 'accountant');

    Livewire::actingAs($accountant)->test(PayrollExpenseReport::class)
        ->set('year', 1405)
        ->set('month', 5)
        ->assertOk()
        ->assertSee('شرکت arshaman');
});
