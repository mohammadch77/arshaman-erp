<?php

use App\Livewire\CRM\ContactForm;
use App\Livewire\CRM\ContactIndex;
use App\Livewire\CRM\ContactProfile;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function crmMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function crmGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => crmMakeRole($roleName)->id,
    ]);
}

function crmContactData(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => 'test@example.com',
        'site_full_name' => null,
    ], $overrides);
}

it('links two ContactSiteProfiles with the same mobile in different companies to one golden record', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $profileA = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $companyA->id],
        $admin,
        app(ContactMatcher::class)
    );

    $profileB = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['full_name' => 'همان مشتری، نام دیگر']), 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect($profileA->contact_id)->toBe($profileB->contact_id);
    expect(Contact::count())->toBe(1);
    expect(ContactSiteProfile::withoutGlobalScopes()->count())->toBe(2);
});

it('links two profiles sharing only the same email to the same golden record', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $profileA = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['phone' => '09121110000', 'email' => 'shared@example.com']), 'owner_company_id' => $companyA->id],
        $admin,
        app(ContactMatcher::class)
    );

    $profileB = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['phone' => '09129990000', 'email' => 'shared@example.com']), 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect($profileA->contact_id)->toBe($profileB->contact_id);
    expect(Contact::count())->toBe(1);
});

it('rejects a create attempt by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($intruder, $company, 'sales_rep');

    expect(fn () => app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $company->id],
        $intruder,
        app(ContactMatcher::class)
    ))->toThrow(AuthorizationException::class);

    expect(Contact::count())->toBe(0);
});

it('rejects an operator creating a contact site profile for a company where they have no role at all', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $operatorOfA = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($operatorOfA, $companyA, 'operator');

    expect(fn () => app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $companyB->id],
        $operatorOfA,
        app(ContactMatcher::class)
    ))->toThrow(AuthorizationException::class);

    expect(Contact::count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($user, $companyA, 'operator');
    crmGiveRole($user, $companyB, 'viewer');

    expect(fn () => app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $companyB->id],
        $user,
        app(ContactMatcher::class)
    ))->toThrow(AuthorizationException::class);

    expect(Contact::count())->toBe(0);
});

it('forbids a role without company access from viewing the contact list', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $stranger = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($stranger)->get('/contacts')->assertForbidden();
});

it('forbids an accountant from viewing the contact list — Contact belongs to operator/holding_admin, not the Party-facing accountant role', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($accountant, $company, 'accountant');

    $this->actingAs($accountant)->get('/contacts')->assertForbidden();
});

it('rejects a create attempt by an accountant, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($accountant, $company, 'accountant');

    expect(fn () => app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $company->id],
        $accountant,
        app(ContactMatcher::class)
    ))->toThrow(AuthorizationException::class);

    expect(Contact::count())->toBe(0);
});

it('allows an operator to view and create contact site profiles', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($operator, $company, 'operator');

    $this->actingAs($operator)->get('/contacts')->assertOk();

    $profile = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['full_name' => 'مخاطب اپراتور']), 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    expect($profile->owner_company_id)->toBe($company->id);
});

it('allows an operator to view the holding-wide 360 profile — same role set as ContactSiteProfilePolicy', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $operator = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    $this->actingAs($operator)
        ->get("/contacts/{$profile->contact_id}/profile")
        ->assertOk();
});

it('forbids an accountant from viewing the holding-wide 360 profile — Contact is not the Party-facing accountant role', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    crmGiveRole($accountant, $company, 'accountant');

    $profile = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(), 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    $this->actingAs($accountant)
        ->get("/contacts/{$profile->contact_id}/profile")
        ->assertForbidden();
});

it('allows a holding_admin to view the holding-wide 360 profile without exposing order-level data', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    crmGiveRole($admin, $companyA, 'holding_admin');

    $profileA = app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['phone' => '09120001111']), 'owner_company_id' => $companyA->id],
        $admin,
        app(ContactMatcher::class)
    );
    app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['phone' => '09120001111']), 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    $this->actingAs($admin);
    session(['active_company_id' => $companyA->id]);

    $component = Livewire::test(ContactProfile::class, ['contactId' => $profileA->contact_id])
        ->assertOk();

    $siteProfiles = $component->get('siteProfiles');

    expect($siteProfiles)->toHaveCount(2);

    // فقط ستون‌های خود جدول contact_site_profiles در دسترس‌اند — هیچ رابطه یا
    // ستون سطح سفارش (orders/interactions) لود یا نمایش داده نمی‌شود.
    foreach ($siteProfiles as $siteProfile) {
        expect(array_keys($siteProfile->getRelations()))->toEqual(['company']);
        expect($siteProfile->getAttributes())->toHaveKeys(['total_purchase_amount', 'first_seen_at'])
            ->not->toHaveKeys(['order_id', 'order_number']);
    }
});

it('creates a contact from the ContactForm Livewire component using ContactMatcher', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    crmGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(ContactForm::class)
        ->set('full_name', 'مخاطب فرم')
        ->set('phone', '09123334444')
        ->set('email', 'form@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $contact = Contact::where('phone', '09123334444')->firstOrFail();
    expect($contact->full_name)->toBe('مخاطب فرم');

    $profile = ContactSiteProfile::where('contact_id', $contact->id)->first();
    expect($profile->owner_company_id)->toBe($company->id);
});

it('lists contacts of the active company on the ContactIndex page', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    crmGiveRole($admin, $company, 'holding_admin');

    app(CreateContactSiteProfile::class)->handle(
        [...crmContactData(['full_name' => 'مخاطب فهرست', 'phone' => '09125556666']), 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(ContactIndex::class)
        ->assertSee('مخاطب فهرست');
});
