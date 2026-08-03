<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\HR\Models\Employee;

/*
|--------------------------------------------------------------------------
| آیتم‌های منوی HR در layouts/app.blade.php
|--------------------------------------------------------------------------
|
| هدف این تست‌ها فقط «دیده‌شدن متن» نیست: هر آیتم منو یک route() صدا می‌زند و
| اگر مسیری حذف یا تغییر نام دهد، رندر layout با RouteNotFoundException می‌شکند.
| پس این تست‌ها نگهبان لینک‌های شکسته در پوسته پیشخوان‌اند.
*/

function navCompany(string $name = 'آرشامان', string $slug = 'arshaman'): Company
{
    return Company::create([
        'name' => $name,
        'slug' => $slug,
        'business_type' => 'project_services',
    ]);
}

function navGiveRole(User $user, Company $company, string $roleName): void
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

it('shows every HR admin menu item to an accountant', function () {
    $company = navCompany();
    $accountant = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($accountant, $company, 'accountant');

    $this->actingAs($accountant)->get('/')
        ->assertOk()
        ->assertSee('منابع انسانی')
        ->assertSee('پرسنل')
        ->assertSee('حضور و غیاب')
        ->assertSee('جمع ماهانه کارکرد')
        ->assertSee('مرخصی‌ها')
        ->assertSee('حقوق و دستمزد');
});

it('shows the self-service menu to a user with a linked employee record, regardless of business role', function () {
    $company = navCompany();
    $user = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($user, $company, 'sales_agent');
    Employee::factory()->create(['owner_company_id' => $company->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('پنل من')
        ->assertSee('حضور و غیاب من')
        ->assertSee('مرخصی‌های من')
        ->assertSee('فیش‌های حقوقی من');
});

it('hides the self-service menu from an authenticated user with no linked employee record', function () {
    $company = navCompany();
    $user = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($user, $company, 'sales_agent');

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertDontSee('پنل من');
});

it('hides the HR admin menu from a user without an authorized role', function () {
    $company = navCompany();
    $user = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($user, $company, 'sales_agent');

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertDontSee('منابع انسانی')
        ->assertDontSee('حقوق و دستمزد');
});

it('hides the HR admin menu from a user who is accountant in a different company than the active one — cross-company role leak regression', function () {
    $companyA = navCompany('آرشامان', 'arshaman');
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($user, $companyA, 'accountant');
    navGiveRole($user, $companyB, 'viewer');

    $this->actingAs($user);
    session(['active_company_id' => $companyB->id]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('منابع انسانی');
});
