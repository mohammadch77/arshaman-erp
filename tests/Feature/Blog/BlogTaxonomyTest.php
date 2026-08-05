<?php

use App\Livewire\Blog\BlogCategoryForm;
use App\Livewire\Blog\BlogTagForm;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Livewire\Livewire;

function taxonomyMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function taxonomyGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => taxonomyMakeRole($roleName)->id,
    ]);
}

function taxonomyActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    taxonomyGiveRole($user, $company, $roleName);

    return [$user, $company];
}

it('lets an operator create a blog category', function () {
    [$operator, $company] = taxonomyActingAsWithRole('operator');
    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogCategoryForm::class)
        ->set('name', 'اخبار')
        ->set('slug', 'news')
        ->call('save')
        ->assertHasNoErrors();

    expect(BlogCategory::where('owner_company_id', $company->id)->where('slug', 'news')->exists())->toBeTrue();
});

it('rejects a duplicate category slug within the same company', function () {
    [$operator, $company] = taxonomyActingAsWithRole('operator');
    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogCategoryForm::class)
        ->set('name', 'اخبار')
        ->set('slug', 'news')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(BlogCategoryForm::class)
        ->set('name', 'اخبار دو')
        ->set('slug', 'news')
        ->call('save')
        ->assertHasErrors(['slug']);
});

it('lets an operator create a blog tag', function () {
    [$operator, $company] = taxonomyActingAsWithRole('operator');
    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogTagForm::class)
        ->set('name', 'لاراول')
        ->set('slug', 'laravel')
        ->call('save')
        ->assertHasNoErrors();

    expect(BlogTag::where('owner_company_id', $company->id)->where('slug', 'laravel')->exists())->toBeTrue();
});

it('forbids a viewer from creating a category or tag but allows viewing the lists', function () {
    [$viewer, $company] = taxonomyActingAsWithRole('viewer');
    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    $this->get('/blog/categories')->assertOk();
    $this->get('/blog/categories/create')->assertForbidden();

    $this->get('/blog/tags')->assertOk();
    $this->get('/blog/tags/create')->assertForbidden();
});

it('forbids an accountant from creating a category or tag', function () {
    [$accountant, $company] = taxonomyActingAsWithRole('accountant');
    $this->actingAs($accountant);
    session(['active_company_id' => $company->id]);

    $this->get('/blog/categories/create')->assertForbidden();
    $this->get('/blog/tags/create')->assertForbidden();
});
