<?php

use App\Livewire\CRM\RfmSegmentIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\CalculateRfmSegment;
use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Interaction;
use App\Modules\CRM\Models\RfmSegment;
use App\Modules\CRM\Services\ContactMatcher;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function rfmMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function rfmGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => rfmMakeRole($roleName)->id,
    ]);
}

function rfmRecordPurchase(ContactSiteProfile $profile, User $actor, Carbon $occurredAt): Interaction
{
    return Interaction::create([
        'owner_company_id' => $profile->owner_company_id,
        'contact_site_profile_id' => $profile->id,
        'interaction_type' => Interaction::TYPE_PURCHASE,
        'notes' => null,
        'occurred_at' => $occurredAt,
        'created_by_user_id' => $actor->id,
    ]);
}

it('assigns segment=new with null fields to a profile without any purchase interaction', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری بدون خرید', 'phone' => '09121234590', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    $segment = app(CalculateRfmSegment::class)->handle($profile, $operator);

    expect($segment->segment)->toBe(RfmSegment::SEGMENT_NEW);
    expect($segment->recency_days)->toBeNull();
    expect($segment->frequency_count)->toBeNull();
    expect($segment->monetary_amount)->toBeNull();
});

it('calculates recency, frequency and segment from manual purchase interactions', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری ویژه', 'phone' => '09121234591', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );
    $profile->update(['total_purchase_amount' => 1500000]);

    rfmRecordPurchase($profile, $operator, now()->subDays(30));
    rfmRecordPurchase($profile, $operator, now()->subDays(10));
    rfmRecordPurchase($profile, $operator, now()->subDays(2));

    $segment = app(CalculateRfmSegment::class)->handle($profile, $operator);

    expect($segment->recency_days)->toBe(2);
    expect($segment->frequency_count)->toBe(3);
    expect((float) $segment->monetary_amount)->toBe(1500000.0);
    expect($segment->segment)->toBe(RfmSegment::SEGMENT_VIP);
});

it('stores monetary_amount as null, not zero, when purchases exist but total_purchase_amount was never populated — regression against a false "spent nothing" reading', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری بدون مبلغ ثبت‌شده', 'phone' => '09121234598', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    rfmRecordPurchase($profile, $operator, now()->subDays(5));

    $segment = app(CalculateRfmSegment::class)->handle($profile, $operator);

    expect($segment->frequency_count)->toBe(1);
    expect($segment->monetary_amount)->toBeNull();
});

it('classifies a long-inactive profile as dormant regardless of past frequency', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری غیرفعال', 'phone' => '09121234592', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    rfmRecordPurchase($profile, $operator, now()->subDays(30));
    rfmRecordPurchase($profile, $operator, now()->subDays(200));

    $segment = app(CalculateRfmSegment::class)->handle($profile, $operator);

    expect($segment->recency_days)->toBe(30);
    expect($segment->segment)->toBe(RfmSegment::SEGMENT_AT_RISK);
});

it('recalculating a profile updates the existing segment record instead of creating a new one', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تکراری', 'phone' => '09121234593', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    app(CalculateRfmSegment::class)->handle($profile, $operator);
    app(CalculateRfmSegment::class)->handle($profile, $operator);

    expect(RfmSegment::withoutGlobalScopes()->where('contact_site_profile_id', $profile->id)->count())->toBe(1);
});

it('rejects rfm calculation by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($intruder, $company, 'sales_rep');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234594', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(CalculateRfmSegment::class)->handle($profile, $intruder))
        ->toThrow(AuthorizationException::class);

    expect(RfmSegment::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($user, $companyA, 'operator');
    rfmGiveRole($user, $companyB, 'viewer');

    $profileB = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری شرکت ب', 'phone' => '09121234595', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $companyB->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(CalculateRfmSegment::class)->handle($profileB, $user))
        ->toThrow(AuthorizationException::class);

    expect(RfmSegment::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects rfm calculation by an accountant', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($accountant, $company, 'accountant');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تست', 'phone' => '09121234596', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $admin,
        app(ContactMatcher::class)
    );

    expect(fn () => app(CalculateRfmSegment::class)->handle($profile, $accountant))
        ->toThrow(AuthorizationException::class);
});

it('shows the rfm segment list grouped by segment and lets an operator recalculate through it', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($operator, $company, 'operator');

    $profile = app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری برد', 'phone' => '09121234597', 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $operator,
        app(ContactMatcher::class)
    );

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(RfmSegmentIndex::class)
        ->assertOk()
        ->call('recalculate', $profile->id)
        ->assertHasNoErrors();

    expect(RfmSegment::withoutGlobalScopes()->where('contact_site_profile_id', $profile->id)->where('segment', RfmSegment::SEGMENT_NEW)->count())->toBe(1);
});

it('denies mounting the rfm segment list to a user without an authorized role in the active company', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    rfmGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    Livewire::test(RfmSegmentIndex::class)
        ->assertForbidden();
});
