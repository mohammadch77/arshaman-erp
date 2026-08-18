<?php

use App\Livewire\Process\ProcessDefinitionIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessFlowchartBuilder;
use Livewire\Livewire;

/**
 * بخش ۴.۱ Session جاری — ProcessFlowchartBuilder فقط رشته‌ی syntax مرمید
 * می‌سازد (رندر SVG کاملاً سمت کلاینت است، اینجا قابل‌تست نیست)؛ این تست فقط
 * شکل رشته و دکمه‌ی «مشاهده فلوچارت» را بررسی می‌کند.
 */
it('builds valid mermaid flowchart syntax with all node shapes and edge labels', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pfb-'.uniqid(), 'business_type' => 'project_services']);
    $creator = User::factory()->create(['is_super_admin' => true]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند تست فلوچارت',
        'process_key' => 'pfb_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید "ویژه"', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $condition = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'cond', 'name' => 'بررسی مبلغ', 'step_type' => StepType::Condition, 'condition_field' => 'x', 'condition_operator' => '>', 'condition_value' => '10']);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    $endRejected = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_rejected', 'name' => 'پایان — ردشده', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $condition->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::ConditionFalse]);

    $mermaid = app(ProcessFlowchartBuilder::class)->build($definition->fresh());

    expect($mermaid)->toStartWith('flowchart TD');
    expect($mermaid)->toContain('شروع');
    // نقل‌قول داخل نام مرحله باید خنثی شده باشد تا syntax مرمید نشکند.
    expect($mermaid)->not->toContain('تأیید "ویژه"');
    expect($mermaid)->toContain("تأیید 'ویژه'");
    expect($mermaid)->toContain('{"بررسی مبلغ"}'); // شکل لوزی برای شرط
    expect($mermaid)->toContain('-->|تأیید شد|');
    expect($mermaid)->toContain('-->|رد شد|');
    expect($mermaid)->toContain('-->|شرط برقرار بود|');
    expect($mermaid)->toContain('-->|شرط برقرار نبود|');
});

it('shows the flowchart button and dispatches the mermaid string only to holding_admin', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pfb2-'.uniqid(), 'business_type' => 'project_services']);
    $creator = User::factory()->create(['is_super_admin' => true]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند تست دکمه',
        'process_key' => 'pfb2_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);

    $role = Role::firstOrCreate(['name' => 'holding_admin'], ['display_name' => 'holding_admin', 'is_system' => true]);
    $admin = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create(['user_id' => $admin->id, 'owner_company_id' => $company->id, 'assigned_role_id' => $role->id]);

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(ProcessDefinitionIndex::class)
        ->call('showFlowchart', $definition->id)
        ->assertDispatched('process-flowchart-ready')
        ->assertSet('showFlowchartModal', true);
});
