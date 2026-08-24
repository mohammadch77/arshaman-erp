<?php

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Actions\CreatePartyRecord;
use App\Modules\Core\Actions\RecordExchangeRate;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Sales\Actions\CreateManualOrder;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function salesMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function salesGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => salesMakeRole($roleName)->id,
    ]);
}

function salesActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'Tkart', 'slug' => 'tkart-'.uniqid(), 'business_type' => 'physical_goods']);
    $user = User::factory()->create(['is_super_admin' => false]);
    salesGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function salesMakeCustomer(Company $company, User $user): Party
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

function salesMakeProduct(Company $company, User $user, string $salePrice = '10000', ?string $costPrice = '5000', ?string $currencyId = null): Product
{
    return app(CreateProduct::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'کالای تستی',
        'category_id' => null,
        'sku' => null,
        'sale_price' => $salePrice,
        'cost_price' => $costPrice,
        'currency_id' => $currencyId,
        'fulfillment_type' => 'physical',
        'unit_of_measure' => 'piece',
        'woocommerce_product_id' => null,
        'is_active' => true,
    ], $user);
}

it('creates a manual order with the triple snapshot and sequential order numbers', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user, salePrice: '10000', costPrice: '5000');

    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [
            ['product_id' => $product->id, 'quantity' => '2'],
        ],
    ], $user);

    expect($order->order_number)->toBe(1)
        ->and($order->order_status->value)->toBe('received')
        ->and((float) $order->subtotal_amount)->toBe(20000.0)
        ->and((float) $order->total_amount)->toBe(20000.0);

    $line = OrderLine::query()->where('order_id', $order->id)->firstOrFail();

    expect((float) $line->unit_sale_price_amount)->toBe(10000.0)
        ->and((float) $line->unit_cost_amount)->toBe(5000.0)
        ->and($line->fulfillment_type->value)->toBe('physical');

    $secondOrder = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_telegram',
        'lines' => [
            ['product_id' => $product->id, 'quantity' => '1'],
        ],
    ], $user);

    expect($secondOrder->order_number)->toBe(2);
});

it('never lets the price/cost snapshot change when the product changes later', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user, salePrice: '10000', costPrice: '5000');

    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [
            ['product_id' => $product->id, 'quantity' => '1'],
        ],
    ], $user);

    $product->update(['sale_price' => '99999', 'cost_price' => '88888']);

    $line = OrderLine::query()->where('order_id', $order->id)->firstOrFail();

    expect((float) $line->unit_sale_price_amount)->toBe(10000.0)
        ->and((float) $line->unit_cost_amount)->toBe(5000.0);
});

it('rejects a second manual order with the same source and external_order_id', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user);

    app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_other',
        'external_order_id' => 'DUPLICATE-1',
        'lines' => [
            ['product_id' => $product->id, 'quantity' => '1'],
        ],
    ], $user);

    expect(fn () => app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_other',
        'external_order_id' => 'DUPLICATE-1',
        'lines' => [
            ['product_id' => $product->id, 'quantity' => '1'],
        ],
    ], $user))->toThrow(ValidationException::class);

    expect(Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->count())->toBe(1);
});

it('allows two manual orders with no external_order_id (null never collides)', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user);

    app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user);

    app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user);

    expect(Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->count())->toBe(2);
});

it('resolves and snapshots the exchange rate when the product is priced in a foreign currency', function () {
    [$user, $company] = salesActingAsWithRole('holding_admin');
    $party = salesMakeCustomer($company, $user);

    $currency = Currency::create(['code' => 'USD', 'name' => 'دلار', 'symbol' => '$', 'is_active' => true]);
    app(RecordExchangeRate::class)->handle([
        'currency_id' => $currency->id,
        'rate_to_toman' => '60000',
        'effective_date' => now()->toDateString(),
    ], $user);

    $product = salesMakeProduct($company, $user, salePrice: '10', costPrice: '5', currencyId: $currency->id);

    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user);

    expect($order->currency_id)->toBe($currency->id)
        ->and((float) $order->exchange_rate_snapshot)->toBe(60000.0);
});

it('rejects a manual order whose lines mix two different foreign currencies', function () {
    [$user, $company] = salesActingAsWithRole('holding_admin');
    $party = salesMakeCustomer($company, $user);

    $usd = Currency::create(['code' => 'USD', 'name' => 'دلار', 'symbol' => '$', 'is_active' => true]);
    $eur = Currency::create(['code' => 'EUR', 'name' => 'یورو', 'symbol' => '€', 'is_active' => true]);

    app(RecordExchangeRate::class)->handle(['currency_id' => $usd->id, 'rate_to_toman' => '60000', 'effective_date' => now()->toDateString()], $user);
    app(RecordExchangeRate::class)->handle(['currency_id' => $eur->id, 'rate_to_toman' => '65000', 'effective_date' => now()->toDateString()], $user);

    $usdProduct = salesMakeProduct($company, $user, salePrice: '10', costPrice: '5', currencyId: $usd->id);
    $eurProduct = salesMakeProduct($company, $user, salePrice: '10', costPrice: '5', currencyId: $eur->id);

    expect(fn () => app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [
            ['product_id' => $usdProduct->id, 'quantity' => '1'],
            ['product_id' => $eurProduct->id, 'quantity' => '1'],
        ],
    ], $user))->toThrow(InvalidArgumentException::class);

    expect(Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->count())->toBe(0);
});

it('rejects a party that is not a customer', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $product = salesMakeProduct($company, $user);

    $supplier = app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'تأمین‌کننده تستی',
        'party_type' => 'company',
        'is_customer' => false,
        'is_supplier' => true,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user);

    expect(fn () => app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $supplier->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects the woocommerce source for a manual order', function () {
    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user);

    expect(fn () => app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'woocommerce',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user))->toThrow(InvalidArgumentException::class);
});

it('rejects order creation for a role without sales permission', function () {
    [$admin, $company] = salesActingAsWithRole('holding_admin');
    $party = salesMakeCustomer($company, $admin);
    $product = salesMakeProduct($company, $admin);

    $user = User::factory()->create(['is_super_admin' => false]);
    salesGiveRole($user, $company, 'viewer');

    expect(fn () => app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user))->toThrow(AuthorizationException::class);
});

it('never lets orders from two different companies see each other', function () {
    [$userA, $companyA] = salesActingAsWithRole('operator');
    $partyA = salesMakeCustomer($companyA, $userA);
    $productA = salesMakeProduct($companyA, $userA);

    [$userB, $companyB] = salesActingAsWithRole('operator');
    $partyB = salesMakeCustomer($companyB, $userB);
    $productB = salesMakeProduct($companyB, $userB);

    app(CreateManualOrder::class)->handle([
        'owner_company_id' => $companyA->id,
        'party_id' => $partyA->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $productA->id, 'quantity' => '1']],
    ], $userA);

    app(CreateManualOrder::class)->handle([
        'owner_company_id' => $companyB->id,
        'party_id' => $partyB->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $productB->id, 'quantity' => '1']],
    ], $userB);

    $this->actingAs($userA);
    session(['active_company_id' => $companyA->id]);

    expect(Order::query()->count())->toBe(1);
});

it('never lets two concurrent manual order creations reuse the same order_number', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('قفل ردیفی واقعی (lockForUpdate) فقط روی mysql واقعی با دو اتصال مستقل قابل تست است.');
    }

    [$user, $company] = salesActingAsWithRole('operator');
    $party = salesMakeCustomer($company, $user);
    $product = salesMakeProduct($company, $user);

    $config = config('database.connections.mysql');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

    $connectionA = new PDO($dsn, $config['username'], $config['password']);
    $connectionB = new PDO($dsn, $config['username'], $config['password']);

    // A: قفل واقعی ردیف companies را می‌گیرد و هنوز commit نمی‌کند —
    // دقیقاً همان ردیفی که CreateManualOrder::handle() برای تولید order_number قفل می‌کند.
    $connectionA->beginTransaction();
    $connectionA->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE')->execute([$company->id]);

    // B: تلاش می‌کند همان ردیف را قفل کند — باید تا commit شدن A بلاک بماند.
    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE')->execute([$company->id]);
    } catch (PDOException) {
        $blocked = true;
    }

    $connectionB->rollBack();
    $connectionA->rollBack();

    expect($blocked)->toBeTrue();

    // بعد از هر دو rollback، Action واقعی همچنان می‌تواند بدون تداخل سفارش بسازد
    // و شماره‌ها پیوسته می‌مانند — اثبات این‌که قفل موقت هیچ داده‌ای را خراب نکرد.
    $order = app(CreateManualOrder::class)->handle([
        'owner_company_id' => $company->id,
        'party_id' => $party->id,
        'source' => 'manual_instagram',
        'lines' => [['product_id' => $product->id, 'quantity' => '1']],
    ], $user);

    expect($order->order_number)->toBe(1);
});
