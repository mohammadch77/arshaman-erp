<?php

use App\Livewire\Catalog\ProductForm;
use App\Livewire\Catalog\ProductIndex;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function productMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function productGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => productMakeRole($roleName)->id,
    ]);
}

function productActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    productGiveRole($user, $company, $roleName);

    return [$user, $company];
}

it('shows a cost-price-missing warning in the form when cost_price is empty', function () {
    [$user, $company] = productActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(ProductForm::class)
        ->assertSet('showsCostWarning', true)
        ->set('cost_price', '10000')
        ->assertSet('showsCostWarning', false)
        ->set('cost_price', '')
        ->assertSet('showsCostWarning', true);
});

it('shows a cost-price-missing badge in the product list when cost_price is null', function () {
    [$user, $company] = productActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'محصول بدون بها',
        'category_id' => null,
        'sale_price' => '50000',
        'cost_price' => null,
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);

    Livewire::test(ProductIndex::class)
        ->assertSee('محصول بدون بها')
        ->assertSee('بهای تمام‌شده نامشخص');
});

it('creates a product with a chosen fulfillment_type and shows it filtered in the list', function () {
    [$user, $company] = productActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(ProductForm::class)
        ->set('name', 'دانلود قالب گرافیکی ویژه')
        ->set('sale_price', '25000')
        ->set('fulfillment_type', 'digital')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'دانلود قالب گرافیکی ویژه')->firstOrFail();
    expect($product->fulfillment_type->value)->toBe('digital');

    Livewire::test(ProductIndex::class)
        ->set('fulfillmentType', 'digital')
        ->assertSee('دانلود قالب گرافیکی ویژه');

    Livewire::test(ProductIndex::class)
        ->set('fulfillmentType', 'service')
        ->assertDontSee('دانلود قالب گرافیکی ویژه');
});

it('searches products by name', function () {
    [$user, $company] = productActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'طراحی سایت شرکتی',
        'category_id' => null,
        'sale_price' => '100000',
        'cost_price' => '50000',
        'currency_id' => null,
        'fulfillment_type' => 'service',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);

    app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کارت دعای NFC',
        'category_id' => null,
        'sale_price' => '75000',
        'cost_price' => '30000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);

    Livewire::test(ProductIndex::class)
        ->set('search', 'طراحی')
        ->assertSee('طراحی سایت شرکتی')
        ->assertDontSee('کارت دعای NFC');
});

it('forbids a viewer role from creating or updating a product but allows viewing', function () {
    [$user, $company] = productActingAsWithRole('viewer');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $this->get('/products')->assertOk();
    $this->get('/products/create')->assertForbidden();

    expect(fn () => app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'نفوذی',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => null,
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user))->toThrow(AuthorizationException::class);
});

it('forbids a user with no role in any company from viewing products', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    $this->get('/products')->assertForbidden();
});

it('rejects an operator of company A creating a product for company B where they have no role at all', function () {
    [$operatorOfA] = productActingAsWithRole('operator');
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);

    expect(fn () => app(CreateProduct::class)->handle([
        'owner_company_id' => $companyB->id,
        'name' => 'نفوذی شرکت ب',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => null,
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $operatorOfA))->toThrow(AuthorizationException::class);

    expect(Product::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->exists())->toBeFalse();
});
