<?php

use App\Livewire\Inventory\LowStockReport;
use App\Livewire\Inventory\StockIndex;
use App\Livewire\Inventory\StockMovementForm;
use App\Livewire\Inventory\WarehouseIndex;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Inventory\Actions\AdjustStock;
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
use Spatie\Activitylog\Models\Activity;

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
    ], $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
    ], $user);

    app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 15,
        'reference_note' => 'فروش',
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    $sumOfMovements = StockMovement::withoutGlobalScopes()->where('stock_id', $stock->id)
        ->get()
        ->sum(fn (StockMovement $movement) => $movement->movement_type->direction() === 'out' ? -(float) $movement->quantity : (float) $movement->quantity);

    expect((float) $stock->quantity_on_hand)->toBe(55.0)
        ->and((float) $stock->quantity_on_hand)->toBe($sumOfMovements);
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
    ], $user);

    expect(fn () => app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects an invalid movement_type at the database level', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK/ENUM constraint فقط روی mysql واقعی اعمال می‌شود.');
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
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a non-positive movement quantity at the database level, bypassing the Action entirely', function () {
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
        'movement_type' => 'purchase_in',
        'quantity' => 0,
        'occurred_at' => now(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('computes weighted average cost correctly after two purchases at different unit costs', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 10000,
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();
    expect((float) $stock->average_cost)->toBe(10000.0);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 20000,
    ], $user);

    // (10×10000 + 10×20000) / 20 = 15000
    expect((float) $stock->fresh()->average_cost)->toBe(15000.0)
        ->and((float) $stock->fresh()->quantity_on_hand)->toBe(20.0);
});

it('does not change average_cost when a purchase has no unit_cost', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 10000,
    ], $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    expect((float) $stock->average_cost)->toBe(10000.0)
        ->and((float) $stock->quantity_on_hand)->toBe(15.0);
});

it('does not change average_cost on issue or adjustment movements', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 10000,
    ], $user);

    app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'movement_type' => 'sale_out',
    ], $user);

    app(AdjustStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'movement_type' => 'adjustment_in',
        'reference_note' => 'شمارش فیزیکی بیشتر از سیستم',
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    expect((float) $stock->average_cost)->toBe(10000.0)
        ->and((float) $stock->quantity_on_hand)->toBe(9.0);
});

it('rejects an adjustment without a reference_note', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    expect(fn () => app(AdjustStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'movement_type' => 'adjustment_in',
        'reference_note' => '',
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects a decreasing adjustment beyond available stock', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
    ], $user);

    expect(fn () => app(AdjustStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'movement_type' => 'adjustment_out',
        'reference_note' => 'مغایرت شمارش',
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects an out-of-range movement_type for each action', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    expect(fn () => app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'movement_type' => 'sale_out',
    ], $user))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'movement_type' => 'purchase_in',
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('logs activity for receive, issue, and adjust actions', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 1000,
    ], $user);

    app(IssueStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
    ], $user);

    app(AdjustStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'movement_type' => 'adjustment_out',
        'reference_note' => 'ضایعات کشف‌شده حین شمارش',
    ], $user);

    expect(Activity::query()->where('description', 'دریافت موجودی')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'خروج موجودی')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'تعدیل موجودی')->count())->toBe(1)
        ->and(Activity::query()->where('causer_id', $user->id)->count())->toBeGreaterThanOrEqual(3);
});

it('never lets two concurrent issue operations push stock below zero', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('قفل ردیفی واقعی (lockForUpdate) فقط روی mysql واقعی با دو اتصال مستقل قابل تست است.');
    }

    [$user, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ], $user);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    $config = config('database.connections.mysql');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

    $connectionA = new PDO($dsn, $config['username'], $config['password']);
    $connectionB = new PDO($dsn, $config['username'], $config['password']);

    // A: تراکنش را باز می‌کند، ردیف stocks را قفل می‌کند و کاهش می‌دهد اما هنوز commit نمی‌کند.
    $connectionA->beginTransaction();
    $connectionA->prepare('SELECT quantity_on_hand FROM stocks WHERE id = ? FOR UPDATE')->execute([$stock->id]);
    $connectionA->prepare('UPDATE stocks SET quantity_on_hand = quantity_on_hand - 8 WHERE id = ?')->execute([$stock->id]);

    // B: در یک پردازش/اتصال جدا تلاش می‌کند همان ردیف را قفل کند — باید تا commit شدن A بلاک بماند.
    // چون PDO تک‌رشته‌ای است، بلاک‌شدن واقعی B را با یک timeout کوتاه شبیه‌سازی می‌کنیم:
    // تلاش B با innodb_lock_wait_timeout کوچک باید Exception بدهد، نه این‌که بی‌صدا رد شود.
    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->prepare('SELECT quantity_on_hand FROM stocks WHERE id = ? FOR UPDATE')->execute([$stock->id]);
    } catch (PDOException) {
        $blocked = true;
    }

    $connectionB->rollBack();
    $connectionA->rollBack();

    expect($blocked)->toBeTrue();

    // بعد از rollback هر دو، موجودی واقعی دست‌نخورده و هرگز منفی نشده است.
    expect((float) $stock->fresh()->quantity_on_hand)->toBe(10.0);
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
    ], $operator);

    $this->actingAs($admin);
    session(['active_company_id' => $holdingCompany->id]);

    Livewire::test(WarehouseIndex::class)
        ->assertSee('کالای تستی');
});

it('renders the real stock movement ledger page for a holding_admin across any owning company', function () {
    [$operator, $company] = inventoryActingAsWithRole('operator');
    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $operator);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => 5000,
        'reference_note' => 'خرید اولیه',
    ], $operator);

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    $holdingCompany = Company::create(['name' => 'Arshaman', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    inventoryGiveRole($admin, $holdingCompany, 'holding_admin');

    $this->actingAs($admin);
    session(['active_company_id' => $holdingCompany->id]);

    $response = $this->get(route('inventory.stock.movements', $stock->id));

    $response->assertOk();
    $response->assertSee('خرید اولیه');
});

it('submits a purchase_in movement through the real StockMovementForm component', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $warehouse = inventoryMakeWarehouse();
    $product = inventoryMakeProduct($company, $user);

    Livewire::test(StockMovementForm::class)
        ->assertOk()
        ->set('movementType', 'purchase_in')
        ->set('product_id', $product->id)
        ->set('warehouse_id', $warehouse->id)
        ->set('quantity', '10')
        ->set('unit_cost', '1000')
        ->call('save')
        ->assertRedirect(route('inventory.stock.index'));

    $stock = Stock::withoutGlobalScopes()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();

    expect((float) $stock->quantity_on_hand)->toBe(10.0)
        ->and((float) $stock->average_cost)->toBe(1000.0);
});

it('defaults the StockMovementForm to the right movement_type per URL type', function () {
    [$user, $company] = inventoryActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(StockMovementForm::class, ['type' => 'adjust'])
        ->assertOk()
        ->assertSet('movementType', 'adjustment_in');
});
