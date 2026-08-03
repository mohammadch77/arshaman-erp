<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;

/*
|--------------------------------------------------------------------------
| آیتم‌های منوی CRM در layouts/app.blade.php
|--------------------------------------------------------------------------
|
| هدف: تضمین این‌که شرط نمایش آیتم منو دقیقاً همان Policy واقعی صفحه است
| (ContactSiteProfilePolicy/LeadPolicy/RfmSegmentPolicy::viewAny — فقط
| holding_admin/operator)، نه یک شرط شل‌تر مثل «هر نقشی در شرکت». اگر این دو
| از هم جدا بیفتند، کاربر آیتم منو را می‌بیند ولی با کلیک ۴۰۳ می‌گیرد.
*/

function crmNavCompany(string $name = 'آرشامان', string $slug = 'arshaman'): Company
{
    return Company::create(['name' => $name, 'slug' => $slug, 'business_type' => 'project_services']);
}

function crmNavGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => $roleName, 'is_system' => true]
        )->id,
    ]);
}

it('shows the CRM menu to an operator of the active company', function () {
    $company = crmNavCompany();
    $operator = User::factory()->create(['is_super_admin' => false]);
    crmNavGiveRole($operator, $company, 'operator');

    $this->actingAs($operator)->get('/')
        ->assertOk()
        ->assertSee('مخاطبین')
        ->assertSee('فهرست مخاطبین')
        ->assertSee('مخاطب جدید')
        ->assertSee('قیف فروش')
        ->assertSee('بخش‌بندی RFM');
});

it('shows the CRM menu to a holding_admin of the active company', function () {
    $company = crmNavCompany();
    $admin = User::factory()->create(['is_super_admin' => false]);
    crmNavGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin)->get('/')
        ->assertOk()
        ->assertSee('مخاطبین')
        ->assertSee('بخش‌بندی RFM');
});

it('hides the CRM menu from a viewer, even though a viewer has some role in the active company', function () {
    $company = crmNavCompany();
    $viewer = User::factory()->create(['is_super_admin' => false]);
    crmNavGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer)->get('/')
        ->assertOk()
        ->assertDontSee('مخاطبین')
        ->assertDontSee('بخش‌بندی RFM');
});

it('hides the CRM menu from an accountant — CRM is scoped to holding_admin/operator only', function () {
    $company = crmNavCompany();
    $accountant = User::factory()->create(['is_super_admin' => false]);
    crmNavGiveRole($accountant, $company, 'accountant');

    $this->actingAs($accountant)->get('/')
        ->assertOk()
        ->assertDontSee('مخاطبین');
});

it('hides the CRM menu from a user who is operator in a different company than the active one — cross-company role leak regression', function () {
    $companyA = crmNavCompany('آرشامان', 'arshaman');
    $companyB = crmNavCompany('Tkart', 'tkart');
    $user = User::factory()->create(['is_super_admin' => false]);
    crmNavGiveRole($user, $companyA, 'operator');
    crmNavGiveRole($user, $companyB, 'viewer');

    $this->actingAs($user);
    session(['active_company_id' => $companyB->id]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('مخاطبین');
});
