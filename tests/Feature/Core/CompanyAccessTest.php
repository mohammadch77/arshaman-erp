<?php

use App\Modules\Core\Enums\BusinessType;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Support\Facades\Route;

function makeCompany(string $slug): Company
{
    return Company::create([
        'name' => $slug,
        'slug' => $slug,
        'business_type' => BusinessType::ProjectServices,
    ]);
}

beforeEach(function () {
    Route::middleware(['web', 'auth', 'company.access'])
        ->get('/test-company/{company}', fn () => response('ok'))
        ->name('test.company-access');
});

it('prevents cross-company data access', function () {
    $companyA = makeCompany('company-a');
    $companyB = makeCompany('company-b');

    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);

    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $companyA->id,
        'assigned_role_id' => $role->id,
    ]);

    $this->actingAs($user)
        ->get("/test-company/{$companyB->id}")
        ->assertForbidden();

    $this->actingAs($user)
        ->get("/test-company/{$companyA->id}")
        ->assertOk();
});

it('allows super admin access to all companies', function () {
    $companyA = makeCompany('company-a');
    $companyB = makeCompany('company-b');

    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)->get("/test-company/{$companyA->id}")->assertOk();
    $this->actingAs($admin)->get("/test-company/{$companyB->id}")->assertOk();
});
