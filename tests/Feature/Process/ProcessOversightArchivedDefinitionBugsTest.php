<?php

use App\Livewire\Process\ProcessDefinitionForm;
use App\Livewire\Process\ProcessOversight;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Actions\DeleteProcessDefinition;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Livewire\Livewire;

/**
 * رگرسیون دو باگ گزارش‌شده‌ی واقعی: (۱) /processes/oversight با خطای 500
 * روی instance متعلق به یک definition بایگانی‌شده (soft-deleted) می‌شکست
 * چون رابطه‌ی ProcessInstance::definition() از global scope پیش‌فرض
 * SoftDeletes عبور می‌کرد؛ (۲) تولید خودکار process_key هنگام چک تصادم،
 * رکوردهای soft-deleted را نادیده می‌گرفت و به UNIQUE سطح دیتابیس
 * (uq_process_definitions_company_key که هنوز ردیف فیزیکی بایگانی‌شده را
 * می‌بیند) برمی‌خورد.
 */
function poabRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function poabGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => poabRole($roleName)->id,
    ]);
}

function poabUserWithRole(Company $company, string $roleName): User
{
    $user = User::factory()->create(['is_super_admin' => false]);
    poabGiveRole($user, $company, $roleName);

    return $user;
}

function poabCompany(): Company
{
    return Company::create(['name' => 'آرشامان', 'slug' => 'poab-'.uniqid(), 'business_type' => 'project_services']);
}

/**
 * @return array{definition: ProcessDefinition, start: ProcessStep, approval: ProcessStep, end: ProcessStep}
 */
function poabFreeFormDefinition(Company $company, User $creator, string $name = 'فرایند تستی بایگانی'): array
{
    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => $name,
        'process_key' => \Illuminate\Support\Str::slug($name),
        'subject_type' => null,
        'request_form_fields' => [],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);

    return compact('definition', 'start', 'approval', 'end');
}

it('bug 1: renders /processes/oversight without a 500 for an instance whose definition is archived, and flags it as archived', function () {
    $company = poabCompany();
    $admin = poabUserWithRole($company, 'holding_admin');
    $chain = poabFreeFormDefinition($company, $admin);

    $instance = app(ProcessEngine::class)->startInstance($chain['definition'], $admin);

    // بایگانی (soft-delete) — instance سابقه دارد، پس DeleteProcessDefinition
    // فقط soft-delete می‌کند (بند ۳ Session قبلی).
    app(DeleteProcessDefinition::class)->handle($admin, $chain['definition']->fresh());

    expect($chain['definition']->fresh()->trashed())->toBeTrue();

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    // رابطه دیگر null نیست — withTrashed() روی belongsTo رفع شد.
    expect($instance->fresh()->definition)->not->toBeNull()
        ->and($instance->fresh()->definition->name)->toBe('فرایند تستی بایگانی');

    $response = test()->get('/processes/oversight');
    $response->assertOk();
    $response->assertSee('فرایند تستی بایگانی');
    $response->assertSee('بایگانی‌شده');

    Livewire::test(ProcessOversight::class)
        ->assertOk()
        ->assertSee('فرایند تستی بایگانی')
        ->assertSee('بایگانی‌شده');
});

it('bug 2: creating a new definition with a name whose key collides with an archived definition succeeds without a raw database exception', function () {
    $company = poabCompany();
    $admin = poabUserWithRole($company, 'holding_admin');
    $chain = poabFreeFormDefinition($company, $admin, 'فرایند تکراری');

    // این definition تازه‌ساخته هنوز هیچ instance ندارد، پس حذف واقعاً hard
    // است — ولی برای بازتولید دقیق گزارش (که hard-delete هم همین مشکل را
    // نشان می‌دهد چون ردیف physically باقی می‌ماند تا commit تراکنش)، یک
    // instance واقعی می‌سازیم تا مسیر soft-delete طی شود.
    app(ProcessEngine::class)->startInstance($chain['definition'], $admin);
    app(DeleteProcessDefinition::class)->handle($admin, $chain['definition']->fresh());

    expect($chain['definition']->fresh()->trashed())->toBeTrue();

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    // ساخت یک تعریف جدید با همان نام — نباید یک QueryException خام یا ۵۰۰
    // بدهد؛ باید موفق شود با یک process_key متفاوت (پسوند عددی).
    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('name', 'فرایند تکراری');

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
        ->call('save')
        ->assertHasNoErrors();

    $newDefinition = ProcessDefinition::withoutGlobalScope('owner_company')
        ->where('owner_company_id', $company->id)
        ->where('name', 'فرایند تکراری')
        ->where('id', '!=', $chain['definition']->id)
        ->firstOrFail();

    expect($newDefinition->process_key)->not->toBe($chain['definition']->process_key)
        ->and($newDefinition->process_key)->toStartWith($chain['definition']->process_key);
});
