<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Services\ProcessFormFieldResolver;
use Illuminate\Support\Facades\Schema;

/**
 * بخش ۴ بازطراحی — request_data/step_data (JSON) با
 * process_instance_field_values/process_instance_log_field_values جایگزین شدند.
 */
it('no longer has request_data or step_data columns', function () {
    expect(Schema::hasColumn('process_instances', 'request_data'))->toBeFalse()
        ->and(Schema::hasColumn('process_instance_logs', 'step_data'))->toBeFalse();
});

it('stores every request field value and every step decision field value without loss', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'idm-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    $role = Role::firstOrCreate(['name' => 'holding_admin'], ['display_name' => 'holding_admin', 'is_system' => true]);
    UserCompanyRole::create(['user_id' => $admin->id, 'owner_company_id' => $company->id, 'assigned_role_id' => $role->id]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'مقادیر کامل',
        'process_key' => 'idm_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    ProcessFormField::create(['formable_type' => ProcessFormField::FORMABLE_DEFINITION, 'formable_id' => $definition->id, 'field_key' => 'title', 'label' => 'عنوان', 'field_type' => 'text', 'is_required' => true]);
    ProcessFormField::create(['formable_type' => ProcessFormField::FORMABLE_DEFINITION, 'formable_id' => $definition->id, 'field_key' => 'qty', 'label' => 'تعداد', 'field_type' => 'number', 'is_required' => true]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role, 'assigned_role' => 'holding_admin',
    ]);
    ProcessFormField::create(['formable_type' => ProcessFormField::FORMABLE_STEP, 'formable_id' => $approval->id, 'field_key' => 'approved_qty', 'label' => 'تعداد تأییدشده', 'field_type' => 'number', 'is_required' => true]);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Rejected]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $admin, requestData: ['title' => 'یک درخواست', 'qty' => '7']);

    $resolver = app(ProcessFormFieldResolver::class);
    expect($resolver->valuesForInstance($instance))->toBe(['title' => 'یک درخواست', 'qty' => '7']);

    app(ApproveProcessStep::class)->handle($instance, $admin, null, ['approved_qty' => '5']);

    $log = $instance->fresh()->logs()->where('step_id', $approval->id)->where('action', 'approved')->first();
    $logFieldValue = $log->fieldValues()->with('formField')->first();

    expect($logFieldValue->formField->field_key)->toBe('approved_qty')
        ->and($logFieldValue->value)->toBe('5');
});
