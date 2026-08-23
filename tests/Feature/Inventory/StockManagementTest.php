<?php

use App\Livewire\Inventory\LowStockReport;
use App\Livewire\Inventory\StockIndex;
use App\Livewire\Inventory\WarehouseIndex;
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

    expect((float) $stock->quantity_on_hand)->toBe(55.0)
        ->and((float) $stock->quantity_on_hand)->toBe((float) $sumOfMovements);
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
        'quantity_on_hand' => 0,
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
    expect((float) Stock::withoutGlobalScopes()->where('owner_company_id', $companyA->id)->sum('quantity_on_hand'))->toBe(40.0);
});

it('keeps stock separate for the same product/warehouse across two different owning companies', function () {
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
    $productB = app(CreateProduct::class)->handle([
        'owner_company_id' => $companyB->id,
        'name' => 'کالای تستی',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
        'reorder_point' => null,
    ], $userB);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyB->id,
        'product_id' => $productB->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 15,
        'reason' => null,
    ], $userB);

    $stockA = Stock::withoutGlobalScopes()->where('owner_company_id', $companyA->id)->where('warehouse_id', $warehouse->id)->firstOrFail();
    $stockB = Stock::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    expect((float) $stockA->quantity_on_hand)->toBe(40.0)
        ->and((float) $stockB->quantity_on_hand)->toBe(15.0)
        ->and($stockA->id)->not->toBe($stockB->id);
});

it('rejects a negative quantity_on_hand at the model level', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    expect(fn () => Stock::create([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => -5,
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a negative quantity_on_hand at the database level', function () {
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
        'quantity_on_hand' => 0,
    ]);

    expect(fn () => DB::table('stocks')->where('id', $stock->id)->update(['quantity_on_hand' => -1]))
        ->toThrow(QueryException::class);
});

it('lets a stock-level reorder_point override the product-level default', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user, reorderPoint: 100);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 30,
        'reason' => null,
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    // بدون override، آستانه محصول (۱۰۰) اعمال می‌شود و ۳۰ زیر آن است.
    expect($stock->isBelowReorderPoint())->toBeTrue();

    // با override اختصاصی انبار (۱۰)، همان ۳۰ دیگر زیر آستانه نیست.
    $stock->update(['reorder_point' => 10]);
    expect($stock->fresh()->isBelowReorderPoint())->toBeFalse();
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

it('renders the real /inventory/warehouses page over HTTP scoped to the active company for a non-admin', function () {
    [$operator, $companyA] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $productA = inventoryMakeProduct($companyA, $operator);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 12,
        'reason' => null,
    ], $operator);

    [$userB, $companyB] = inventoryActingAsWithRole('operator');
    $productB = app(CreateProduct::class)->handle([
        'owner_company_id' => $companyB->id,
        'name' => 'کالای شرکت دیگر',
        'category_id' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'woocommerce_product_id' => null,
        'is_active' => true,
        'reorder_point' => null,
    ], $userB);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyB->id,
        'product_id' => $productB->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 7,
        'reason' => null,
    ], $userB);

    $this->actingAs($operator);
    session(['active_company_id' => $companyA->id]);

    $response = $this->get(route('inventory.warehouses.index'));

    $response->assertOk();
    $response->assertSee($warehouse->name);
    $response->assertSee('کالای تستی');
    $response->assertDontSee('کالای شرکت دیگر');
});

it('lets a holding_admin see stock from every owning company in the same warehouse', function () {
    $holdingCompany = Company::create(['name' => 'Arshaman', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    inventoryGiveRole($admin, $holdingCompany, 'holding_admin');

    [$operator, $companyA] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $productA = inventoryMakeProduct($companyA, $operator);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $companyA->id,
        'product_id' => $productA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 8,
        'reason' => null,
    ], $operator);

    $this->actingAs($admin);
    session(['active_company_id' => $holdingCompany->id]);

    Livewire::test(WarehouseIndex::class)
        ->assertSee('کالای تستی');
});
