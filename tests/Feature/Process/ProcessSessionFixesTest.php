<?php

use App\Livewire\Process\MyProcessTasks;
use App\Livewire\Process\ProcessDefinitionForm;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Actions\RequestLeave;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
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
use App\Modules\Process\Services\ProcessGraphValidator;
use App\Modules\Process\Support\ProcessSubjectSummary;
use App\Support\Jalali;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * تست‌های ۷ بخش این Session (رفع باگ تاریخ، شرط روی فرم آزاد، فرم اختیاری هر
 * مرحله، ایزوله‌سازی خطای موتور، کلید خودکار، ترتیب نمایش، برچسب فارسی شرط).
 * توابع global محلی با پیشوند psf تا با فایل‌های دیگر تداخل نکنند.
 */
function psfRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function psfGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => psfRole($roleName)->id,
    ]);
}

function psfCompanyWithAdmin(): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    psfGiveRole($admin, $company, 'holding_admin');

    return [$company, $admin];
}

// ===================================================================
// بخش ۱ — نمایش تاریخ در ProcessSubjectSummary
// ===================================================================

it('formats a date-cast Carbon value through Jalali instead of raw json_encode', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $employee = Employee::factory()->create(['owner_company_id' => $company->id]);

    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => LeaveType::Annual->value,
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-12',
        'reason' => 'تست',
    ], $admin, RecordedBy::Admin);

    $instance = ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => psfLeaveDefinition($company, $admin)->id,
        'subject_type' => Leave::class,
        'subject_id' => $leave->id,
        'current_step_id' => null,
        'status' => ProcessStatus::InProgress,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
    ]);

    $summary = ProcessSubjectSummary::forInstance($instance);
    $startDateItem = collect($summary)->firstWhere('label', 'از تاریخ');

    expect($startDateItem)->not->toBeNull();
    expect($startDateItem['value'])->not->toContain('timezone');
    expect($startDateItem['value'])->not->toContain('{');
    // باید دقیقاً همان چیزی باشد که تابع تبدیل شمسی موجود پروژه تولید می‌کند،
    // نه میلادی خام json_encode شده.
    expect($startDateItem['value'])->toBe(Jalali::toDisplay($leave->start_date));
});

function psfLeaveDefinition(Company $company, User $admin): ProcessDefinition
{
    return ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تأیید مرخصی (تستی برای فرمت تاریخ)',
        'process_key' => 'leave_date_fmt_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);
}

// ===================================================================
// بخش ۲ — شرط روی فیلدهای فرم خودِ فرایند آزاد
// ===================================================================

it('evaluates a condition on a free-form process using its own request_form_fields, reading from request_data', function () {
    [$company, $admin] = psfCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست آزاد با شرط (تستی)',
        'process_key' => 'free_condition_'.uniqid(),
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
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $condition = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'check_amount',
        'name' => 'بررسی مبلغ',
        'step_type' => StepType::Condition,
        'condition_field_id' => $amountField->id,
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '1000',
    ]);
    $endTrue = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_true', 'name' => 'پایان بالا', 'step_type' => StepType::End]);
    $endFalse = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_false', 'name' => 'پایان پایین', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $condition->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $endTrue->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $endFalse->id, 'on_result' => TransitionResult::ConditionFalse]);

    $engine = app(ProcessEngine::class);

    $high = $engine->startInstance($definition, $admin, requestData: ['amount' => '5000']);
    expect($high->current_step_id)->toBe($endTrue->id);

    $low = $engine->startInstance($definition, $admin, requestData: ['amount' => '10']);
    expect($low->current_step_id)->toBe($endFalse->id);
});

it('rejects a free-form condition field that is not in the definition own request_form_fields', function () {
    [$company, $admin] = psfCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست آزاد با شرط نامعتبر (تستی)',
        'process_key' => 'free_condition_bad_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [['key' => 'amount', 'type' => 'text', 'label' => 'مبلغ']],
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $condition = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'check_secret',
        'name' => 'بررسی نامعتبر',
        'step_type' => StepType::Condition,
        'condition_field' => 'not_a_field',
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '1',
    ]);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $condition->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::ConditionTrue]);
    ProcessTransition::create(['from_step_id' => $condition->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::ConditionFalse]);

    expect(fn () => app(ProcessEngine::class)->startInstance($definition, $admin, requestData: ['amount' => '5']))
        ->toThrow(RuntimeException::class);
});

it('rejects a graph where a free-form condition step points to a field outside its own request_form_fields, at design time', function () {
    $validator = app(ProcessGraphValidator::class);

    $steps = [
        ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
        ['step_key' => 'cond', 'name' => 'شرط', 'step_type' => StepType::Condition->value, 'condition_field' => 'not_defined', 'condition_operator' => '>', 'condition_value' => '1'],
        ['step_key' => 'end_true', 'name' => 'پایان الف', 'step_type' => StepType::End->value],
        ['step_key' => 'end_false', 'name' => 'پایان ب', 'step_type' => StepType::End->value],
    ];
    $transitions = [
        ['from_step_key' => 'start', 'to_step_key' => 'cond', 'on_result' => TransitionResult::Approved->value],
        ['from_step_key' => 'cond', 'to_step_key' => 'end_true', 'on_result' => TransitionResult::ConditionTrue->value],
        ['from_step_key' => 'cond', 'to_step_key' => 'end_false', 'on_result' => TransitionResult::ConditionFalse->value],
    ];

    expect(fn () => $validator->validate(null, $steps, $transitions, [['key' => 'amount', 'label' => 'مبلغ', 'type' => 'text']]))
        ->toThrow(ValidationException::class);
});

// ===================================================================
// بخش ۳ — فرم اختیاری برای هر مرحله
// ===================================================================

it('stores step_form_fields on the step and persists submitted values into the log as step_data', function () {
    [$company, $admin] = psfCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند با فرم مرحله (تستی)',
        'process_key' => 'step_form_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'title',
        'label' => 'عنوان',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'approval',
        'name' => 'تأیید',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_STEP,
        'formable_id' => $approval->id,
        'field_key' => 'approved_amount',
        'label' => 'مبلغ تأییدشده',
        'field_type' => 'number',
        'is_required' => true,
    ]);

    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Rejected]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $admin, requestData: ['title' => 'درخواست']);

    app(ApproveProcessStep::class)->handle($instance, $admin, 'تأیید شد', ['approved_amount' => '250000']);

    $log = $instance->logs()->where('step_id', $approval->id)->where('action', 'approved')->first();

    expect($log)->not->toBeNull();

    $storedValues = app(ProcessFormFieldResolver::class)->fieldsFor(ProcessFormField::FORMABLE_STEP, $approval->id);
    $fieldValue = $log->fieldValues()->where('process_form_field_id', $storedValues->get('approved_amount')->id)->first();

    expect($fieldValue?->value)->toBe('250000');
});

it('rejects submitting step data that fails validation against step_form_fields', function () {
    [$company, $admin] = psfCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند با فرم مرحله نامعتبر (تستی)',
        'process_key' => 'step_form_invalid_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'title',
        'label' => 'عنوان',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'approval',
        'name' => 'تأیید',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_STEP,
        'formable_id' => $approval->id,
        'field_key' => 'approved_amount',
        'label' => 'مبلغ تأییدشده',
        'field_type' => 'number',
        'is_required' => true,
    ]);

    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Rejected]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $admin, requestData: ['title' => 'درخواست']);

    expect(fn () => app(ApproveProcessStep::class)->handle($instance, $admin, null, ['approved_amount' => 'not-a-number']))
        ->toThrow(ValidationException::class);
});

it('shows step-form values in the MyProcessTasks comment modal fields for the current step', function () {
    [$company, $admin] = psfCompanyWithAdmin();

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند با فرم مرحله (پنل)',
        'process_key' => 'step_form_panel_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'title',
        'label' => 'عنوان',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'approval',
        'name' => 'تأیید',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_STEP,
        'formable_id' => $approval->id,
        'field_key' => 'note_field',
        'label' => 'یادداشت مخصوص',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Rejected]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $admin, requestData: ['title' => 'درخواست']);

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->assertSet('stepDataValues', ['note_field' => null])
        ->assertSee('یادداشت مخصوص');
});

// ===================================================================
// بخش ۴ — ایزوله‌سازی خطای موتور از عملیات اصلی
// ===================================================================

it('still creates the leave record even when the process engine throws internally', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $employee = Employee::factory()->create(['owner_company_id' => $company->id]);

    // یک تعریف فرایند «فعال» برای این subject_type می‌سازیم اما بدون هیچ
    // مرحله‌ی start — دقیقاً همان چیزی که ProcessEngine::startInstance() را
    // با یک RuntimeException واقعی می‌شکند.
    ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند خراب (تستی بخش ۴)',
        'process_key' => 'broken_leave_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    Log::shouldReceive('error')->once();

    $leave = app(RequestLeave::class)->handle($employee, [
        'leave_type' => LeaveType::Annual->value,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-02',
        'reason' => 'تست ایزوله‌سازی خطای موتور',
    ], $admin, RecordedBy::Admin);

    expect($leave->exists)->toBeTrue()
        ->and(Leave::find($leave->id))->not->toBeNull();
});

// ===================================================================
// بخش ۵ — کلید خودکار فیلدهای فرم
// ===================================================================

it('auto-generates a unique field key for request form fields without asking the admin', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    // دو فیلد پشت‌سرهم باید کلید متفاوت داشته باشند حتی وقتی هر دو برچسب خالی دارند —
    // کلید هرگز از یک اینپوت دستی خوانده نمی‌شود.
    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('subjectType', '')
        ->call('addRequestField')
        ->call('addRequestField')
        ->assertDontSeeHtml('label="کلید"');

    $keys = $component->get('requestFormFields');
    expect($keys[0]['key'])->not->toBe('')
        ->and($keys[0]['key'])->not->toBeNull()
        ->and($keys[1]['key'])->not->toBe('')
        ->and($keys[0]['key'])->not->toBe($keys[1]['key']);
});

it('auto-generates a unique field key for step_form_fields the same way', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->call('addStep') // step index 1 (index 0 is the default start step)
        ->set('steps.1.step_type', StepType::Approval->value)
        ->call('addStepFormField', 1)
        ->call('addStepFormField', 1);

    $fields = $component->get('steps.1.step_form_fields');
    expect($fields)->toHaveCount(2)
        ->and($fields[0]['key'])->not->toBe('')
        ->and($fields[0]['key'])->not->toBe($fields[1]['key']);
});

// ===================================================================
// بخش ۶ — حفظ ترتیب اصلی مراحل/گذارها
// ===================================================================

it('persists display_order matching creation order and reloads steps in that order', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $payload = [
        'name' => 'ترتیب تستی',
        'process_key' => 'order_test_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [['key' => 'title', 'label' => 'عنوان', 'type' => 'text']],
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'zzz_last_alphabetically', 'name' => 'ب', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'holding_admin'],
            ['step_key' => 'aaa_first_alphabetically', 'name' => 'الف', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'zzz_last_alphabetically', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'zzz_last_alphabetically', 'to_step_key' => 'aaa_first_alphabetically', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'zzz_last_alphabetically', 'to_step_key' => 'aaa_first_alphabetically', 'on_result' => TransitionResult::Rejected->value],
        ],
    ];

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload);

    $orderedKeys = $definition->fresh()->steps()->pluck('step_key')->all();

    expect($orderedKeys)->toBe(['start', 'zzz_last_alphabetically', 'aaa_first_alphabetically']);
});

// ===================================================================
// بخش ۷ — برچسب فارسی و راهنما برای فیلدهای شرط ماژول‌محور
// ===================================================================

it('shows Persian labels and hints for module-connected condition fields in the designer form', function () {
    [$company, $admin] = psfCompanyWithAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('subjectType', Leave::class);

    $options = $component->get('conditionFieldOptions');
    $hints = $component->get('conditionFieldHints');

    expect(collect($options)->pluck('label')->all())->toContain('تعداد روز مرخصی')
        ->and($hints['days_count'] ?? null)->toContain('روزهای درخواست مرخصی');
});
