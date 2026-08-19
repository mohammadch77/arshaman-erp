<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Exceptions\ProcessCycleDetectedException;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * از توابع global همنام‌شده در ProcessDefinitionTest.php عمداً استفاده نمی‌کند
 * (تا با ترتیب require شدن فایل‌های تست کوپل نشود) — به‌جایش کلوژرهای محلی.
 */
function engineTestRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function engineTestGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => engineTestRole($roleName)->id,
    ]);
}

/**
 * دقیقاً همان زنجیره‌ی start→approval→condition→(دو مسیر)→end که
 * ProcessSampleSeeder می‌سازد، ولی مستقیم روی دیتابیس تست (sqlite) — بدون
 * وابستگی به اجرای seeder واقعی روی MySQL.
 *
 * @return array{definition: ProcessDefinition, start: ProcessStep, approval: ProcessStep, condition: ProcessStep, endApproved: ProcessStep, endRejected: ProcessStep}
 */
function engineTestBuildSampleChain(Company $company, User $creator, string $conditionOperator = '>', string $conditionValue = '1000000'): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست نمونه (تستی)',
        'process_key' => 'sample_free_request_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'title',
        'label' => 'عنوان درخواست',
        'field_type' => 'text',
        'is_required' => true,
        'display_order' => 0,
    ]);

    $amountField = ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'amount',
        'label' => 'مبلغ درخواستی',
        'field_type' => 'text',
        'is_required' => true,
        'display_order' => 1,
    ]);

    $start = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'start',
        'name' => 'شروع',
        'step_type' => StepType::Start,
    ]);

    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'manager_approval',
        'name' => 'تأیید مدیر',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
    ]);

    $condition = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'amount_check',
        'name' => 'بررسی مبلغ',
        'step_type' => StepType::Condition,
        'condition_field_id' => $amountField->id,
        'condition_operator' => ConditionOperator::from($conditionOperator),
        'condition_value' => $conditionValue,
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

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $condition->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $endApproved->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::ConditionFalse]);

    return compact('definition', 'start', 'approval', 'condition', 'endApproved', 'endRejected');
}

function engineTestCompanyWithAdmin(): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    engineTestGiveRole($admin, $company, 'holding_admin');

    return [$company, $admin];
}

it('starts an instance and stops at the first real approval step', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin);

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['title' => 'درخواست ۱', 'amount' => '2000000']);

    expect($instance->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->current_step_id)->toBe($chain['approval']->id);

    $logs = $instance->logs()->orderBy('created_at')->orderBy('id')->get();
    expect($logs)->toHaveCount(1)
        ->and($logs->first()->action)->toBe(LogAction::Started)
        ->and($logs->first()->step_id)->toBe($chain['start']->id)
        ->and($logs->first()->actor_user_id)->toBe($admin->id);
});

it('approves the approval step as the assigned role, auto-evaluates the condition, and reaches the approved end', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin);

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['title' => 'درخواست بالا', 'amount' => '2000000']);

    app(ApproveProcessStep::class)->handle($instance, $admin, 'تأیید شد، مبلغ منطقی است');
    $instance->refresh();

    expect($instance->status)->toBe(ProcessStatus::Approved)
        ->and($instance->current_step_id)->toBe($chain['endApproved']->id)
        ->and($instance->completed_at)->not->toBeNull();

    $actions = $instance->logs()->orderBy('created_at')->orderBy('id')->pluck('action');
    expect($actions->map(fn ($a) => $a->value)->all())->toBe([
        'started', 'approved', 'condition_evaluated', 'completed',
    ]);
});

it('rejects the approval step and moves straight to the rejected end, skipping the condition entirely', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin);

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['title' => 'درخواست', 'amount' => '2000000']);

    app(RejectProcessStep::class)->handle($instance, $admin, 'رد شد');
    $instance->refresh();

    expect($instance->status)->toBe(ProcessStatus::Rejected)
        ->and($instance->current_step_id)->toBe($chain['endRejected']->id);

    $actions = $instance->logs()->orderBy('created_at')->orderBy('id')->pluck('action');
    expect($actions->map(fn ($a) => $a->value)->all())->toBe(['started', 'rejected', 'completed'])
        ->and($instance->logs()->where('action', 'condition_evaluated')->exists())->toBeFalse();
});

it('rejects approval from a user who has neither the assigned role nor is the assigned user', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin);

    $outsider = User::factory()->create(['is_super_admin' => false]);
    engineTestGiveRole($outsider, $company, 'operator');

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['title' => 'درخواست', 'amount' => '2000000']);

    expect(fn () => app(ApproveProcessStep::class)->handle($instance, $outsider))
        ->toThrow(AuthorizationException::class);

    $instance->refresh();
    expect($instance->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->current_step_id)->toBe($chain['approval']->id);
});

it('allows approval by the specifically assigned user even without the role', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin);

    $assignee = User::factory()->create(['is_super_admin' => false]);
    engineTestGiveRole($assignee, $company, 'operator');

    $chain['approval']->update([
        'assignment_type' => AssignmentType::SpecificUser,
        'assigned_role' => null,
        'assigned_user_id' => $assignee->id,
    ]);

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['title' => 'درخواست', 'amount' => '2000000']);

    app(ApproveProcessStep::class)->handle($instance, $assignee);
    $instance->refresh();

    expect($instance->status)->toBe(ProcessStatus::Approved)
        ->and($instance->current_step_id)->toBe($chain['endApproved']->id);
});

it('evaluates condition_true when the amount is above the threshold', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin, '>', '1000000');

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['amount' => '5000000']);
    app(ApproveProcessStep::class)->handle($instance, $admin);
    $instance->refresh();

    expect($instance->current_step_id)->toBe($chain['endApproved']->id)
        ->and($instance->status)->toBe(ProcessStatus::Approved);
});

it('evaluates condition_false when the amount is below the threshold', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();
    $chain = engineTestBuildSampleChain($company, $admin, '>', '1000000');

    $engine = app(ProcessEngine::class);
    $instance = $engine->startInstance($chain['definition'], $admin, requestData: ['amount' => '500']);
    app(ApproveProcessStep::class)->handle($instance, $admin);
    $instance->refresh();

    expect($instance->current_step_id)->toBe($chain['endRejected']->id)
        ->and($instance->status)->toBe(ProcessStatus::Rejected);
});

it('detects a real cycle in the process graph and throws instead of looping forever', function () {
    [$company, $admin] = engineTestCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'گراف چرخه‌دار (تستی)',
        'process_key' => 'cyclic_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $amountField = ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'amount',
        'label' => 'مبلغ',
        'field_type' => 'text',
        'is_required' => true,
        'display_order' => 0,
    ]);

    $start = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'start',
        'name' => 'شروع',
        'step_type' => StepType::Start,
    ]);

    $conditionA = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'cond_a',
        'name' => 'شرط الف',
        'step_type' => StepType::Condition,
        'condition_field_id' => $amountField->id,
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '0',
    ]);

    $conditionB = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'cond_b',
        'name' => 'شرط ب',
        'step_type' => StepType::Condition,
        'condition_field_id' => $amountField->id,
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '0',
    ]);

    // شروع -> شرط الف -> (اگر true) شرط ب -> (اگر true) دوباره شرط الف: چرخه‌ی واقعی.
    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $conditionA->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $conditionA->id, 'to_step_id' => $conditionB->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $conditionB->id, 'to_step_id' => $conditionA->id, 'on_result' => TransitionResult::ConditionTrue]);

    $engine = app(ProcessEngine::class);

    expect(fn () => $engine->startInstance($definition, $admin, requestData: ['amount' => '10']))
        ->toThrow(ProcessCycleDetectedException::class);
});
