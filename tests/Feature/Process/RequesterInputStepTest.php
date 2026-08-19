<?php

use App\Livewire\Process\MyProcessRequests;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Actions\SubmitRequesterInput;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Services\ProcessGraphValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * مرحله‌ی جدید requester_input — یک نوع مرحله‌ی مستقل در گراف (نه ترکیب‌شده
 * با approval): فرم آن را همیشه started_by_user_id تکمیل می‌کند، بدون
 * assignment_type، و فقط یک مسیر خروجی (on_result='default'). توابع global
 * محلی با پیشوند ri (requester-input) تا با نام‌های مشابه در فایل‌های تست
 * دیگر همین ماژول تداخل نکنند.
 */
function riRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function riGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => riRole($roleName)->id,
    ]);
}

function riUserWithRole(Company $company, string $roleName): User
{
    $user = User::factory()->create(['is_super_admin' => false]);
    riGiveRole($user, $company, $roleName);

    return $user;
}

/**
 * سناریوی دقیق نقض قبلی: سرپرست (operator) → تکمیل مدرک توسط فرستنده →
 * حسابداری (accountant) چک کند → مدیر کل (holding_admin) امضا کند. ساخته‌شده
 * مستقیم با Eloquent (نه از طریق ویزارد) برای تست‌های سطح موتور.
 */
function riFullChain(Company $company, User $creator): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست تجهیزات (تستی)',
        'process_key' => 'ri_chain_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'item_name',
        'label' => 'نام تجهیز',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $start = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'start',
        'name' => 'شروع',
        'step_type' => StepType::Start,
    ]);

    $supervisor = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'supervisor_approval',
        'name' => 'تأیید سرپرست',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'operator',
    ]);

    $requesterInput = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'complete_docs',
        'name' => 'تکمیل مدرک توسط فرستنده',
        'step_type' => StepType::RequesterInput,
    ]);

    ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_STEP,
        'formable_id' => $requesterInput->id,
        'field_key' => 'doc_number',
        'label' => 'شماره مدرک',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $accounting = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'accounting_check',
        'name' => 'بررسی حسابداری',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'accountant',
    ]);

    $seniorSign = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'senior_sign',
        'name' => 'امضای مدیر کل',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => 'holding_admin',
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

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $supervisor->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $supervisor->id, 'to_step_id' => $requesterInput->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $supervisor->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $requesterInput->id, 'to_step_id' => $accounting->id, 'on_result' => TransitionResult::Default]);
    ProcessTransition::create(['from_step_id' => $accounting->id, 'to_step_id' => $seniorSign->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $accounting->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $seniorSign->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $seniorSign->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    return compact('definition', 'start', 'supervisor', 'requesterInput', 'accounting', 'seniorSign', 'end', 'endRejected');
}

it('runs a full chain through supervisor approval, requester input, accounting approval, and senior sign-off with different roles', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ri-'.uniqid(), 'business_type' => 'project_services']);
    $requester = riUserWithRole($company, 'operator');
    $supervisor = riUserWithRole($company, 'operator');
    $accountant = riUserWithRole($company, 'accountant');
    $seniorAdmin = riUserWithRole($company, 'holding_admin');

    $chain = riFullChain($company, $requester);

    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester, null, ['item_name' => 'لپ‌تاپ']);
    expect($instance->current_step_id)->toBe($chain['supervisor']->id);

    app(ApproveProcessStep::class)->handle($instance, $supervisor, 'تأیید شد.');
    expect($instance->fresh()->current_step_id)->toBe($chain['requesterInput']->id)
        ->and($instance->fresh()->status)->toBe(ProcessStatus::InProgress);

    // بعد از ارسال، خودکار (بدون هیچ اقدام اضافه) به مرحله‌ی بعد می‌رود.
    app(SubmitRequesterInput::class)->handle($instance->fresh(), $requester, ['doc_number' => 'DOC-1001']);
    expect($instance->fresh()->current_step_id)->toBe($chain['accounting']->id);

    app(ApproveProcessStep::class)->handle($instance->fresh(), $accountant, 'مدارک کامل است.');
    expect($instance->fresh()->current_step_id)->toBe($chain['seniorSign']->id);

    app(ApproveProcessStep::class)->handle($instance->fresh(), $seniorAdmin, 'امضا شد.');

    $final = $instance->fresh();
    expect($final->status)->toBe(ProcessStatus::Approved)
        ->and($final->current_step_id)->toBe($chain['end']->id);

    $log = $final->logs()->where('action', LogAction::RequesterInput->value)->first();
    $fieldValue = $log->fieldValues()->with('formField')->first();
    expect($fieldValue->formField->field_key)->toBe('doc_number')
        ->and($fieldValue->value)->toBe('DOC-1001')
        ->and($log->actor_user_id)->toBe($requester->id);
});

it('rejects submitting requester input from anyone other than the original requester, even bypassing the UI', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ri-'.uniqid(), 'business_type' => 'project_services']);
    $requester = riUserWithRole($company, 'operator');
    $supervisor = riUserWithRole($company, 'operator');
    $stranger = riUserWithRole($company, 'viewer');

    $chain = riFullChain($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester);
    app(ApproveProcessStep::class)->handle($instance, $supervisor);

    expect(fn () => app(SubmitRequesterInput::class)->handle($instance->fresh(), $stranger, ['doc_number' => 'X']))
        ->toThrow(AuthorizationException::class);

    expect($instance->fresh()->current_step_id)->toBe($chain['requesterInput']->id);
});

it('blocks submitRequesterInput through the MyProcessRequests Livewire component for a non-requester user', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ri-'.uniqid(), 'business_type' => 'project_services']);
    $requester = riUserWithRole($company, 'operator');
    $supervisor = riUserWithRole($company, 'operator');
    $stranger = riUserWithRole($company, 'viewer');

    $chain = riFullChain($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester);
    app(ApproveProcessStep::class)->handle($instance, $supervisor);

    $this->actingAs($stranger);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessRequests::class)
        ->call('openInputForm', $instance->id)
        ->assertForbidden();
});

it('lets the original requester complete the form through the MyProcessRequests panel and advances automatically', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ri-'.uniqid(), 'business_type' => 'project_services']);
    $requester = riUserWithRole($company, 'operator');
    $supervisor = riUserWithRole($company, 'operator');

    $chain = riFullChain($company, $requester);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $requester);
    app(ApproveProcessStep::class)->handle($instance, $supervisor);

    $this->actingAs($requester);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessRequests::class)
        ->assertSee('نیاز به تکمیل اطلاعات شما')
        ->call('openInputForm', $instance->id)
        ->set('inputStepDataValues.doc_number', 'DOC-9')
        ->call('submitInput');

    expect($instance->fresh()->current_step_id)->toBe($chain['accounting']->id);

    Livewire::test(MyProcessRequests::class)->assertDontSee('نیاز به تکمیل اطلاعات شما');
});

it('validates a full designer-style payload with requester_input between two different-role approvals', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ri-'.uniqid(), 'business_type' => 'project_services']);
    $admin = riUserWithRole($company, 'holding_admin');

    $payload = [
        'name' => 'گردش تجهیزات',
        'process_key' => 'ri_designer_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'first', 'name' => 'تأیید اول', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'operator'],
            ['step_key' => 'docs', 'name' => 'تکمیل مدرک', 'step_type' => StepType::RequesterInput->value, 'step_form_fields' => [['key' => 'doc', 'label' => 'مدرک', 'type' => 'text']]],
            ['step_key' => 'second', 'name' => 'تأیید دوم', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'holding_admin'],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
            ['step_key' => 'end_rejected', 'name' => 'پایان ردشده', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'first', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'first', 'to_step_key' => 'docs', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'first', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
            ['from_step_key' => 'docs', 'to_step_key' => 'second', 'on_result' => TransitionResult::Default->value],
            ['from_step_key' => 'second', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'second', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
        ],
    ];

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload);

    expect($definition->exists)->toBeTrue();
    $docsStep = ProcessStep::where('process_definition_id', $definition->id)->where('step_key', 'docs')->first();
    expect($docsStep->step_type)->toBe(StepType::RequesterInput)
        ->and($docsStep->assignment_type)->toBeNull();

    $fields = $docsStep->formFields;
    expect($fields)->toHaveCount(1)
        ->and($fields->first()->field_key)->toBe('doc')
        ->and($fields->first()->label)->toBe('مدرک')
        ->and($fields->first()->field_type)->toBe('text');
});

it('rejects a requester_input step with zero outgoing transitions', function () {
    $steps = [
        ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
        ['step_key' => 'docs', 'name' => 'تکمیل مدرک', 'step_type' => StepType::RequesterInput->value, 'step_form_fields' => [['key' => 'doc', 'label' => 'مدرک', 'type' => 'text']]],
        ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
    ];

    $transitions = [
        ['from_step_key' => 'start', 'to_step_key' => 'docs', 'on_result' => TransitionResult::Approved->value],
        // بدون هیچ گذار خروجی از docs.
    ];

    expect(fn () => app(ProcessGraphValidator::class)->validate(null, $steps, $transitions))
        ->toThrow(ValidationException::class);
});

it('rejects a requester_input step with two outgoing transitions', function () {
    $steps = [
        ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
        ['step_key' => 'docs', 'name' => 'تکمیل مدرک', 'step_type' => StepType::RequesterInput->value, 'step_form_fields' => [['key' => 'doc', 'label' => 'مدرک', 'type' => 'text']]],
        ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ['step_key' => 'end2', 'name' => 'پایان دوم', 'step_type' => StepType::End->value],
    ];

    $transitions = [
        ['from_step_key' => 'start', 'to_step_key' => 'docs', 'on_result' => TransitionResult::Approved->value],
        ['from_step_key' => 'docs', 'to_step_key' => 'end', 'on_result' => TransitionResult::Default->value],
        ['from_step_key' => 'docs', 'to_step_key' => 'end2', 'on_result' => TransitionResult::Default->value],
    ];

    expect(fn () => app(ProcessGraphValidator::class)->validate(null, $steps, $transitions))
        ->toThrow(ValidationException::class);
});

it('rejects a requester_input step with no form fields', function () {
    $steps = [
        ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
        ['step_key' => 'docs', 'name' => 'تکمیل مدرک', 'step_type' => StepType::RequesterInput->value, 'step_form_fields' => []],
        ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
    ];

    $transitions = [
        ['from_step_key' => 'start', 'to_step_key' => 'docs', 'on_result' => TransitionResult::Approved->value],
        ['from_step_key' => 'docs', 'to_step_key' => 'end', 'on_result' => TransitionResult::Default->value],
    ];

    expect(fn () => app(ProcessGraphValidator::class)->validate(null, $steps, $transitions))
        ->toThrow(ValidationException::class);
});

it('isolates process instances awaiting requester input by company', function () {
    $companyA = Company::create(['name' => 'شرکت الف', 'slug' => 'ri-a-'.uniqid(), 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'شرکت ب', 'slug' => 'ri-b-'.uniqid(), 'business_type' => 'project_services']);

    $requesterA = riUserWithRole($companyA, 'operator');
    $supervisorA = riUserWithRole($companyA, 'operator');
    $userB = riUserWithRole($companyB, 'operator');

    $chainA = riFullChain($companyA, $requesterA);
    $instance = app(ProcessEngine::class)->startInstance($chainA['definition'], $requesterA);
    app(ApproveProcessStep::class)->handle($instance, $supervisorA);

    $this->actingAs($userB);
    app(CompanyContext::class)->set($companyB->id);

    Livewire::test(MyProcessRequests::class)->assertDontSee('درخواست تجهیزات (تستی)');
});
