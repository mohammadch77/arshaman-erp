<?php

use App\Modules\Core\Models\Company;
use App\Modules\Sales\Actions\SyncWooCommerceOrder;
use App\Modules\Sales\Enums\OrderSource;
use App\Modules\Sales\Jobs\SyncWooCommerceOrders;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\WooCommerceClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function wcMakeCompany(string $name = 'Verifex'): Company
{
    return Company::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'business_type' => 'hybrid',
        'woocommerce_config' => [
            'url' => 'https://'.Str::slug($name).'.test',
            'key' => 'ck_test',
            'secret' => 'cs_test',
        ],
    ]);
}

function wcRawOrder(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'currency' => 'IRR',
        'shipping_total' => '15000',
        'billing' => [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'email' => "customer{$id}@example.com",
            'phone' => '09120000'.$id,
        ],
        'line_items' => [
            [
                'product_id' => 500 + $id,
                'name' => 'کالای ووکامرسی '.$id,
                'quantity' => '2',
                'total' => '200000',
            ],
        ],
    ], $overrides);
}

it('does not duplicate an order when the same company is synced twice', function () {
    $company = wcMakeCompany();

    Http::fake([
        '*/wp-json/wc/v3/orders*' => Http::response([wcRawOrder(101)], 200),
    ]);

    (new SyncWooCommerceOrders($company->id))->handle(app(WooCommerceClient::class), app(SyncWooCommerceOrder::class));
    (new SyncWooCommerceOrders($company->id))->handle(app(WooCommerceClient::class), app(SyncWooCommerceOrder::class));

    $orders = Order::withoutGlobalScopes()
        ->where('owner_company_id', $company->id)
        ->where('source', OrderSource::Woocommerce)
        ->where('external_order_id', '101')
        ->get();

    expect($orders)->toHaveCount(1);
    expect((int) $orders->first()->order_number)->toBe(1);
});

it('auto-creates an unknown product with a null cost_price and logs a warning', function () {
    $company = wcMakeCompany();

    Http::fake([
        '*/wp-json/wc/v3/orders*' => Http::response([wcRawOrder(202)], 200),
    ]);

    Log::shouldReceive('warning')->once()->with(
        'محصول ووکامرس ناشناخته — خودکار با بهای تمام‌شده نامشخص ساخته شد.',
        Mockery::on(fn ($context) => $context['owner_company_id'] === $company->id),
    );

    app(SyncWooCommerceOrder::class)->handle($company, wcRawOrder(202));

    $order = Order::withoutGlobalScopes()
        ->where('owner_company_id', $company->id)
        ->where('external_order_id', '202')
        ->firstOrFail();

    $line = $order->lines()->firstOrFail();
    $product = \App\Modules\Catalog\Models\Product::withoutGlobalScopes()->findOrFail($line->product_id);

    expect($product->cost_price)->toBeNull();
    expect($product->woocommerce_product_id)->toBe((string) (500 + 202));
});

it('does not touch an existing order on re-sync, even if the raw payload changed', function () {
    $company = wcMakeCompany();

    Http::fake([
        '*/wp-json/wc/v3/orders*' => Http::response([wcRawOrder(303)], 200),
    ]);

    app(SyncWooCommerceOrder::class)->handle($company, wcRawOrder(303));

    // یک payload متفاوت با همان external_order_id — نباید سفارش موجود را بازنویسی کند.
    $result = app(SyncWooCommerceOrder::class)->handle($company, wcRawOrder(303, ['shipping_total' => '999999']));

    expect((string) $result->shipping_amount)->toBe('15000.00');
});

it('logs and continues when one company API call fails, without affecting other companies', function () {
    $brokenCompany = wcMakeCompany('BrokenShop');
    $healthyCompany = wcMakeCompany('HealthyShop');

    Http::fake([
        'https://brokenshop.test/*' => Http::response('server error', 500),
        'https://healthyshop.test/*' => Http::response([wcRawOrder(404)], 200),
    ]);

    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('error')->once()->with(
        'همگام‌سازی ووکامرس برای این شرکت با خطا مواجه شد.',
        Mockery::on(fn ($context) => $context['owner_company_id'] === $brokenCompany->id),
    );

    (new SyncWooCommerceOrders($brokenCompany->id))->handle(app(WooCommerceClient::class), app(SyncWooCommerceOrder::class));
    (new SyncWooCommerceOrders($healthyCompany->id))->handle(app(WooCommerceClient::class), app(SyncWooCommerceOrder::class));

    expect(Order::withoutGlobalScopes()->where('owner_company_id', $brokenCompany->id)->count())->toBe(0);
    expect(Order::withoutGlobalScopes()->where('owner_company_id', $healthyCompany->id)->count())->toBe(1);
});

it('reuses an existing party matched by email instead of creating a duplicate customer', function () {
    $company = wcMakeCompany();

    app(SyncWooCommerceOrder::class)->handle($company, wcRawOrder(501));
    $firstOrder = Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->where('external_order_id', '501')->firstOrFail();

    app(SyncWooCommerceOrder::class)->handle($company, wcRawOrder(502, [
        'billing' => [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'email' => 'customer501@example.com',
            'phone' => '09999999999',
        ],
    ]));
    $secondOrder = Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->where('external_order_id', '502')->firstOrFail();

    expect($secondOrder->party_id)->toBe($firstOrder->party_id);
});

it('skips a single malformed order but still processes the rest for the same company', function () {
    $company = wcMakeCompany();

    $malformed = wcRawOrder(601, ['line_items' => []]);
    $valid = wcRawOrder(602);

    Http::fake([
        '*/wp-json/wc/v3/orders*' => Http::response([$malformed, $valid], 200),
    ]);

    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('error')->once()->with(
        'پردازش یک سفارش ووکامرس با خطا مواجه شد — این سفارش رد شد.',
        Mockery::on(fn ($context) => $context['external_order_id'] === 601),
    );

    (new SyncWooCommerceOrders($company->id))->handle(app(WooCommerceClient::class), app(SyncWooCommerceOrder::class));

    expect(Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->where('external_order_id', '601')->exists())->toBeFalse();
    expect(Order::withoutGlobalScopes()->where('owner_company_id', $company->id)->where('external_order_id', '602')->exists())->toBeTrue();
});
