<?php

use App\Livewire\CRM\InteractionTimeline;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Actions\RecordInteraction;
use App\Modules\CRM\Models\Interaction;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function interactionMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function interactionGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => interactionMakeRole($roleName)->id,
    ]);
}

it('lets an operator manually record a call interaction on a contact site profile', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234567', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    $interaction = app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'call',
        'notes' => 'پیگیری سفارش',
        'occurred_at' => now(),
    ], $operator);

    expect($interaction->interaction_type)->toBe('call');
    expect($interaction->owner_company_id)->toBe($company->id);
    expect($interaction->contact_site_profile_id)->toBe($profile->id);
});

it('rejects a manual interaction record by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($intruder, $company, 'sales_rep');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234568', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'call',
        'notes' => null,
        'occurred_at' => now(),
    ], $intruder))->toThrow(AuthorizationException::class);

    expect(Interaction::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an operator recording an interaction on a contact site profile of a company where they have no role at all', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $operatorOfA = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($operatorOfA, $companyA, 'operator');

    $profileB = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری شرکت ب', 'phone' => '09121234580', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(RecordInteraction::class)->handle($profileB, [
        'interaction_type' => 'call',
        'notes' => null,
        'occurred_at' => now(),
    ], $operatorOfA))->toThrow(AuthorizationException::class);

    expect(Interaction::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($user, $companyA, 'operator');
    interactionGiveRole($user, $companyB, 'viewer');

    $profileB = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری شرکت ب دوم', 'phone' => '09121234581', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(RecordInteraction::class)->handle($profileB, [
        'interaction_type' => 'call',
        'notes' => null,
        'occurred_at' => now(),
    ], $user))->toThrow(AuthorizationException::class);

    expect(Interaction::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a manual interaction record by an accountant', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($accountant, $company, 'accountant');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234569', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'call',
        'notes' => null,
        'occurred_at' => now(),
    ], $accountant))->toThrow(AuthorizationException::class);
});

it('rejects a manual interaction of an unsupported type such as purchase', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234570', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    expect(fn () => app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'purchase',
        'notes' => null,
        'occurred_at' => now(),
    ], $operator))->toThrow(InvalidArgumentException::class);
});

it('shows the interaction timeline in chronological order on the ContactProfile page', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    interactionGiveRole($admin, $company, 'holding_admin');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تایم‌لاین', 'phone' => '09121234571', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    $older = app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'call',
        'notes' => 'تماس اول',
        'occurred_at' => now()->subDay(),
    ], $admin);

    $newer = app(RecordInteraction::class)->handle($profile, [
        'interaction_type' => 'telegram',
        'notes' => 'پیام دوم',
        'occurred_at' => now(),
    ], $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(InteractionTimeline::class, ['contactId' => $profile->contact_id])
        ->assertOk();

    $interactions = $component->get('interactions');

    expect($interactions->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('lets an operator record an interaction through the Livewire InteractionTimeline component', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    interactionGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری فرم', 'phone' => '09121234572', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    session(['active_company_id' => $company->id]);

    Livewire::test(InteractionTimeline::class, ['contactId' => $profile->contact_id])
        ->set('contact_site_profile_id', $profile->id)
        ->set('interaction_type', 'site_form')
        ->set('notes', 'ثبت‌شده از فرم سایت')
        ->call('record')
        ->assertHasNoErrors();

    expect(Interaction::withoutGlobalScopes()->where('contact_site_profile_id', $profile->id)->count())->toBe(1);
});
