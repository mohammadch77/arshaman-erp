<?php

use App\Livewire\Process\MyProcessRequests;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\CancelProcessInstance;
use App\Modules\Process\Actions\UpdateProcessInstanceRequest;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * بخش ۳ Session جاری — ویرایش/لغو درخواست توسط فرستنده قبل از اقدام.
 */
function recRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function recGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => recRole($roleName)->id,
    ]);
}

/**
 * @return array{definition: ProcessDefinition, start: ProcessStep, approval: ProcessStep, end: ProcessStep, endRejected: ProcessStep}
 */
function recFreeFormDefinition(Company $company, User $creator): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست آزاد قابل‌ویرایش',
        'process_key' => 'rec_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [
            ['key' => 'title', 'label' => 'عنوان', 'type' => 'text'],
        ],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    $endRejected = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_rejected', 'name' => 'پایان — ردشده', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    return compact('definition', 'start', 'approval', 'end', 'endRejected');
}

/**
 * رگرسیون واقعی کشف‌شده حین بازدید بصری این Session: نسخه‌ی اول Policy هر
 * لاگی (حتی لاگ خودِ ویرایش فرستنده) را «اقدام» حساب می‌کرد، پس اولین ویرایش
 * بلافاصله دکمه‌ی ویرایش/لغو را برای همیشه قفل می‌کرد. این تست دقیقاً همان
 * سناریو را چند بار تکرار می‌کند تا رگرسیون احتمالی آینده را بگیرد.
 */
it('lets the requester edit the free-form request multiple times before any approver decision', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'rec-'.uniqid(), 'business_type' => 'project_services']);
    $requester = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($requester, $company, 'operator');

    $chain = recFreeFormDefinition($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester, null, ['title' => 'عنوان اول']);

    app(UpdateProcessInstanceRequest::class)->handle($requester, $instance, ['title' => 'عنوان دوم']);
    expect($instance->fresh()->request_data['title'])->toBe('عنوان دوم');

    // دومین ویرایش هم باید مجاز بماند — نه فقط اولین بار.
    app(UpdateProcessInstanceRequest::class)->handle($requester->fresh(), $instance->fresh(), ['title' => 'عنوان سوم']);
    expect($instance->fresh()->request_data['title'])->toBe('عنوان سوم');

    // بعد از چند ویرایش، لغو هم باید همچنان مجاز باشد.
    app(CancelProcessInstance::class)->handle($requester->fresh(), $instance->fresh());
    expect($instance->fresh()->status)->toBe(ProcessStatus::Cancelled);
});

it('blocks editing and cancelling once the approver has actually approved the current step', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'rec2-'.uniqid(), 'business_type' => 'project_services']);
    $requester = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($requester, $company, 'operator');
    $approver = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($approver, $company, 'accountant');

    $chain = recFreeFormDefinition($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester, null, ['title' => 'عنوان']);

    app(ApproveProcessStep::class)->handle($instance, $approver);

    expect($instance->fresh()->status)->toBe(ProcessStatus::Approved);

    expect(fn () => app(UpdateProcessInstanceRequest::class)->handle($requester, $instance->fresh(), ['title' => 'دیگر مجاز نیست']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(CancelProcessInstance::class)->handle($requester, $instance->fresh()))
        ->toThrow(AuthorizationException::class);
});

it('blocks a user other than the original requester from editing or cancelling', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'rec3-'.uniqid(), 'business_type' => 'project_services']);
    $requester = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($requester, $company, 'operator');
    $someoneElse = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($someoneElse, $company, 'operator');

    $chain = recFreeFormDefinition($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester, null, ['title' => 'عنوان']);

    expect(fn () => app(UpdateProcessInstanceRequest::class)->handle($someoneElse, $instance, ['title' => 'دستکاری غیرمجاز']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(CancelProcessInstance::class)->handle($someoneElse, $instance))
        ->toThrow(AuthorizationException::class);
});

it('shows and uses the edit/cancel buttons through the real MyProcessRequests Livewire component', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'rec4-'.uniqid(), 'business_type' => 'project_services']);
    $requester = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($requester, $company, 'operator');

    $chain = recFreeFormDefinition($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester, null, ['title' => 'عنوان اولیه']);

    test()->actingAs($requester);

    $component = Livewire::test(MyProcessRequests::class);
    $rows = $component->instance()->requests;
    expect($rows->first()['can_edit'])->toBeTrue()
        ->and($rows->first()['can_cancel'])->toBeTrue();

    $component->call('openEditForm', $instance->id)
        ->set('editFormValues.title', 'عنوان تغییریافته از UI')
        ->call('saveEditRequest')
        ->assertHasNoErrors();

    expect($instance->fresh()->request_data['title'])->toBe('عنوان تغییریافته از UI');

    // بعد از ویرایش از طریق UI هم دکمه‌ها باید همچنان فعال بمانند (رگرسیون).
    $rowsAfterEdit = Livewire::test(MyProcessRequests::class)->instance()->requests;
    expect($rowsAfterEdit->first()['can_edit'])->toBeTrue()
        ->and($rowsAfterEdit->first()['can_cancel'])->toBeTrue();

    $component->call('cancelInstance', $instance->id)->assertHasNoErrors();

    expect($instance->fresh()->status)->toBe(ProcessStatus::Cancelled);
});

it('does not allow editing request_data for a module-linked (non free-form) process even before any decision', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'rec5-'.uniqid(), 'business_type' => 'project_services']);
    $requester = User::factory()->create(['is_super_admin' => false]);
    recGiveRole($requester, $company, 'operator');

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند وصل‌به‌ماژول',
        'process_key' => 'rec5_'.uniqid(),
        'subject_type' => \App\Modules\HR\Models\Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'created_by_user_id' => $requester->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);

    $employeeUser = User::factory()->create(['is_super_admin' => false]);
    $employee = \App\Modules\HR\Models\Employee::factory()->create([
        'owner_company_id' => $company->id,
        'user_id' => $employeeUser->id,
    ]);
    $leave = \App\Modules\HR\Models\Leave::create([
        'owner_company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'days_count' => 2,
        'reason' => 'تست',
        'leave_status' => 'pending',
        'requested_by_user_id' => $employeeUser->id,
    ]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $employeeUser, $leave);

    expect(fn () => app(UpdateProcessInstanceRequest::class)->handle($employeeUser, $instance, ['x' => 'y']))
        ->toThrow(AuthorizationException::class);

    // لغو اما برای هر دو نوع فرایند مجاز است (نه فقط آزاد).
    app(CancelProcessInstance::class)->handle($employeeUser, $instance->fresh());
    expect($instance->fresh()->status)->toBe(ProcessStatus::Cancelled);
});
