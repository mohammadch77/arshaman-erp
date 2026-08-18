<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Actions\CreateProcessDefinitionVersion;
use App\Modules\Process\Actions\UpdateProcessDefinition;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Validation\ValidationException;

function designerRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function designerGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => designerRole($roleName)->id,
    ]);
}

function designerActingAs(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    designerGiveRole($user, $company, $roleName);

    test()->actingAs($user);

    return [$user, $company];
}

/**
 * زنجیره‌ی معتبر شش‌مرحله‌ای وصل‌شده به Leave (طبق whitelist واقعی
 * config/processes.php): start → approval → condition(days_count<=5) →
 * end_approved مستقیم، یا شاخه‌ی senior_approval → end_approved/end_rejected.
 */
function designerValidLeavePayload(): array
{
    return [
        'name' => 'تأیید مرخصی تستی',
        'process_key' => 'leave_designer_test_'.uniqid(),
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'first_approval', 'name' => 'تأیید اول', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'accountant'],
            ['step_key' => 'duration_check', 'name' => 'بررسی مدت', 'step_type' => StepType::Condition->value, 'condition_field' => 'days_count', 'condition_operator' => '<=', 'condition_value' => '5'],
            ['step_key' => 'senior_approval', 'name' => 'تأیید ارشد', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'holding_admin'],
            ['step_key' => 'end_approved', 'name' => 'پایان تأییدشده', 'step_type' => StepType::End->value],
            ['step_key' => 'end_rejected', 'name' => 'پایان ردشده', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'first_approval', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'first_approval', 'to_step_key' => 'duration_check', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'first_approval', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
            ['from_step_key' => 'duration_check', 'to_step_key' => 'end_approved', 'on_result' => TransitionResult::ConditionTrue->value],
            ['from_step_key' => 'duration_check', 'to_step_key' => 'senior_approval', 'on_result' => TransitionResult::ConditionFalse->value],
            ['from_step_key' => 'senior_approval', 'to_step_key' => 'end_approved', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'senior_approval', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
        ],
    ];
}

it('saves a complete process definition with steps and transitions in one transaction', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, designerValidLeavePayload());

    expect($definition->exists)->toBeTrue();

    $steps = ProcessStep::where('process_definition_id', $definition->id)->get()->keyBy('step_key');
    expect($steps)->toHaveCount(6);

    $transitions = ProcessTransition::whereIn('from_step_id', $steps->pluck('id'))->get();
    expect($transitions)->toHaveCount(7);

    expect($steps['duration_check']->condition_field)->toBe('days_count')
        ->and($steps['first_approval']->assigned_role)->toBe('accountant');
});

it('rejects a definition where an approval step is missing an outgoing transition', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $payload = designerValidLeavePayload();
    $payload['process_key'] = 'incomplete_'.uniqid();
    // حذف گذار «رد شد» مرحله‌ی first_approval — مسیر رد بدون مقصد می‌ماند.
    $payload['transitions'] = array_values(array_filter(
        $payload['transitions'],
        fn ($t) => ! ($t['from_step_key'] === 'first_approval' && $t['on_result'] === TransitionResult::Rejected->value)
    ));

    expect(fn () => app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload))
        ->toThrow(ValidationException::class);

    expect(ProcessDefinition::where('process_key', $payload['process_key'])->exists())->toBeFalse();
});

it('rejects a condition step whose field is outside the config whitelist', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $payload = designerValidLeavePayload();
    $payload['process_key'] = 'bad_field_'.uniqid();
    $payload['steps'][2]['condition_field'] = 'not_a_whitelisted_field';

    expect(fn () => app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload))
        ->toThrow(ValidationException::class);
});

it('rejects a definition with an orphan step unreachable from start', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $payload = designerValidLeavePayload();
    $payload['process_key'] = 'orphan_'.uniqid();
    $payload['steps'][] = ['step_key' => 'orphan', 'name' => 'یتیم', 'step_type' => StepType::End->value];

    expect(fn () => app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload))
        ->toThrow(ValidationException::class);
});

it('rejects a definition whose graph contains a cycle', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $payload = designerValidLeavePayload();
    $payload['process_key'] = 'cycle_'.uniqid();
    // شاخه‌ی condition_false را به‌جای senior_approval به first_approval برمی‌گرداند — چرخه.
    foreach ($payload['transitions'] as &$transition) {
        if ($transition['from_step_key'] === 'duration_check' && $transition['on_result'] === TransitionResult::ConditionFalse->value) {
            $transition['to_step_key'] = 'first_approval';
        }
    }
    unset($transition);

    expect(fn () => app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload))
        ->toThrow(ValidationException::class);
});

it('denies operator/accountant/viewer access to the designer panel', function () {
    foreach (['operator', 'accountant', 'viewer'] as $role) {
        designerActingAs($role);

        test()->get('/processes')->assertForbidden();
        test()->get('/processes/create')->assertForbidden();
    }
});

it('allows holding_admin to access the designer panel', function () {
    designerActingAs('holding_admin');

    test()->get('/processes')->assertOk();
    test()->get('/processes/create')->assertOk();
});

it('blocks structural edits once a definition has an in-progress instance, but still allows renaming/toggling', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, designerValidLeavePayload());
    $originalStepCount = $definition->steps()->count();

    $startStep = $definition->steps()->where('step_type', StepType::Start->value)->first();

    ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $definition->id,
        'subject_type' => null,
        'subject_id' => null,
        'current_step_id' => $startStep->id,
        'status' => ProcessStatus::InProgress->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
    ]);

    $attemptedPayload = designerValidLeavePayload();
    $attemptedPayload['name'] = 'نام تغییریافته';
    $attemptedPayload['is_active'] = false;
    $attemptedPayload['steps'] = [
        ['step_key' => 'only_start', 'name' => 'فقط شروع', 'step_type' => StepType::Start->value],
    ];
    $attemptedPayload['transitions'] = [];

    $updated = app(UpdateProcessDefinition::class)->handle($admin, $definition->fresh(), $attemptedPayload);

    expect($updated->name)->toBe('نام تغییریافته')
        ->and($updated->is_active)->toBeFalse()
        ->and($updated->steps()->count())->toBe($originalStepCount);
});

/**
 * بخش ۴.۲ Session جاری — وقتی تعریف فقط instance تمام‌شده دارد (بدون هیچ
 * در‌جریان)، UpdateProcessDefinition دیگر صدا زده نمی‌شود؛ CreateProcessDefinitionVersion
 * یک رکورد کاملاً جدید می‌سازد و نسخه‌ی قدیمی را is_current_version=false می‌کند،
 * بدون این‌که تاریخچه‌ی نسخه‌ی قدیمی (instance/logsش) تغییر کند.
 */
it('creates a brand-new version when a definition only has finished instances, leaving the old version and its history untouched', function () {
    [$admin, $company] = designerActingAs('holding_admin');

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, designerValidLeavePayload());
    $originalStepCount = $definition->steps()->count();

    $startStep = $definition->steps()->where('step_type', StepType::Start->value)->first();

    $finishedInstance = ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $definition->id,
        'subject_type' => null,
        'subject_id' => null,
        'current_step_id' => $startStep->id,
        'status' => ProcessStatus::Approved->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $newPayload = designerValidLeavePayload();
    $newPayload['name'] = 'نام نسخه‌ی جدید';

    $newVersion = app(CreateProcessDefinitionVersion::class)->handle($admin, $definition->fresh(), $newPayload);

    expect($newVersion->id)->not->toBe($definition->id)
        ->and($newVersion->process_key)->toBe($definition->process_key)
        ->and($newVersion->version)->toBe(2)
        ->and($newVersion->is_current_version)->toBeTrue()
        ->and($newVersion->name)->toBe('نام نسخه‌ی جدید');

    $definition->refresh();
    expect($definition->is_current_version)->toBeFalse()
        ->and($definition->version)->toBe(1)
        ->and($definition->steps()->count())->toBe($originalStepCount);

    $finishedInstance->refresh();
    expect($finishedInstance->process_definition_id)->toBe($definition->id)
        ->and($finishedInstance->current_step_id)->toBe($startStep->id);
});
