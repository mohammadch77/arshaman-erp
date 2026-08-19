<?php

use App\Livewire\Process\MyProcessRequests;
use App\Livewire\Process\MyProcessTasks;
use App\Livewire\Process\NewProcessRequest;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Services\ProcessFormFieldResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * صندوق کارهای من / درخواست جدید / درخواست‌های من — آخرین قطعه‌ی نقشه‌راه
 * ماژول Process. توابع global محلی با پیشوند mpp (my-process-panels) تا با
 * نام‌های مشابه در ProcessEngineTest.php/ProcessDefinitionDesignerTest.php/
 * LeaveProcessIntegrationTest.php تداخل نکنند.
 */
function mppRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function mppGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => mppRole($roleName)->id,
    ]);
}

function mppUserWithRole(Company $company, string $roleName): User
{
    $user = User::factory()->create(['is_super_admin' => false]);
    mppGiveRole($user, $company, $roleName);

    return $user;
}

/**
 * تعریف فرایند آزاد ساده: start → approval(role=accountant) → end.
 */
function mppFreeFormDefinition(Company $company, User $creator, array $requestFormFields = []): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست آزاد تستی',
        'process_key' => 'mpp_free_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    foreach (array_values($requestFormFields) as $order => $field) {
        ProcessFormField::create([
            'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
            'formable_id' => $definition->id,
            'field_key' => $field['key'],
            'label' => $field['label'],
            'field_type' => $field['type'],
            'is_required' => $field['type'] !== 'boolean',
            'display_order' => $order,
        ]);
    }

    $start = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'start',
        'name' => 'شروع',
        'step_type' => StepType::Start,
    ]);

    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'approval',
        'name' => 'تأیید حسابدار',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'accountant',
    ]);

    $end = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'end',
        'name' => 'پایان',
        'step_type' => StepType::End,
    ]);

    $endRejected = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'end_rejected',
        'name' => 'پایان — ردشده',
        'step_type' => StepType::End,
    ]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    return compact('definition', 'start', 'approval', 'end', 'endRejected');
}

it('shows a task in my process tasks only to a user whose role matches the current step assignment, not to others', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = mppUserWithRole($company, 'accountant');
    $operator = mppUserWithRole($company, 'operator');

    $chain = mppFreeFormDefinition($company, $admin);

    app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $this->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->assertSee('درخواست آزاد تستی');

    $this->actingAs($operator);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->assertDontSee('درخواست آزاد تستی');
});

it('approves a task from the my-tasks panel and actually advances the instance through the real ProcessEngine', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = mppUserWithRole($company, 'accountant');

    $chain = mppFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $this->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->set('comment', 'تأیید شد از پنل.')
        ->call('approve');

    expect($instance->fresh()->status)->toBe(ProcessStatus::Approved);

    Livewire::test(MyProcessTasks::class)->assertDontSee('درخواست آزاد تستی');
});

it('rejects a task from the my-tasks panel and advances it to rejected via the real engine', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = mppUserWithRole($company, 'accountant');

    $chain = mppFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $this->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->call('reject');

    expect($instance->fresh()->status)->toBe(ProcessStatus::Rejected);
});

it('blocks a user with no access to the current step from approving/rejecting directly, even bypassing the UI', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $stranger = mppUserWithRole($company, 'viewer');

    $chain = mppFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    expect(fn () => app(ApproveProcessStep::class)->handle($instance, $stranger))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(RejectProcessStep::class)->handle($instance, $stranger))
        ->toThrow(AuthorizationException::class);

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress);
});

it('blocks approve/reject through the my-tasks Livewire component itself, not just hides the button', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $stranger = mppUserWithRole($company, 'viewer');

    $chain = mppFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $this->actingAs($stranger);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->call('approve')
        ->assertForbidden();

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress);
});

it('submits a free-form process request from New Process Request and actually starts and runs an instance', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $requester = mppUserWithRole($company, 'operator');

    $chain = mppFreeFormDefinition($company, $admin, [
        ['key' => 'topic', 'label' => 'موضوع', 'type' => 'text'],
        ['key' => 'days', 'label' => 'تعداد روز', 'type' => 'number'],
    ]);

    $this->actingAs($requester);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(NewProcessRequest::class)
        ->call('selectDefinition', $chain['definition']->id)
        ->set('formValues.topic', 'درخواست تجهیزات')
        ->set('formValues.days', 3)
        ->call('submit');

    $instance = ProcessInstance::where('process_definition_id', $chain['definition']->id)
        ->where('started_by_user_id', $requester->id)
        ->firstOrFail();

    $values = app(ProcessFormFieldResolver::class)->valuesForInstance($instance);

    expect($instance->status)->toBe(ProcessStatus::InProgress)
        ->and($values)->toMatchArray(['topic' => 'درخواست تجهیزات', 'days' => '3'])
        ->and($instance->current_step_id)->toBe($chain['approval']->id);
});

it('never lists a subject-type-linked (module-connected) process definition in New Process Request', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $requester = mppUserWithRole($company, 'operator');

    ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تأیید مرخصی (وصل به ماژول)',
        'process_key' => 'mpp_leave_'.uniqid(),
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    mppFreeFormDefinition($company, $admin);

    $this->actingAs($requester);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(NewProcessRequest::class)
        ->assertSee('درخواست آزاد تستی')
        ->assertDontSee('تأیید مرخصی (وصل به ماژول)');
});

it('shows my-process-requests only for instances the current user started, with their full log history', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'mpp-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $requester = mppUserWithRole($company, 'operator');
    $otherRequester = mppUserWithRole($company, 'operator');
    $accountant = mppUserWithRole($company, 'accountant');

    $chain = mppFreeFormDefinition($company, $admin);

    $this->actingAs($requester);
    app(CompanyContext::class)->set($company->id);
    $myInstance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester);

    $this->actingAs($otherRequester);
    app(CompanyContext::class)->set($company->id);
    app(ProcessEngine::class)->startInstance($chain['definition'], $otherRequester);

    app(ApproveProcessStep::class)->handle($myInstance, $accountant, 'باشه، تأیید شد.');

    $this->actingAs($requester);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessRequests::class)
        ->call('openHistory', $myInstance->id)
        ->assertSee('باشه، تأیید شد.');
});

it('isolates process tasks/requests by company — a same-role user in a different company sees nothing', function () {
    $companyA = Company::create(['name' => 'شرکت الف', 'slug' => 'mpp-a-'.uniqid(), 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'شرکت ب', 'slug' => 'mpp-b-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $accountantB = mppUserWithRole($companyB, 'accountant');

    $chain = mppFreeFormDefinition($companyA, $admin);
    app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $this->actingAs($accountantB);
    app(CompanyContext::class)->set($companyB->id);

    Livewire::test(MyProcessTasks::class)->assertDontSee('درخواست آزاد تستی');
    Livewire::test(NewProcessRequest::class)->assertDontSee('درخواست آزاد تستی');
});
