<?php

use App\Livewire\Core\CompanySwitcher;
use App\Modules\Core\Enums\BusinessType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
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
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
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
