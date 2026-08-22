<?php

use App\Livewire\CRM\CampaignIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\CreateCampaign;
use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Actions\TriggerWinbackCampaign;
use App\Modules\CRM\Enums\CampaignChannel;
use App\Modules\CRM\Enums\CampaignTriggerType;
use App\Modules\CRM\Models\Campaign;
use App\Modules\CRM\Models\CampaignLog;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\RfmSegment;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function campaignMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function campaignGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => campaignMakeRole($roleName)->id,
    ]);
}

function campaignMakeProfile(Company $company, User $actor, string $fullName, string $phone): ContactSiteProfile
{
    return app(CreateContactSiteProfile::class)->handle(
        ['full_name' => $fullName, 'phone' => $phone, 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $actor,
        app(ContactMatcher::class)
    );
}

function campaignSetSegment(ContactSiteProfile $profile, string $segment): void
{
    RfmSegment::withoutGlobalScopes()->create([
        'owner_company_id' => $profile->owner_company_id,
        'contact_site_profile_id' => $profile->id,
        'recency_days' => 10,
        'frequency_count' => 1,
        'monetary_amount' => null,
        'segment' => $segment,
        'calculated_at' => now(),
    ]);
}

function campaignCreate(Company $company, User $actor): Campaign
{
    return app(CreateCampaign::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کمپین بازگشت مشتریان',
        'trigger_type' => CampaignTriggerType::Winback90Days->value,
        'channel' => CampaignChannel::Sms->value,
        'message_template' => 'سلام {نام}، دلمون براتون تنگ شده!',
        'is_active' => true,
    ], $actor);
}

it('targets only dormant contact profiles, not vip/at_risk/new ones', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($operator, $company, 'operator');

    $dormant = campaignMakeProfile($company, $operator, 'مشتری غیرفعال', '09121230001');
    campaignSetSegment($dormant, RfmSegment::SEGMENT_DORMANT);

    $vip = campaignMakeProfile($company, $operator, 'مشتری ویژه', '09121230002');
    campaignSetSegment($vip, RfmSegment::SEGMENT_VIP);

    $atRisk = campaignMakeProfile($company, $operator, 'مشتری در معرض ریزش', '09121230003');
    campaignSetSegment($atRisk, RfmSegment::SEGMENT_AT_RISK);

    campaignMakeProfile($company, $operator, 'مشتری بدون بخش‌بندی', '09121230004'); // segment=new (no rfm record)

    $campaign = campaignCreate($company, $operator);

    $logs = app(TriggerWinbackCampaign::class)->handle($campaign, $operator);

    expect($logs)->toHaveCount(1);
    expect($logs->first()->contact_site_profile_id)->toBe($dormant->id);
});

it('creates campaign logs with status=simulated and never actually sends anything', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($operator, $company, 'operator');

    $dormant = campaignMakeProfile($company, $operator, 'مشتری غیرفعال', '09121230005');
    campaignSetSegment($dormant, RfmSegment::SEGMENT_DORMANT);

    $campaign = campaignCreate($company, $operator);

    $logs = app(TriggerWinbackCampaign::class)->handle($campaign, $operator);

    expect($logs->first()->status)->toBe(CampaignLog::STATUS_SIMULATED);
    expect(CampaignLog::withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('status', 'simulated')->count())->toBe(1);
});

it('refuses to trigger a campaign whose trigger_type is not winback_90days', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($operator, $company, 'operator');

    $campaign = app(CreateCampaign::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'اطلاع‌رسانی ارسال',
        'trigger_type' => CampaignTriggerType::ShippingNotification->value,
        'channel' => CampaignChannel::Sms->value,
        'message_template' => 'سفارش شما ارسال شد.',
        'is_active' => true,
    ], $operator);

    expect(fn () => app(TriggerWinbackCampaign::class)->handle($campaign, $operator))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects triggering a campaign by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($intruder, $company, 'viewer');

    $campaign = campaignCreate($company, $admin);

    expect(fn () => app(TriggerWinbackCampaign::class)->handle($campaign, $intruder))
        ->toThrow(AuthorizationException::class);

    expect(CampaignLog::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($user, $companyA, 'operator');
    campaignGiveRole($user, $companyB, 'viewer');

    $campaignB = campaignCreate($companyB, $admin);

    expect(fn () => app(TriggerWinbackCampaign::class)->handle($campaignB, $user))
        ->toThrow(AuthorizationException::class);

    expect(CampaignLog::withoutGlobalScopes()->count())->toBe(0);
});

it('lets an operator manage campaigns from the panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($operator, $company, 'operator');

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(CampaignIndex::class)
        ->assertOk()
        ->set('name', 'کمپین تست')
        ->set('triggerType', CampaignTriggerType::Winback90Days->value)
        ->set('channel', CampaignChannel::Sms->value)
        ->set('messageTemplate', 'سلام {نام}')
        ->call('create')
        ->assertHasNoErrors();

    expect(Campaign::where('name', 'کمپین تست')->count())->toBe(1);
});

it('denies mounting the campaign panel to a user without an authorized role in the active company', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    campaignGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    Livewire::test(CampaignIndex::class)
        ->assertForbidden();
});
