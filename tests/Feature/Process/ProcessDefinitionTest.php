<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function processMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function processGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => processMakeRole($roleName)->id,
    ]);
}

function processActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    processGiveRole($user, $company, $roleName);

    test()->actingAs($user);

    return [$user, $company];
}

it('rejects a process_definition with both subject_type and request_form_fields at the database CHECK constraint layer', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('CHECK constraint فقط روی mysql واقعی اعمال می‌شود.');
    }

    [, $company] = processActingAsWithRole('holding_admin');

    expect(fn () => DB::table('process_definitions')->insert([
        'id' => (string) Str::uuid(),
        'owner_company_id' => $company->id,
        'name' => 'نمونه نامعتبر',
        'process_key' => 'invalid_both',
        'subject_type' => 'App\\Modules\\HR\\Models\\Leave',
        'request_form_fields' => json_encode([['key' => 'x', 'type' => 'text', 'label' => 'x']]),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a process_instance where only one of subject_type/subject_id is filled', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('CHECK constraint فقط روی mysql واقعی اعمال می‌شود.');
    }

    [$user, $company] = processActingAsWithRole('holding_admin');

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند تستی',
        'process_key' => 'test_process_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [['key' => 'title', 'type' => 'text', 'label' => 'عنوان']],
        'is_active' => true,
        'created_by_user_id' => $user->id,
    ]);

    expect(fn () => DB::table('process_instances')->insert([
        'id' => (string) Str::uuid(),
        'owner_company_id' => $company->id,
        'process_definition_id' => $definition->id,
        'subject_type' => 'App\\Modules\\HR\\Models\\Leave',
        'subject_id' => null,
        'status' => 'in_progress',
        'started_by_user_id' => $user->id,
        'started_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('isolates process_definitions between companies', function () {
    [$userA, $companyA] = processActingAsWithRole('holding_admin');

    ProcessDefinition::create([
        'owner_company_id' => $companyA->id,
        'name' => 'فرایند شرکت A',
        'process_key' => 'proc_a',
        'request_form_fields' => [['key' => 'x', 'type' => 'text', 'label' => 'x']],
        'is_active' => true,
        'created_by_user_id' => $userA->id,
    ]);

    [$userB, $companyB] = processActingAsWithRole('holding_admin');

    ProcessDefinition::create([
        'owner_company_id' => $companyB->id,
        'name' => 'فرایند شرکت B',
        'process_key' => 'proc_b',
        'request_form_fields' => [['key' => 'y', 'type' => 'text', 'label' => 'y']],
        'is_active' => true,
        'created_by_user_id' => $userB->id,
    ]);

    test()->actingAs($userB);

    expect(ProcessDefinition::count())->toBe(1)
        ->and(ProcessDefinition::first()->owner_company_id)->toBe($companyB->id);

    expect(ProcessDefinition::withoutGlobalScopes()->count())->toBe(2);
});

it('only allows holding_admin to create a process_definition', function () {
    [$operator, $company] = processActingAsWithRole('operator');

    expect(Gate::forUser($operator)->allows('create', [ProcessDefinition::class, $company->id]))->toBeFalse();

    [$admin, $adminCompany] = processActingAsWithRole('holding_admin');

    expect(Gate::forUser($admin)->allows('create', [ProcessDefinition::class, $adminCompany->id]))->toBeTrue();
});

it('only allows holding_admin to update a process_definition', function () {
    [$admin, $company] = processActingAsWithRole('holding_admin');

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'فرایند تستی',
        'process_key' => 'update_test',
        'request_form_fields' => [['key' => 'x', 'type' => 'text', 'label' => 'x']],
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    processGiveRole($operator = User::factory()->create(['is_super_admin' => false]), $company, 'operator');

    expect(Gate::forUser($operator)->allows('update', $definition))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('update', $definition))->toBeTrue();
});

it('builds the seeded sample process chain correctly in the real database', function () {
    Company::withoutGlobalScopes()->updateOrCreate(
        ['slug' => 'arshaman'],
        ['name' => 'آرشامان', 'business_type' => 'project_services']
    );

    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ProcessSampleSeeder', '--force' => true]);

    $definition = ProcessDefinition::withoutGlobalScopes()->where('process_key', 'sample_free_request')->first();

    expect($definition)->not->toBeNull()
        ->and($definition->subject_type)->toBeNull()
        ->and($definition->request_form_fields)->toBeArray();

    $steps = ProcessStep::where('process_definition_id', $definition->id)->get()->keyBy('step_key');

    expect($steps)->toHaveCount(5)
        ->and($steps['start']->step_type)->toBe(StepType::Start)
        ->and($steps['manager_approval']->step_type)->toBe(StepType::Approval)
        ->and($steps['manager_approval']->assignment_type)->toBe(AssignmentType::Role)
        ->and($steps['amount_check']->step_type)->toBe(StepType::Condition)
        ->and($steps['amount_check']->condition_operator)->toBe(ConditionOperator::GreaterThan)
        ->and($steps['end_approved']->step_type)->toBe(StepType::End)
        ->and($steps['end_rejected']->step_type)->toBe(StepType::End);

    $transitions = ProcessTransition::whereIn('from_step_id', $steps->pluck('id'))->get();

    expect($transitions)->toHaveCount(5);

    $fromStart = $transitions->firstWhere('from_step_id', $steps['start']->id);
    expect($fromStart->to_step_id)->toBe($steps['manager_approval']->id)
        ->and($fromStart->on_result)->toBe(TransitionResult::Approved);

    $fromApproval = $transitions->where('from_step_id', $steps['manager_approval']->id);
    expect($fromApproval)->toHaveCount(2);

    $fromCondition = $transitions->where('from_step_id', $steps['amount_check']->id);
    expect($fromCondition)->toHaveCount(2)
        ->and($fromCondition->firstWhere('on_result', TransitionResult::ConditionTrue)->to_step_id)->toBe($steps['end_approved']->id)
        ->and($fromCondition->firstWhere('on_result', TransitionResult::ConditionFalse)->to_step_id)->toBe($steps['end_rejected']->id);
});
