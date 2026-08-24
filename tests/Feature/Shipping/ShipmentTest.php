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
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Actions\CreateManualOrder;
use App\Modules\Sales\Actions\TransitionOrderStatus;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Actions\AssignTrackingCode;
use App\Modules\Shipping\Actions\MarkDelivered;
use App\Modules\Shipping\Actions\PackOrder;
use App\Modules\Shipping\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Validation\ValidationException;

function shpMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function shpGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => shpMakeRole($roleName)->id,
    ]);
}

function shpActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'Tkart', 'slug' => 'tkart-'.uniqid(), 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    shpGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function shpMakeCustomer(Company $company, User $user): Party
{
    return app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'مشتری تستی',
        'party_type' => 'individual',
        'is_customer' => true,
        'is_supplier' => false,
        'phone' => '09120000000',
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user);
}

function shpMakeProduct(Company $company, User $user): Product
{
    return app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کالای فیزیکی تستی',
        'category_id' => null,
        'sku' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'physical',
        'unit_of_measure' => 'piece',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);
}

/**
 * سفارش فیزیکی را تا preparing جلو می‌برد (موجودی کافی، از طریق ماشین
 * وضعیت واقعی) — دقیقاً پیش‌نیاز لازم برای شروع بسته‌بندی.
 */
function shpMakeOrderInPreparing(Company $company, User $user): Order
{
    $party = shpMakeCustomer($company, $user);
    $product = shpMakeProduct($company, $user);
    $warehouse = Warehouse::create(['name' => 'انبار مرکزی '.uniqid()]);

    app(ReceiveStock::class)->handle([
        'owner_company_id' => $company->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '10',
    ], $user);

    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '2']],
    ], $user);

    $transition = app(TransitionOrderStatus::class);
    $order = $transition->handle($order, OrderStatus::Paid, $user);
    $order = $transition->handle($order, OrderStatus::Preparing, $user);

    return $order;
}

it('packs an order and does not touch order.shipping_amount yet', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);

    $shipment = app(PackOrder::class)->handle($order, '15000', $user);

    expect($shipment->status)->toBe(ShipmentStatus::Packed);
    expect($shipment->shipping_cost_amount)->toEqual('15000.00');

    $order->refresh();
    expect((float) $order->shipping_amount)->toBe(0.0);
});

it('rejects packing an order without a physical line', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $party = shpMakeCustomer($company, $user);

    $product = app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'محصول دیجیتال',
        'category_id' => null,
        'sku' => null,
        'sale_price' => '10000',
        'cost_price' => '5000',
        'currency_id' => null,
        'fulfillment_type' => 'digital',
        'unit_of_measure' => 'piece',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);

    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user);

    expect(fn () => app(PackOrder::class)->handle($order, '15000', $user))
        ->toThrow(InvalidArgumentException::class);
});

it('assigns a tracking code, moves the order to shipped through the real state machine, and writes shipping_amount/total_amount before the financial lock', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);
    $originalSubtotal = $order->subtotal_amount;

    $shipment = app(PackOrder::class)->handle($order, '15000', $user);
    $shipment = app(AssignTrackingCode::class)->handle($shipment, 'TP-12345', $user);

    expect($shipment->status)->toBe(ShipmentStatus::Shipped);
    expect($shipment->tracking_code)->toBe('TP-12345');
    expect($shipment->shipped_at)->not->toBeNull();

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Shipped);
    expect((float) $order->shipping_amount)->toBe(15000.0);
    expect((float) $order->total_amount)->toBe((float) $originalSubtotal + 15000.0);
});

it('rejects assigning a tracking code before the shipment is packed', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);

    $shipment = Shipment::create([
        'owner_company_id' => $company->id,
        'order_id' => $order->id,
        'status' => ShipmentStatus::Pending,
        'created_by_user_id' => $user->id,
    ]);

    expect(fn () => app(AssignTrackingCode::class)->handle($shipment, 'TP-1', $user))
        ->toThrow(InvalidArgumentException::class);
});

it('marks a shipment delivered and moves the order to delivered, locking financial fields afterward', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);

    $shipment = app(PackOrder::class)->handle($order, '15000', $user);
    $shipment = app(AssignTrackingCode::class)->handle($shipment, 'TP-12345', $user);
    $shipment = app(MarkDelivered::class)->handle($shipment, $user);

    expect($shipment->status)->toBe(ShipmentStatus::Delivered);
    expect($shipment->delivered_at)->not->toBeNull();

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatus::Delivered);

    // رگرسیون قفل مالی: بعد از delivered، تلاش برای تغییر shipping_amount باید رد شود.
    expect(fn () => $order->update(['shipping_amount' => '99999']))
        ->toThrow(ValidationException::class);
});

it('rejects marking a shipment delivered before it has shipped', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);
    $shipment = app(PackOrder::class)->handle($order, '15000', $user);

    expect(fn () => app(MarkDelivered::class)->handle($shipment, $user))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a user without an authorized role from packing an order', function () {
    [$operator, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $operator);

    $viewer = User::factory()->create(['is_super_admin' => false]);
    shpGiveRole($viewer, $company, 'viewer');

    expect(fn () => app(PackOrder::class)->handle($order, '15000', $viewer))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('renders the real shipping index and form pages over HTTP with at least one row', function () {
    [$user, $company] = shpActingAsWithRole('operator');
    $order = shpMakeOrderInPreparing($company, $user);
    app(PackOrder::class)->handle($order, '15000', $user);

    test()->actingAs($user)
        ->get(route('shipping.orders.index'))
        ->assertOk()
        ->assertSee('ارسال و حمل')
        ->assertSee((string) $order->order_number);

    test()->actingAs($user)
        ->get(route('shipping.orders.show', $order->id))
        ->assertOk()
        ->assertSee('ثبت کد رهگیری');
});

it('prevents cross-company access to a shipment', function () {
    [$userA, $companyA] = shpActingAsWithRole('operator');
    $orderA = shpMakeOrderInPreparing($companyA, $userA);
    $shipmentA = app(PackOrder::class)->handle($orderA, '15000', $userA);

    [$userB, $companyB] = shpActingAsWithRole('operator');

    expect(fn () => app(AssignTrackingCode::class)->handle($shipmentA, 'TP-1', $userB))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    // ایزولاسیون کوئری: کاربر شرکت B مرسوله شرکت A را با global scope عادی نمی‌بیند.
    test()->actingAs($userB);
    expect(Shipment::where('id', $shipmentA->id)->exists())->toBeFalse();
});
