<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;

/*
|--------------------------------------------------------------------------
| آیتم‌های منوی HR در layouts/app.blade.php
|--------------------------------------------------------------------------
|
| هدف این تست‌ها فقط «دیده‌شدن متن» نیست: هر آیتم منو یک route() صدا می‌زند و
| اگر مسیری حذف یا تغییر نام دهد، رندر layout با RouteNotFoundException می‌شکند.
| پس این تست‌ها نگهبان لینک‌های شکسته در پوسته پیشخوان‌اند.
*/

function navCompany(): Company
{
    return Company::create([
        'name' => 'آرشامان',
        'slug' => 'arshaman',
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

it('shows the self-service menu to any authenticated user', function () {
    $company = navCompany();
    $user = User::factory()->create(['is_super_admin' => false]);
    navGiveRole($user, $company, 'sales_agent');

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('پنل من')
        ->assertSee('حضور و غیاب من')
        ->assertSee('مرخصی‌های من')
        ->assertSee('فیش‌های حقوقی من');
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
