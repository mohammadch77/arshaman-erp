<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Validation\ValidationException;

/**
 * اولین مصرف‌کننده‌ی واقعی موتور فرایند — اتصال به فرایند مرخصی HR. توابع
 * global محلی با پیشوند lpi (leave-process-integration) تا با نام‌های مشابه
 * در LeaveTest.php/ProcessEngineTest.php تداخل نکنند.
 */
function lpiRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function lpiGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => lpiRole($roleName)->id,
    ]);
}

function lpiEmployeeData(string $companyId, string $nationalId): array
{
    return [
        'owner_company_id' => $companyId,
        'full_name' => 'کارمند تست فرایند مرخصی',
        'national_id' => $nationalId,
        'phone' => '09121234567',
        'address' => 'تهران',
        'position' => 'developer',
        'hire_date' => '2025-01-01',
        'contract_type' => 'permanent',
        'contract_start_date' => '2025-01-01',
        'contract_end_date' => null,
        'base_salary' => '500000000',
    ];
}

/**
 * دقیقاً همان زنجیره‌ی ProcessLeaveDefinitionSeeder (start → تأیید
 * سرپرست/HR[accountant] → بررسی مدت[days_count>5] → تأیید مدیر ارشد
 * [holding_admin] → end)، ولی مستقیم روی دیتابیس تست، برای یک شرکت دلخواه.
 *
 * @return array{definition: ProcessDefinition, supervisorApproval: ProcessStep, durationCheck: ProcessStep, seniorApproval: ProcessStep, endApproved: ProcessStep, endRejected: ProcessStep}
 */
function lpiBuildLeaveProcessDefinition(Company $company, User $creator): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تأیید درخواست مرخصی (تستی)',
        'process_key' => 'hr_leave_approval_'.uniqid(),
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'start',
        'name' => 'شروع',
        'step_type' => StepType::Start,
    ]);

    $supervisorApproval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'supervisor_approval',
        'name' => 'تأیید سرپرست/منابع انسانی',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'accountant',
    ]);

    // condition_true → end_approved مستقیم، condition_false → تأیید اضافه.
    // عمداً LessThanOrEqual (نه GreaterThan با اهداف برعکس): ProcessEngine
    // status نهایی را از روی نوع نتیجه‌ی transition تعیین می‌کند (condition_true
    // همیشه approved، condition_false همیشه rejected)، نه از روی این‌که کدام
    // end step فیزیکی هدف است — پس مسیر «مستقیم تأیید» باید حتماً condition_true باشد.
    $durationCheck = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'duration_check',
        'name' => 'بررسی مدت مرخصی',
        'step_type' => StepType::Condition,
        'condition_module_field' => 'days_count',
        'condition_operator' => ConditionOperator::LessThanOrEqual,
        'condition_value' => '5',
    ]);

    $seniorApproval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'senior_approval',
        'name' => 'تأیید اضافه‌ی مدیر ارشد',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
    ]);

    $endApproved = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'end_approved',
        'name' => 'پایان — تأییدشده',
        'step_type' => StepType::End,
    ]);

    $endRejected = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'end_rejected',
        'name' => 'پایان — ردشده',
        'step_type' => StepType::End,
    ]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $supervisorApproval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $supervisorApproval->id, 'to_step_id' => $durationCheck->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $supervisorApproval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $durationCheck->id, 'to_step_id' => $endApproved->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $durationCheck->id, 'to_step_id' => $seniorApproval->id, 'on_result' => TransitionResult::ConditionFalse]);
    ProcessTransition::create(['from_step_id' => $seniorApproval->id, 'to_step_id' => $endApproved->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $seniorApproval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    return compact('definition', 'supervisorApproval', 'durationCheck', 'seniorApproval', 'endApproved', 'endRejected');
}

/**
 * شرکت + ادمین + سرپرست(accountant) + مدیر ارشد(holding_admin) + کارمند —
 * همه‌ی اجزای مشترک تست‌های این فایل.
 *
 * @return array{0: Company, 1: User, 2: User, 3: User, 4: Employee}
 */
function lpiScenario(string $nationalId): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-lpi-'.$nationalId, 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $supervisor = User::factory()->create(['is_super_admin' => false]);
    lpiGiveRole($supervisor, $company, 'accountant');
    $seniorManager = User::factory()->create(['is_super_admin' => false]);
    lpiGiveRole($seniorManager, $company, 'holding_admin');
    $employee = app(CreateEmployee::class)->handle(lpiEmployeeData($company->id, $nationalId), $admin);

    return [$company, $admin, $supervisor, $seniorManager, $employee];
}

it('auto-starts a process instance when a leave is requested in a company with an active leave process, and blocks the direct approve/reject path', function () {
    [$company, $admin, , , $employee] = lpiScenario('4000000001');
    lpiBuildLeaveProcessDefinition($company, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    expect($leave->leave_status)->toBe(LeaveStatus::Pending);

    $instance = ProcessInstance::withoutGlobalScopes()
        ->where('subject_type', Leave::class)
        ->where('subject_id', $leave->id)
        ->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe(ProcessStatus::InProgress);

    expect(fn () => app(ApproveLeave::class)->handle($leave, $admin))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RejectLeave::class)->handle($leave, $admin))
        ->toThrow(ValidationException::class);

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Pending);
});

it('approves a short leave (<=5 days) through a single supervisor approval and marks it approved via the real HR Action', function () {
    [$company, $admin, $supervisor, , $employee] = lpiScenario('4000000002');
    lpiBuildLeaveProcessDefinition($company, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    expect($leave->days_count)->toBeLessThanOrEqual(5);

    app(ApproveProcessStep::class)->handle(
        ProcessInstance::withoutGlobalScopes()->where('subject_id', $leave->id)->firstOrFail(),
        $supervisor,
    );

    $freshLeave = $leave->fresh();
    expect($freshLeave->leave_status)->toBe(LeaveStatus::Approved)
        ->and($freshLeave->approved_by_user_id)->toBe($supervisor->id);

    $instance = ProcessInstance::withoutGlobalScopes()->where('subject_id', $leave->id)->firstOrFail();
    expect($instance->status)->toBe(ProcessStatus::Approved);
});

it('escalates a long leave (>5 days) to the senior approval step before approving it', function () {
    [$company, $admin, $supervisor, $seniorManager, $employee] = lpiScenario('4000000003');
    $chain = lpiBuildLeaveProcessDefinition($company, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-10'],
        $admin,
        RecordedBy::Admin,
    );

    expect($leave->days_count)->toBeGreaterThan(5);

    $instance = ProcessInstance::withoutGlobalScopes()->where('subject_id', $leave->id)->firstOrFail();
    app(ApproveProcessStep::class)->handle($instance, $supervisor);

    $instance->refresh();
    expect($instance->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->current_step_id)->toBe($chain['seniorApproval']->id);
    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Pending);

    app(ApproveProcessStep::class)->handle($instance, $seniorManager);

    $freshLeave = $leave->fresh();
    expect($freshLeave->leave_status)->toBe(LeaveStatus::Approved)
        ->and($freshLeave->approved_by_user_id)->toBe($seniorManager->id);
    expect($instance->fresh()->status)->toBe(ProcessStatus::Approved);
});

it('rejects a leave immediately when the supervisor rejects it, via the real RejectLeave Action', function () {
    [$company, $admin, $supervisor, , $employee] = lpiScenario('4000000004');
    lpiBuildLeaveProcessDefinition($company, $admin);

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    $instance = ProcessInstance::withoutGlobalScopes()->where('subject_id', $leave->id)->firstOrFail();
    app(RejectProcessStep::class)->handle($instance, $supervisor, 'با توجه به حجم پروژه فعلی امکان‌پذیر نیست.');

    $freshLeave = $leave->fresh();
    expect($freshLeave->leave_status)->toBe(LeaveStatus::Rejected)
        ->and($freshLeave->approved_by_user_id)->toBe($supervisor->id)
        ->and($freshLeave->rejection_reason)->toBe('با توجه به حجم پروژه فعلی امکان‌پذیر نیست.');

    expect($instance->fresh()->status)->toBe(ProcessStatus::Rejected);
});

it('regression: a company without an active leave process definition keeps the old direct approve/reject behavior unchanged', function () {
    [$company, $admin, , , $employee] = lpiScenario('4000000005');
    // عمداً هیچ ProcessDefinition ای برای این شرکت ساخته نمی‌شود.

    $leave = app(RequestLeave::class)->handle(
        $employee,
        ['leave_type' => 'annual', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        $admin,
        RecordedBy::Admin,
    );

    expect($leave->leave_status)->toBe(LeaveStatus::Pending);
    expect(ProcessInstance::withoutGlobalScopes()->where('subject_id', $leave->id)->exists())->toBeFalse();

    app(ApproveLeave::class)->handle($leave, $admin);

    expect($leave->fresh()->leave_status)->toBe(LeaveStatus::Approved);
});
