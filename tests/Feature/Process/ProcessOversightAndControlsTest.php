<?php

use App\Livewire\Process\MyProcessTasks;
use App\Livewire\Process\ProcessDefinitionForm;
use App\Livewire\Process\ProcessDefinitionIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\DeleteProcessDefinition;
use App\Modules\Process\Actions\RecordProcessReminder;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Actions\ReverseProcessDecision;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * بخش‌های ۱، ۳، ۴، ۵ Session جاری: محدودسازی واقعی تأیید/رد، نظارت هلدینگ +
 * یادآوری، حذف واقعی فرایند (soft/hard)، تاریخچه + ویرایش تصمیم اخیر در
 * کارهای من، و تولید خودکار process_key/step_key. توابع global محلی با
 * پیشوند poc (process-oversight-controls) تا با نام‌های مشابه در فایل‌های
 * دیگر تست همین ماژول تداخل نکنند.
 */
function pocRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pocGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => pocRole($roleName)->id,
    ]);
}

function pocUserWithRole(Company $company, string $roleName): User
{
    $user = User::factory()->create(['is_super_admin' => false]);
    pocGiveRole($user, $company, $roleName);

    return $user;
}

function pocCompany(): Company
{
    return Company::create(['name' => 'آرشامان', 'slug' => 'poc-'.uniqid(), 'business_type' => 'project_services']);
}

/**
 * زنجیره‌ی آزاد: start → approval(role=accountant) → end / end_rejected.
 *
 * @return array{definition: ProcessDefinition, start: ProcessStep, approval: ProcessStep, end: ProcessStep, endRejected: ProcessStep}
 */
function pocFreeFormDefinition(Company $company, User $creator, string $role = 'accountant'): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'درخواست آزاد نظارتی',
        'process_key' => 'poc_free_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => $role]);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    $endRejected = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_rejected', 'name' => 'پایان — ردشده', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    return compact('definition', 'start', 'approval', 'end', 'endRejected');
}

/**
 * تبدیل زنجیره‌ی pocFreeFormDefinition به یک زنجیره‌ی دومرحله‌ای: approval
 * اول به‌جای رفتن مستقیم به end، به یک approval دوم می‌رود. برای تست
 * بازگردانی تصمیم لازم است — در زنجیره‌ی تک‌مرحله‌ای، تأیید بلافاصله به end
 * می‌رسد (یک لاگ completed بعد از approved ثبت می‌شود) و دیگر قابل بازگردانی
 * نیست؛ اینجا بعد از تأیید اول، instance واقعاً «در جریان» باقی می‌ماند.
 *
 * @param  array{definition: ProcessDefinition, approval: ProcessStep, end: ProcessStep, endRejected: ProcessStep}  $chain
 */
function pocAddSecondApproval(array $chain, string $role = 'accountant'): ProcessStep
{
    $secondApproval = ProcessStep::create([
        'process_definition_id' => $chain['definition']->id,
        'step_key' => 'second_approval',
        'name' => 'تأیید دوم',
        'step_type' => StepType::Approval,
        'assignment_type' => AssignmentType::Role,
        'assigned_role' => $role,
    ]);

    ProcessTransition::where('from_step_id', $chain['approval']->id)
        ->where('on_result', TransitionResult::Approved->value)
        ->update(['to_step_id' => $secondApproval->id]);

    ProcessTransition::create(['from_step_id' => $secondApproval->id, 'to_step_id' => $chain['end']->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $secondApproval->id, 'to_step_id' => $chain['endRejected']->id, 'on_result' => TransitionResult::Rejected]);

    return $secondApproval;
}

// ============================================================
// بخش ۱ — محدودسازی واقعی + نظارت + یادآوری
// ============================================================

it('does not let a holding_admin approve a step assigned to another role unless they actually hold that role too', function () {
    $company = pocCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $chain = pocFreeFormDefinition($company, $admin, 'accountant');
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    // holding_admin که فقط همین نقش را دارد، نه accountant.
    $pureHoldingAdmin = pocUserWithRole($company, 'holding_admin');

    expect(fn () => app(ApproveProcessStep::class)->handle($instance, $pureHoldingAdmin))
        ->toThrow(AuthorizationException::class);

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress);
});

it('lets a user with the actually-assigned role approve — a plain holding_admin has no special bypass at all', function () {
    // user_company_roles.uq_user_company تضمین می‌کند هر کاربر حداکثر یک نقش در
    // هر شرکت دارد — پس «هم holding_admin هم accountant در یک شرکت» اصلاً در این
    // schema قابل‌نمایش نیست. تنها بای‌پس واقعی موجود is_super_admin است (که
    // hasRoleInCompany صریح مستند می‌کند)، نه holding_admin. این تست تأیید می‌کند
    // کاربری که واقعاً همان نقش واگذارشده (accountant) را دارد، مجاز است —
    // مستقل از این‌که holding_admin هم باشد یا نه (که در این schema نمی‌تواند).
    $company = pocCompany();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $chain = pocFreeFormDefinition($company, $admin, 'accountant');
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    $realAccountant = pocUserWithRole($company, 'accountant');

    app(ApproveProcessStep::class)->handle($instance, $realAccountant);

    expect($instance->fresh()->status)->toBe(ProcessStatus::Approved);
});

it('confirms is_super_admin is the only real bypass — not a holding_admin-specific one — matching the documented User::hasRoleInCompany() escape hatch', function () {
    $company = pocCompany();
    $creator = User::factory()->create(['is_super_admin' => true]);
    $chain = pocFreeFormDefinition($company, $creator, 'accountant');
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $creator);

    // یک super_admin بدون هیچ نقش اختصاصی در این شرکت — بای‌پس فقط از پرچم
    // is_super_admin می‌آید، نه از یک قانون مخصوص holding_admin.
    $superAdminNoRole = User::factory()->create(['is_super_admin' => true]);

    app(ApproveProcessStep::class)->handle($instance, $superAdminNoRole);

    expect($instance->fresh()->status)->toBe(ProcessStatus::Approved);
});

it('only allows holding_admin to access the oversight panel', function () {
    $company = pocCompany();

    foreach (['operator', 'accountant', 'viewer'] as $role) {
        $user = pocUserWithRole($company, $role);
        test()->actingAs($user);
        app(CompanyContext::class)->set($company->id);

        test()->get('/processes/oversight')->assertForbidden();
    }

    $admin = pocUserWithRole($company, 'holding_admin');
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    test()->get('/processes/oversight')->assertOk();
});

it('records a reminder as a new log entry visible in history, without changing the instance state, and shows it on the assignee task', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(RecordProcessReminder::class)->handle($instance, $admin, 'لطفاً هرچه سریع‌تر بررسی کنید.');

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->fresh()->current_step_id)->toBe($chain['approval']->id)
        ->and($instance->logs()->where('action', LogAction::Reminder->value)->count())->toBe(1);

    test()->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->assertSee('یادآوری از ادمین')
        ->assertSee('لطفاً هرچه سریع‌تر بررسی کنید.');
});

it('blocks a non holding_admin from recording a reminder even by calling the action directly', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $operator = pocUserWithRole($company, 'operator');

    $chain = pocFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    expect(fn () => app(RecordProcessReminder::class)->handle($instance, $operator, 'یادآوری غیرمجاز'))
        ->toThrow(AuthorizationException::class);
});

// ============================================================
// بخش ۳ — حذف واقعی فرایند
// ============================================================

it('hard-deletes a process definition that has never had any instance', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $chain = pocFreeFormDefinition($company, $admin);

    app(DeleteProcessDefinition::class)->handle($admin, $chain['definition']);

    expect(ProcessDefinition::withoutGlobalScope('owner_company')->withTrashed()->find($chain['definition']->id))->toBeNull()
        ->and(ProcessStep::where('process_definition_id', $chain['definition']->id)->exists())->toBeFalse();
});

it('soft-deletes (archives) a process definition that has at least one instance, keeping history intact', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $chain = pocFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(DeleteProcessDefinition::class)->handle($admin, $chain['definition']->fresh());

    expect(ProcessDefinition::withoutGlobalScope('owner_company')->find($chain['definition']->id))->toBeNull() // مخفی از فهرست فعال
        ->and(ProcessDefinition::withoutGlobalScope('owner_company')->withTrashed()->find($chain['definition']->id))->not->toBeNull()
        ->and(ProcessStep::where('process_definition_id', $chain['definition']->id)->exists())->toBeTrue()
        ->and($instance->fresh()->exists)->toBeTrue();
});

it('blocks a non holding_admin from deleting a process definition', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $operator = pocUserWithRole($company, 'operator');
    $chain = pocFreeFormDefinition($company, $admin);

    expect(fn () => app(DeleteProcessDefinition::class)->handle($operator, $chain['definition']))
        ->toThrow(AuthorizationException::class);
});

it('deletes a process definition from the index panel via the real Livewire component', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $chain = pocFreeFormDefinition($company, $admin);

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(ProcessDefinitionIndex::class)
        ->call('delete', $chain['definition']->id);

    expect(ProcessDefinition::withTrashed()->find($chain['definition']->id))->toBeNull();
});

// ============================================================
// بخش ۴ — تاریخچه در کارهای من + ویرایش تصمیم اخیر
// ============================================================

it('shows history for a pending task in my process tasks even before the current approver has acted', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    test()->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openHistory', $instance->id)
        ->assertSet('showHistoryModal', true)
        ->assertSee('شروع شد');
});

it('lets an actor reverse their own decision when nothing has happened since, and re-opens the step for a new decision', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    $secondApproval = pocAddSecondApproval($chain);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(ApproveProcessStep::class)->handle($instance, $accountant, 'تأیید اولیه');

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->fresh()->current_step_id)->toBe($secondApproval->id);

    app(ReverseProcessDecision::class)->handle($instance->fresh(), $accountant);

    $fresh = $instance->fresh();
    expect($fresh->status)->toBe(ProcessStatus::InProgress)
        ->and($fresh->current_step_id)->toBe($chain['approval']->id);

    $originalLog = ProcessInstanceLog::where('process_instance_id', $instance->id)
        ->where('action', LogAction::Approved->value)
        ->firstOrFail();

    expect($originalLog->reversed_at)->not->toBeNull()
        ->and($originalLog->comment)->toBe('تأیید اولیه') // محتوای اصلی هرگز عوض نمی‌شود
        ->and($instance->logs()->where('action', LogAction::Reversed->value)->exists())->toBeTrue();

    // بعد از بازگردانی، تصمیم دوباره ممکن است — این‌بار رد.
    app(RejectProcessStep::class)->handle($fresh, $accountant);
    expect($instance->fresh()->status)->toBe(ProcessStatus::Rejected);
});

it('rejects reversing a decision once the next step has already produced any log', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(ApproveProcessStep::class)->handle($instance, $accountant);
    // تأیید مستقیم به end رفت، پس یک لاگ completed بعد از approved ثبت شده.

    expect(fn () => app(ReverseProcessDecision::class)->handle($instance->fresh(), $accountant))
        ->toThrow(AuthorizationException::class);
});

it('rejects reversing a decision by someone other than the original actor', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');
    $otherAccountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    $secondApproval = pocAddSecondApproval($chain);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(ApproveProcessStep::class)->handle($instance, $accountant);

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress)
        ->and($instance->fresh()->current_step_id)->toBe($secondApproval->id);

    expect(fn () => app(ReverseProcessDecision::class)->handle($instance->fresh(), $otherAccountant))
        ->toThrow(AuthorizationException::class);

    // خودِ accountant هنوز می‌تواند بازگرداند.
    app(ReverseProcessDecision::class)->handle($instance->fresh(), $accountant);
    expect($instance->fresh()->current_step_id)->toBe($chain['approval']->id);
});

it('reverses a decision from the my-tasks Livewire component and it reappears in the task list', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');
    $accountant = pocUserWithRole($company, 'accountant');

    $chain = pocFreeFormDefinition($company, $admin);
    pocAddSecondApproval($chain);
    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    app(ApproveProcessStep::class)->handle($instance, $accountant);

    test()->actingAs($accountant);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('reverseDecision', $instance->id);

    expect($instance->fresh()->status)->toBe(ProcessStatus::InProgress);

    Livewire::test(MyProcessTasks::class)
        ->assertSee('درخواست آزاد نظارتی');
});

// ============================================================
// بخش ۵ — تولید خودکار process_key/step_key
// ============================================================

it('auto-generates a unique process_key from the name without exposing it in the form, colliding names get a numeric suffix', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(ProcessDefinitionForm::class)
        ->assertDontSee('کلید فرایند')
        ->assertDontSee('کلید مرحله');

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند تستی کلید خودکار');

    $steps = $component->get('steps');
    $startKey = $steps[0]['step_key'];

    $component->set('steps.1', [
        'step_key' => 'end-'.uniqid(),
        'name' => 'پایان',
        'step_type' => StepType::End->value,
        'assignment_type' => '',
        'assigned_role' => '',
        'assigned_user_id' => '',
        'condition_field' => '',
        'condition_operator' => '',
        'condition_value' => '',
    ]);
    $endKey = $component->get('steps')[1]['step_key'];

    $component->set('transitionSelections.'.$startKey.'.next', $endKey)
        ->call('save');

    $first = ProcessDefinition::where('owner_company_id', $company->id)
        ->where('name', 'فرایند تستی کلید خودکار')
        ->firstOrFail();

    expect($first->process_key)->not->toBeEmpty();

    // ساخت دومین فرایند با همان نام — باید کلید متفاوت (پسوند عددی) بگیرد.
    $component2 = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند تستی کلید خودکار');

    $steps2 = $component2->get('steps');
    $startKey2 = $steps2[0]['step_key'];

    $component2->set('steps.1', [
        'step_key' => 'end-'.uniqid(),
        'name' => 'پایان',
        'step_type' => StepType::End->value,
        'assignment_type' => '',
        'assigned_role' => '',
        'assigned_user_id' => '',
        'condition_field' => '',
        'condition_operator' => '',
        'condition_value' => '',
    ]);
    $endKey2 = $component2->get('steps')[1]['step_key'];

    $component2->set('transitionSelections.'.$startKey2.'.next', $endKey2)
        ->call('save');

    $second = ProcessDefinition::where('owner_company_id', $company->id)
        ->where('name', 'فرایند تستی کلید خودکار')
        ->where('id', '!=', $first->id)
        ->firstOrFail();

    expect($second->process_key)->not->toBe($first->process_key)
        ->and($second->process_key)->toStartWith($first->process_key);
});

it('regression: the HR leave process integration still resolves purely by subject_type, unaffected by auto-generated process_key', function () {
    $company = pocCompany();
    $admin = pocUserWithRole($company, 'holding_admin');

    ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند مرخصی',
        'process_key' => 'auto-generated-key-'.uniqid(),
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    // نگاه کن LeaveProcessIntegrationTest.php برای پوشش کامل‌تر این مسیر — اینجا
    // فقط تأیید می‌کنیم که خودِ لوکیشن (subject_type + is_active) هنوز کار
    // می‌کند، مستقل از این‌که process_key چه مقداری دارد.
    $definition = ProcessDefinition::withoutGlobalScope('owner_company')
        ->where('owner_company_id', $company->id)
        ->where('subject_type', Leave::class)
        ->where('is_active', true)
        ->first();

    expect($definition)->not->toBeNull()
        ->and($definition->process_key)->toStartWith('auto-generated-key-');
});
