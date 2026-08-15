<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;

/*
|--------------------------------------------------------------------------
| سازماندهی منو Session ۹ — وبلاگ و «پیام‌های تماس با ما» زیر «سایت‌ساز»
|--------------------------------------------------------------------------
|
| فقط محل نمایش عوض شده، نه Policy: زیرمنوی وبلاگ همان شرط PagePolicy/
| BlogPostPolicy::viewAny (هر نقشی در شرکت) دارد؛ «پیام‌های تماس با ما»
| همچنان محدود به holding_admin/operator است (همان شرط قبلی زیر «مخاطبین»).
*/

function sbNavCompany(string $name = 'آرشامان', string $slug = 'arshaman'): Company
{
    return Company::create(['name' => $name, 'slug' => $slug, 'business_type' => 'project_services']);
}

function sbNavGiveRole(User $user, Company $company, string $roleName): void
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

it('shows blog and contact-submissions links under the سایت‌ساز menu for a holding_admin', function () {
    $company = sbNavCompany();
    $admin = User::factory()->create(['is_super_admin' => false]);
    sbNavGiveRole($admin, $company, 'holding_admin');

    $this->actingAs($admin)->get('/')
        ->assertOk()
        ->assertSee('سایت‌ساز')
        ->assertSee('صفحات')
        ->assertSee('تنظیمات سایت')
        ->assertSee('پست‌های وبلاگ')
        ->assertSee('دسته‌بندی‌های وبلاگ')
        ->assertSee('برچسب‌های وبلاگ')
        ->assertSee('پیام‌های تماس با ما');
});

it('shows blog links but hides contact-submissions from a viewer, even though a viewer sees سایت‌ساز', function () {
    $company = sbNavCompany();
    $viewer = User::factory()->create(['is_super_admin' => false]);
    sbNavGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer)->get('/')
        ->assertOk()
        ->assertSee('سایت‌ساز')
        ->assertSee('پست‌های وبلاگ')
        ->assertDontSee('پیام‌های تماس با ما');
});

it('no longer shows a standalone وبلاگ menu group separate from سایت‌ساز', function () {
    $company = sbNavCompany();
    $admin = User::factory()->create(['is_super_admin' => false]);
    sbNavGiveRole($admin, $company, 'holding_admin');

    // "وبلاگ" دیگر عنوان یک <x-menu-sub> جدا نیست، فقط زیرآیتم‌های خودش
    // ("پست‌های وبلاگ" و ...) داخل سایت‌ساز — پس متن خام "وبلاگ" دیگر جدا
    // نباید به‌عنوان عنوان زیرمنو ظاهر شود (فقط داخل عبارات ترکیبی).
    $this->actingAs($admin)->get('/')->assertOk()->assertDontSee('>وبلاگ<', false);
});
