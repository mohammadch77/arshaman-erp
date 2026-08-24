<?php

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Actions\CreatePartyRecord;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Actions\CreateManualOrder;
use App\Modules\Sales\Actions\TransitionOrderStatus;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\OrderStateMachine;
use Illuminate\Validation\ValidationException;

function otMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function otGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => otMakeRole($roleName)->id,
    ]);
}

function otActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'Verifex', 'slug' => 'verifex-'.uniqid(), 'business_type' => 'hybrid']);
    $user = User::factory()->create(['is_super_admin' => false]);
    otGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function otMakeCustomer(Company $company, User $user): Party
{
    return app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'مشتری تستی',
        'party_type' => 'individual',
        'is_customer' => true,
        'is_supplier' => false,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user);
}

function otMakeProduct(Company $company, User $user, string $fulfillmentType = 'physical'): Product
{
    return app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کالای تستی '.$fulfillmentType,
        'category_id' => null,
        'sku' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => $fulfillmentType,
        'unit_of_measure' => 'piece',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);
}

function otMakeOrder(Company $company, User $user, Party $party, array $lines): Order
{
    return app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => $lines,
    ], $user);
}

function otStockFor(Company $company, Product $product, Warehouse $warehouse, User $user, string $quantity): Stock
{
    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
    ], $user);

    return Stock::withoutGlobalScopes()
        ->where('owner_company_id', $company->id)
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();
}

it('gives a physical cycle to an order with a physical line', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'physical');
    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '1']]);

    $stateMachine = app(OrderStateMachine::class);

    expect($stateMachine->hasPhysicalLine($order))->toBeTrue();

    $allowed = array_map(fn ($status) => $status->value, $stateMachine->allowedTransitions($order));
    expect($allowed)->toBe(['paid', 'cancelled']);
});

it('gives a short digital cycle to a fully digital order', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'digital');
    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '1']]);

    $stateMachine = app(OrderStateMachine::class);

    expect($stateMachine->hasPhysicalLine($order))->toBeFalse();

    $order->update(['order_status' => OrderStatus::Paid]);
    $order->refresh();

    $allowed = array_map(fn ($status) => $status->value, $stateMachine->allowedTransitions($order));
    expect($allowed)->toBe(['delivered_instant', 'cancelled']);
});

it('gives a mixed order the physical cycle even though its digital line could deliver instantly', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $physicalProduct = otMakeProduct($company, $user, 'physical');
    $digitalProduct = otMakeProduct($company, $user, 'digital');

    $order = otMakeOrder($company, $user, $party, [
        ['product_id' => $physicalProduct->id, 'quantity' => '1'],
        ['product_id' => $digitalProduct->id, 'quantity' => '1'],
    ]);

    $stateMachine = app(OrderStateMachine::class);

    expect($stateMachine->hasPhysicalLine($order))->toBeTrue();

    $order->update(['order_status' => OrderStatus::Paid]);
    $order->refresh();

    $allowed = array_map(fn ($status) => $status->value, $stateMachine->allowedTransitions($order));
    expect($allowed)->toBe(['preparing', 'cancelled']);
});

it('rejects an invalid transition', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'physical');
    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '1']]);

    expect(fn () => app(TransitionOrderStatus::class)->handle($order, OrderStatus::Shipped, $user))
        ->toThrow(InvalidArgumentException::class);

    expect($order->fresh()->order_status)->toBe(OrderStatus::Received);
});

it('reduces stock when the order reaches preparing', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'physical');
    $warehouse = Warehouse::create(['name' => 'انبار مرکزی']);
    otStockFor($company, $product, $warehouse, $user, '10');

    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '3']]);
    $order->update(['order_status' => OrderStatus::Paid]);

    $order = app(TransitionOrderStatus::class)->handle($order->fresh(), OrderStatus::Preparing, $user);

    expect($order->order_status)->toBe(OrderStatus::Preparing);

    $stock = Stock::withoutGlobalScopes()
        ->where('owner_company_id', $company->id)
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect((float) $stock->quantity_on_hand)->toBe(7.0);

    $movement = StockMovement::withoutGlobalScopes()->where('stock_id', $stock->id)->where('movement_type', 'sale_out')->firstOrFail();
    expect($movement->movement_type->value)->toBe('sale_out')
        ->and((float) $movement->quantity)->toBe(3.0);
});

it('splits stock allocation across multiple warehouses when no single warehouse is enough', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'physical');

    $warehouseA = Warehouse::create(['name' => 'انبار الف']);
    $warehouseB = Warehouse::create(['name' => 'انبار ب']);
    otStockFor($company, $product, $warehouseA, $user, '5');
    otStockFor($company, $product, $warehouseB, $user, '5');

    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '8']]);
    $order->update(['order_status' => OrderStatus::Paid]);

    $order = app(TransitionOrderStatus::class)->handle($order->fresh(), OrderStatus::Preparing, $user);

    expect($order->order_status)->toBe(OrderStatus::Preparing);

    $stockA = Stock::withoutGlobalScopes()->where('warehouse_id', $warehouseA->id)->where('product_id', $product->id)->firstOrFail();
    $stockB = Stock::withoutGlobalScopes()->where('warehouse_id', $warehouseB->id)->where('product_id', $product->id)->firstOrFail();

    // ۸ عدد از مجموع ۱۰ (۵+۵) کم می‌شود؛ جمع باقی‌مانده باید ۲ باشد،
    // مستقل از اینکه دقیقاً چطور بین دو انبار تقسیم شده.
    expect((float) $stockA->quantity_on_hand + (float) $stockB->quantity_on_hand)->toBe(2.0);

    $movements = StockMovement::withoutGlobalScopes()
        ->whereIn('stock_id', [$stockA->id, $stockB->id])
        ->where('movement_type', 'sale_out')
        ->get();

    expect($movements)->toHaveCount(2)
        ->and($movements->pluck('reference_note')->unique()->count())->toBe(1)
        ->and((float) $movements->sum('quantity'))->toBe(8.0);
});

it('rejects the whole transition when total stock across all warehouses is insufficient', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'physical');

    $warehouseA = Warehouse::create(['name' => 'انبار الف']);
    $warehouseB = Warehouse::create(['name' => 'انبار ب']);
    otStockFor($company, $product, $warehouseA, $user, '3');
    otStockFor($company, $product, $warehouseB, $user, '2');

    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '8']]);
    $order->update(['order_status' => OrderStatus::Paid]);
    $order = $order->fresh();

    expect(fn () => app(TransitionOrderStatus::class)->handle($order, OrderStatus::Preparing, $user))
        ->toThrow(InvalidArgumentException::class);

    expect($order->fresh()->order_status)->toBe(OrderStatus::Paid);

    $stockA = Stock::withoutGlobalScopes()->where('warehouse_id', $warehouseA->id)->where('product_id', $product->id)->firstOrFail();
    $stockB = Stock::withoutGlobalScopes()->where('warehouse_id', $warehouseB->id)->where('product_id', $product->id)->firstOrFail();

    expect((float) $stockA->quantity_on_hand)->toBe(3.0)
        ->and((float) $stockB->quantity_on_hand)->toBe(2.0);

    expect(StockMovement::withoutGlobalScopes()->whereIn('stock_id', [$stockA->id, $stockB->id])->where('movement_type', 'sale_out')->count())->toBe(0);
});

it('locks financial fields once the order is delivered', function () {
    [$user, $company] = otActingAsWithRole('operator');
    $party = otMakeCustomer($company, $user);
    $product = otMakeProduct($company, $user, 'digital');
    $order = otMakeOrder($company, $user, $party, [['product_id' => $product->id, 'quantity' => '1']]);

    $order->update(['order_status' => OrderStatus::Paid]);
    $order->update(['order_status' => OrderStatus::DeliveredInstant]);
    $order = $order->fresh();

    expect(fn () => $order->update(['total_amount' => '999999']))
        ->toThrow(ValidationException::class);

    // ولی خودِ order_status همچنان قابل تغییر است (delivered_instant → closed).
    // fresh() لازم است چون تلاش ناموفق بالا مقدار total_amount را در حافظه dirty
    // نگه می‌دارد؛ بدون آن، همین ویرایش بعدی هم به‌اشتباه رد می‌شد.
    $order = $order->fresh();
    $order->update(['order_status' => OrderStatus::Closed]);
    expect($order->fresh()->order_status)->toBe(OrderStatus::Closed);
});
