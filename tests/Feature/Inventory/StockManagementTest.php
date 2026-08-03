<?php

use App\Livewire\Inventory\LowStockReport;
use App\Livewire\Inventory\StockIndex;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function inventoryMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function inventoryGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => inventoryMakeRole($roleName)->id,
    ]);
}

function inventoryActingAsWithRole(string $roleName, string $businessType = 'physical_goods'): array
{
    $company = Company::create(['name' => 'Tkart', 'slug' => 'tkart-'.uniqid(), 'business_type' => $businessType]);
    $user = User::factory()->create(['is_super_admin' => false]);
    inventoryGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function inventoryMakeWarehouse(): Warehouse
{
    return Warehouse::create(['name' => 'انبار مرکزی']);
}

function inventoryMakeProduct(Company $company, User $user, ?int $reorderPoint = null): Product
{
    return app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کالای تستی',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
        'reorder_point' => $reorderPoint,
    ], $user);
}

it('keeps stock quantity always equal to the sum of its movements', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
        'reason' => null,
    ], $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'reason' => null,
    ], $user);

    app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 15,
        'reason' => 'فروش',
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    $sumOfMovements = StockMovement::withoutGlobalScopes()->where('stock_id', $stock->id)
        ->get()
        ->sum(fn (StockMovement $movement) => $movement->movement_type->value === 'out' ? -$movement->quantity : $movement->quantity);

    expect($stock->quantity)->toBe(55)
        ->and($stock->quantity)->toBe($sumOfMovements);
});

it('rejects issuing more stock than is available', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'reason' => null,
    ], $user);

    expect(fn () => app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'reason' => null,
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects an invalid movement_type at the database level', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی mysql واقعی اعمال می‌شود.');
    }

    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    $stock = Stock::create([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
    ]);

    expect(fn () => DB::table('stock_movements')->insert([
        'id' => (string) Str::uuid(),
        'owner_company_id' => $company->id,
        'stock_id' => $stock->id,
        'movement_type' => 'invalid',
        'quantity' => 1,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('filters the low stock report to products below their reorder point', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $lowProduct = inventoryMakeProduct($company, $user, reorderPoint: 10);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $lowProduct->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'reason' => null,
    ], $user);

    $normalProduct = app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کالای موجودی کافی',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
        'reorder_point' => 5,
    ], $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $normalProduct->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'reason' => null,
    ], $user);

    Livewire::test(LowStockReport::class)
        ->assertSee('کالای تستی')
        ->assertDontSee('کالای موجودی کافی');
});

it('prevents cross-company stock access even in the same physical warehouse', function () {
    [$userA, $companyA] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $productA = inventoryMakeProduct($companyA, $userA);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 40,
        'reason' => null,
    ], $userA);

    [$userB, $companyB] = inventoryActingAsWithRole('operator');
    $this->actingAs($userB);
    session(['active_company_id' => $companyB->id]);

    Livewire::test(StockIndex::class)
        ->assertDontSee('کالای تستی');

    expect(Stock::query()->count())->toBe(0);
    expect(Stock::withoutGlobalScopes()->where('owner_company_id', $companyA->id)->sum('quantity'))->toBe(40);
});

it('forbids a viewer role from receiving or issuing stock', function () {
    [$operator, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $operator);

    $viewer = User::factory()->create(['is_super_admin' => false]);
    inventoryGiveRole($viewer, $company, 'viewer');

    expect(fn () => app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'reason' => null,
    ], $viewer))->toThrow(AuthorizationException::class);
});
