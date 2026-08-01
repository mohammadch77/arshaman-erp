<?php

use App\Livewire\Core\CompanySwitcher;
use App\Modules\Core\Enums\BusinessType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function switcherCompany(string $slug, BusinessType $businessType = BusinessType::ProjectServices): Company
{
    return Company::create([
        'name' => $slug,
        'slug' => $slug,
        'business_type' => $businessType,
    ]);
}

it('keeps the active company after switching, across a fresh request', function () {
    $companyA = switcherCompany('company-a');
    $companyB = switcherCompany('company-b');

    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);

    foreach ([$companyA, $companyB] as $company) {
        UserCompanyRole::create([
            'user_id' => $user->id,
            'owner_company_id' => $company->id,
            'assigned_role_id' => $role->id,
        ]);
    }

    $this->actingAs($user);

    Livewire::test(CompanySwitcher::class)->call('switchTo', $companyB->id);

    expect(app(CompanyContext::class)->id())->toBe($companyB->id);

    // شبیه‌سازی رفرش: request جدید، همان session
    $this->get('/')->assertOk();

    expect(app(CompanyContext::class)->id())->toBe($companyB->id);
});

it('only lists companies the user has a role in', function () {
    $companyA = switcherCompany('company-a');
    $companyB = switcherCompany('company-b');

    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $companyA->id,
        'assigned_role_id' => $role->id,
    ]);

    $this->actingAs($user);

    $companies = Livewire::test(CompanySwitcher::class)->get('companies');

    expect($companies->pluck('id'))->toContain($companyA->id)
        ->and($companies->pluck('id'))->not->toContain($companyB->id);
});

it('lets a super admin see all companies plus the holding aggregate view', function () {
    $companyA = switcherCompany('company-a');
    $companyB = switcherCompany('company-b');

    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    $companies = Livewire::test(CompanySwitcher::class)->get('companies');

    expect($companies->pluck('id'))->toContain($companyA->id, $companyB->id);

    Livewire::test(CompanySwitcher::class)->call('switchToAggregate');

    expect(app(CompanyContext::class)->isAggregateView())->toBeTrue()
        ->and(app(CompanyContext::class)->id())->toBeNull();
});

it('rejects switching to a company the user has no role in', function () {
    $companyA = switcherCompany('company-a');

    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user);

    expect(fn () => app(CompanyContext::class)->set($companyA->id))
        ->toThrow(AuthorizationException::class);
});

it('shows sidebar menu items based on the active company business type', function () {
    $physical = switcherCompany('physical-co', BusinessType::PhysicalGoods);
    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $physical->id,
        'assigned_role_id' => $role->id,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('انبار')
        ->assertDontSee('پروژه‌ها');
});

// =====================================================================
// دسترسی مهمان
//
// پوسته پیشخوان سوییچر شرکت را روی هر صفحه رندر می‌کند و سوییچر به کاربر
// لاگین‌شده نیاز دارد. پس هر مسیری که این پوسته را استفاده کند باید پشت auth
// باشد — وگرنه مهمان به‌جای ریدایرکت به ورود، خطای ۵۰۰ می‌گیرد.
// =====================================================================

it('redirects a guest away from the internal theme showcase instead of failing', function () {
    $this->get('/theme-showcase')->assertRedirect('/login');
});

it('still serves the theme showcase to a signed-in user', function () {
    $company = switcherCompany('arshaman');
    $role = Role::firstOrCreate(
        ['name' => 'holding_admin'],
        ['display_name' => 'holding_admin', 'is_system' => true]
    );
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => $role->id,
    ]);

    $this->actingAs($user)->get('/theme-showcase')
        ->assertOk()
        ->assertSee('نمایش تم');
});

it('renders the company switcher empty rather than fatally when there is no user', function () {
    // اگر session بین بارگذاری صفحه و درخواست بعدی Livewire منقضی شود، این
    // کامپوننت اولین چیزی است که به کاربر null می‌رسد.
    Livewire::test(CompanySwitcher::class)
        ->assertOk()
        ->assertSet('isSuperAdmin', false)
        ->assertSet('activeCompanyId', null);
});
