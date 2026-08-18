<?php

use App\Livewire\Process\ProcessDefinitionForm;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessStep;
use Livewire\Livewire;

/**
 * ویزارد ۵مرحله‌ای ProcessDefinitionForm — توابع global محلی با پیشوند pdw
 * (process-definition-wizard) تا با نام‌های مشابه در دیگر فایل‌های تست همین
 * ماژول تداخل نکنند.
 */
function pdwRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pdwGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => pdwRole($roleName)->id,
    ]);
}

function pdwActingAsHoldingAdmin(): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pdw-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    pdwGiveRole($user, $company, 'holding_admin');

    test()->actingAs($user);
    app(CompanyContext::class)->set($company->id);

    return [$company, $user];
}

it('does not advance past step 1 when the name is empty', function () {
    pdwActingAsHoldingAdmin();

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', '')
        ->call('nextStep');

    expect($component->get('currentStep'))->toBe(1)
        ->and($component->get('stepErrors'))->not->toBeEmpty();
});

it('advances to step 2 for a free-form process and to step 3 directly for a module-connected one', function () {
    pdwActingAsHoldingAdmin();

    $freeForm = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند آزاد تستی')
        ->call('nextStep');

    expect($freeForm->get('currentStep'))->toBe(2);

    $connected = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند وصل‌به‌ماژول تستی')
        ->set('subjectType', Leave::class)
        ->call('nextStep');

    expect($connected->get('currentStep'))->toBe(3);
});

it('rejects duplicate labels in the request form fields step for a free-form process', function () {
    pdwActingAsHoldingAdmin();

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند آزاد تستی')
        ->call('nextStep') // -> step 2
        ->call('addRequestField')
        ->call('addRequestField')
        ->set('requestFormFields.0.label', 'مبلغ')
        ->set('requestFormFields.1.label', 'مبلغ')
        ->call('nextStep');

    expect($component->get('currentStep'))->toBe(2)
        ->and($component->get('stepErrors'))->not->toBeEmpty();
});

it('does not advance past step 3 when an approval step has no assignment', function () {
    pdwActingAsHoldingAdmin();

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند تستی')
        ->set('subjectType', Leave::class)
        ->call('nextStep') // -> step 3 (module-connected skips step 2)
        ->call('addStep'); // approval step with no assignment_type

    expect($component->get('currentStep'))->toBe(3);

    $component->call('nextStep');

    expect($component->get('currentStep'))->toBe(3)
        ->and($component->get('stepErrors'))->not->toBeEmpty();
});

it('does not advance past step 4 when a step is missing a required transition target', function () {
    pdwActingAsHoldingAdmin();

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند تستی')
        ->call('nextStep') // step 2 (free-form)
        ->call('nextStep'); // step 3

    // یک مرحله‌ی پایان اضافه می‌کنیم تا مرحله ۳ معتبر شود (حداقل یک end لازم است).
    $component->call('addStep');
    $component->set('steps.1.step_type', StepType::End->value);

    $component->call('nextStep'); // -> step 4 موفق (فقط بررسی ساختار مرحله ۳)
    expect($component->get('currentStep'))->toBe(4);

    $component->call('nextStep'); // تلاش برای رفتن به ۵ — start هنوز مرحله‌ی بعد ندارد

    expect($component->get('currentStep'))->toBe(4)
        ->and($component->get('stepErrors'))->not->toBeEmpty();
});

/**
 * زنجیره‌ی کامل و معتبر شش‌مرحله‌ای وصل‌شده به Leave را روی یک کامپوننت
 * ویزارد از طریق set() مستقیم می‌سازد و آن را تا مرحله ۴ جلو می‌برد — برای
 * تست‌هایی که فقط به رفتار مرحله ۴/۵ (نه کل مسیر nextStep) نیاز دارند.
 */
function pdwBuildValidWizardToStep4(): \Livewire\Features\SupportTesting\Testable
{
    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'تأیید مرخصی ویزارد تستی')
        ->set('subjectType', Leave::class)
        ->call('nextStep'); // -> step 3

    $steps = $component->get('steps');
    $startKey = $steps[0]['step_key'];

    $component->call('addStep');
    $steps = $component->get('steps');
    $approvalKey = $steps[1]['step_key'];
    $component->set('steps.1.name', 'تأیید سرپرست')
        ->set('steps.1.assignment_type', AssignmentType::Role->value)
        ->set('steps.1.assigned_role', 'accountant');

    $component->call('addStep');
    $steps = $component->get('steps');
    $endKey = $steps[2]['step_key'];
    $component->set('steps.2.name', 'پایان')
        ->set('steps.2.step_type', StepType::End->value);

    $component->call('addStep');
    $steps = $component->get('steps');
    $endRejectedKey = $steps[3]['step_key'];
    $component->set('steps.3.name', 'پایان ردشده')
        ->set('steps.3.step_type', StepType::End->value);

    $component->call('nextStep'); // -> step 4

    $component
        ->set('transitionSelections.'.$startKey.'.next', $approvalKey)
        ->set('transitionSelections.'.$approvalKey.'.approved', $endKey)
        ->set('transitionSelections.'.$approvalKey.'.rejected', $endRejectedKey);

    return $component;
}

it('returns the user to the exact step that failed final graph validation, with a specific message', function () {
    pdwActingAsHoldingAdmin();

    $component = pdwBuildValidWizardToStep4();

    // مقصد «رد شد» را عمداً حذف می‌کنیم — این خطا مربوط به مرحله ۴ (گذارها) است.
    $steps = $component->get('steps');
    $approvalKey = $steps[1]['step_key'];
    $component->set('transitionSelections.'.$approvalKey.'.rejected', '');

    $component->call('nextStep'); // -> step 5
    $component->call('save');

    expect($component->get('currentStep'))->toBe(4)
        ->and($component->get('graphErrors'))->not->toBeEmpty()
        ->and(collect($component->get('graphErrors'))->contains(fn ($m) => str_contains($m, 'گذار')))->toBeTrue();

    expect(ProcessDefinition::where('name', 'تأیید مرخصی ویزارد تستی')->exists())->toBeFalse();
});

it('drag reordering transitions only changes display_order, never the actual execution result', function () {
    pdwActingAsHoldingAdmin();

    $component = pdwBuildValidWizardToStep4();

    $steps = $component->get('steps');
    $startKey = $steps[0]['step_key'];
    $approvalKey = $steps[1]['step_key'];

    // ترتیب پیش‌فرض: start سپس approval. جابه‌جا می‌کنیم تا approval اول بیاید.
    $originalOrder = $component->get('transitionOrder');
    expect($originalOrder[0])->toBe($startKey);

    $component->call('moveTransitionRow', $approvalKey, 0);

    $newOrder = $component->get('transitionOrder');
    expect($newOrder[0])->toBe($approvalKey)
        ->and($newOrder[1])->toBe($startKey);

    // مقصدهای واقعی گذارها دست‌نخورده مانده‌اند — reorder فقط نمایش را عوض کرده.
    expect($component->get('transitionSelections.'.$startKey.'.next'))->not->toBeEmpty()
        ->and($component->get('transitionSelections.'.$approvalKey.'.approved'))->not->toBeEmpty();

    $component->call('nextStep')->call('save');

    $definition = ProcessDefinition::where('name', 'تأیید مرخصی ویزارد تستی')->firstOrFail();

    $startStep = $definition->steps()->where('step_type', StepType::Start->value)->first();
    $approvalStep = $definition->steps()->where('step_type', StepType::Approval->value)->first();

    $startTransition = $startStep->outgoingTransitions()->first();
    $approvalTransition = $approvalStep->outgoingTransitions()->where('on_result', TransitionResult::Approved->value)->first();

    // display_order گذارها بازتاب ترتیب درگ‌شده است (approval قبل از start)...
    expect($approvalTransition->display_order)->toBeLessThan($startTransition->display_order);

    // ...ولی نتیجه‌ی واقعی اجرا (from_step_id/on_result → to_step_id) عوض نشده:
    // شروع هنوز به همان مرحله‌ی تأیید می‌رود.
    expect($startTransition->to_step_id)->toBe($approvalStep->id);
});

it('shows the simple locked form (no wizard) when editing a definition with history, and the full wizard when editing one without history', function () {
    [$company, $admin] = pdwActingAsHoldingAdmin();

    $definitionWithoutHistory = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'فرایند بدون سابقه',
        'process_key' => 'pdw_no_history_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    Livewire::test(ProcessDefinitionForm::class, ['processDefinition' => $definitionWithoutHistory->id])
        ->assertSee('۱. اطلاعات پایه')
        ->assertDontSee('این فرایند سابقه‌ی اجرا دارد');

    $definitionWithHistory = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'فرایند دارای سابقه',
        'process_key' => 'pdw_with_history_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    $startStep = $definitionWithHistory->steps()->where('step_type', StepType::Start->value)->first();

    ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $definitionWithHistory->id,
        'subject_type' => null,
        'subject_id' => null,
        'current_step_id' => $startStep->id,
        'status' => ProcessStatus::Approved->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    Livewire::test(ProcessDefinitionForm::class, ['processDefinition' => $definitionWithHistory->id])
        ->assertSee('این فرایند سابقه‌ی اجرا دارد')
        ->assertDontSee('۱. اطلاعات پایه')
        ->assertDontSee('۳. مراحل');
});

it('pre-fills the full wizard with existing values when editing a definition without history', function () {
    [$company, $admin] = pdwActingAsHoldingAdmin();

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'فرایند قابل‌ویرایش کامل',
        'process_key' => 'pdw_editable_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    $component = Livewire::test(ProcessDefinitionForm::class, ['processDefinition' => $definition->id]);

    expect($component->get('name'))->toBe('فرایند قابل‌ویرایش کامل')
        ->and($component->get('steps'))->toHaveCount(2)
        ->and($component->get('maxReachedStep'))->toBe(5);

    // چون maxReachedStep=5 است، رفتن مستقیم به هر مرحله (مثلاً بازبینی) آزاد است.
    $component->call('goToStep', 5);
    expect($component->get('currentStep'))->toBe(5);
});
