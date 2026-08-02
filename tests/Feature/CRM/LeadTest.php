<?php

use App\Livewire\CRM\LeadBoard;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\AssignLead;
use App\Modules\CRM\Actions\CreateLead;
use App\Modules\CRM\Actions\UpdateLeadStage;
use App\Modules\CRM\Models\Lead;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function leadMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function leadGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => leadMakeRole($roleName)->id,
    ]);
}

it('lets an operator create a lead without a contact', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_INSTAGRAM,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    expect($lead->pipeline_stage)->toBe(Lead::STAGE_NEW);
    expect($lead->contact_site_profile_id)->toBeNull();
    expect($lead->owner_company_id)->toBe($company->id);
});

it('rejects lead creation by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($intruder, $company, 'sales_rep');

    expect(fn () => app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $intruder))->toThrow(AuthorizationException::class);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an operator creating a lead in a company where they have no role at all', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $operatorOfA = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operatorOfA, $companyA, 'operator');

    expect(fn () => app(CreateLead::class)->handle([
        'owner_company_id' => $companyB->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operatorOfA))->toThrow(AuthorizationException::class);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($user, $companyA, 'operator');
    leadGiveRole($user, $companyB, 'viewer');

    expect(fn () => app(CreateLead::class)->handle([
        'owner_company_id' => $companyB->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $user))->toThrow(AuthorizationException::class);

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects lead creation by an accountant', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($accountant, $company, 'accountant');

    expect(fn () => app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $accountant))->toThrow(AuthorizationException::class);
});

it('moves a lead through valid pipeline transitions', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_CONTACTED, $operator);
    expect($lead->pipeline_stage)->toBe(Lead::STAGE_CONTACTED);

    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_QUALIFIED, $operator);
    expect($lead->pipeline_stage)->toBe(Lead::STAGE_QUALIFIED);

    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_PROPOSAL, $operator);
    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_WON, $operator);
    expect($lead->pipeline_stage)->toBe(Lead::STAGE_WON);
});

it('allows moving a lead to lost from any active stage', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_LOST, $operator);

    expect($lead->pipeline_stage)->toBe(Lead::STAGE_LOST);
});

it('rejects an undefined pipeline transition', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    expect(fn () => app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_WON, $operator))
        ->toThrow(InvalidArgumentException::class);

    expect($lead->fresh()->pipeline_stage)->toBe(Lead::STAGE_NEW);
});

it('rejects any transition out of a terminal stage', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    $lead = app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_LOST, $operator);

    expect(fn () => app(UpdateLeadStage::class)->handle($lead, Lead::STAGE_CONTACTED, $operator))
        ->toThrow(InvalidArgumentException::class);
});

it('lets an operator assign a lead to a user', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    $assignee = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');
    leadGiveRole($assignee, $company, 'operator');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $operator);

    $lead = app(AssignLead::class)->handle($lead, $assignee->id, $operator);

    expect($lead->assigned_to_user_id)->toBe($assignee->id);
});

it('rejects assigning a lead by a user with no role in the lead company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($user, $companyA, 'operator');
    leadGiveRole($user, $companyB, 'viewer');

    $lead = app(CreateLead::class)->handle([
        'owner_company_id' => $companyB->id,
        'contact_site_profile_id' => null,
        'source' => Lead::SOURCE_WEBSITE,
        'estimated_value' => null,
        'notes' => null,
    ], $admin);

    expect(fn () => app(AssignLead::class)->handle($lead, $user->id, $user))
        ->toThrow(AuthorizationException::class);

    expect($lead->fresh()->assigned_to_user_id)->toBeNull();
});

it('shows the lead board grouped by pipeline stage and lets an operator create a lead through it', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(LeadBoard::class)
        ->assertOk()
        ->set('source', Lead::SOURCE_TELEGRAM)
        ->set('notes', 'سرنخ از تلگرام')
        ->call('create')
        ->assertHasNoErrors();

    expect(Lead::where('owner_company_id', $company->id)->where('source', Lead::SOURCE_TELEGRAM)->count())->toBe(1);
});

it('lists an operator of the active company among assignable users on the lead board', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewingOperator = User::factory()->create(['is_super_admin' => false]);
    $otherOperator = User::factory()->create(['is_super_admin' => false, 'full_name' => 'طاها کلایی']);
    leadGiveRole($viewingOperator, $company, 'operator');
    leadGiveRole($otherOperator, $company, 'operator');

    $this->actingAs($viewingOperator);
    session(['active_company_id' => $company->id]);

    $assignable = Livewire::test(LeadBoard::class)->get('assignableUsers');

    expect($assignable->pluck('id'))->toContain($otherOperator->id);
});

it('excludes a user whose only role in the active company is unrelated to lead management, such as viewer or accountant', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');
    leadGiveRole($viewer, $company, 'viewer');
    leadGiveRole($accountant, $company, 'accountant');

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    $assignable = Livewire::test(LeadBoard::class)->get('assignableUsers');

    expect($assignable->pluck('id'))->not->toContain($viewer->id)
        ->and($assignable->pluck('id'))->not->toContain($accountant->id);
});

it('includes a holding_admin whose only real company-role row belongs to a different company — is_super_admin bypass regression', function () {
    $holdingCompany = Company::create(['name' => 'ستاد مشترک', 'slug' => 'shared', 'business_type' => 'project_services']);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $superAdmin = User::factory()->create(['is_super_admin' => true, 'full_name' => 'مدیر کل']);
    leadGiveRole($superAdmin, $holdingCompany, 'holding_admin');

    $operator = User::factory()->create(['is_super_admin' => false]);
    leadGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    $assignable = Livewire::test(LeadBoard::class)->get('assignableUsers');

    expect($assignable->pluck('id'))->toContain($superAdmin->id)
        ->and($assignable->pluck('id'))->toContain($operator->id);
});
