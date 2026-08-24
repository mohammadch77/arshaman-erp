<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Services\ExchangeRateResolver;
use App\Modules\Sales\Enums\OrderSource;
use App\Modules\Sales\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * پردازش idempotent یک سفارش خام ووکامرس. تنها caller اش Job
 * SyncWooCommerceOrders است — یک فرآیند سیستمی بدون User actor، دقیقاً الگوی
 * PublishScheduledPost (بند ۹ CLAUDE.md: وقتی کاربری برای authorize کردن
 * وجود ندارد، از مسیر Gate/Policy عبور نمی‌کنیم).
 *
 * idempotency بر پایه‌ی (owner_company_id, source=woocommerce,
 * external_order_id) است: اگر سفارش از قبل وجود داشت، دست‌نخورده و بدون هیچ
 * بازنویسی برگردانده می‌شود — چون ستون‌های مالی سفارش می‌توانند قفل‌شده باشند
 * (بند ۶ CLAUDE.md، Order::booted) و این Action هرگز نباید سعی کند آن‌ها را
 * دوباره بنویسد. تغییر وضعیت سفارش بعد از دریافت اولیه کار
 * TransitionOrderStatus است، نه این Action.
 */
class SyncWooCommerceOrder
{
    public function handle(Company $company, array $wcOrder): Order
    {
        $externalOrderId = (string) $wcOrder['id'];

        $existing = Order::withoutGlobalScopes()
            ->where('owner_company_id', $company->id)
            ->where('source', OrderSource::Woocommerce)
            ->where('external_order_id', $externalOrderId)
            ->first();

        if ($existing) {
            return $existing;
        }

        if (empty($wcOrder['line_items'])) {
            throw new InvalidArgumentException("سفارش ووکامرس #{$externalOrderId} هیچ قلمی ندارد.");
        }

        return DB::transaction(function () use ($company, $wcOrder, $externalOrderId) {
            // قفل ردیف واقعی companies برای سریال‌سازی تولید order_number —
            // همان الگوی CreateManualOrder/ReceiveStock/IssueStock.
            $lockedCompany = Company::query()->where('id', $company->id)->lockForUpdate()->first();

            $party = $this->resolveParty($lockedCompany, $wcOrder);

            [$lines, $subtotal, $currencyId, $exchangeRateSnapshot] = $this->buildLines($lockedCompany, $wcOrder);

            $shippingAmount = Money::round((string) ($wcOrder['shipping_total'] ?? '0'));
            $totalAmount = Money::round(Money::add($subtotal, $shippingAmount));

            $nextOrderNumber = (int) Order::withoutGlobalScopes()
                ->where('owner_company_id', $lockedCompany->id)
                ->max('order_number') + 1;

            $order = Order::create([
                'owner_company_id' => $lockedCompany->id,
                'order_number' => $nextOrderNumber,
                'party_id' => $party->id,
                'order_status' => 'received',
                'source' => OrderSource::Woocommerce,
                'external_order_id' => $externalOrderId,
                'exchange_rate_snapshot' => $exchangeRateSnapshot,
                'currency_id' => $currencyId,
                'subtotal_amount' => Money::round($subtotal),
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
            ]);

            foreach ($lines as $line) {
                $order->lines()->create($line);
            }

            activity()
                ->causedBy(null)
                ->performedOn($order)
                ->withProperties(['event' => 'woocommerce_sync', 'external_order_id' => $externalOrderId])
                ->log('سفارش از ووکامرس همگام‌سازی شد');

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $wcOrder
     */
    private function resolveParty(Company $company, array $wcOrder): Party
    {
        $billing = $wcOrder['billing'] ?? [];
        $email = trim((string) ($billing['email'] ?? ''));
        $phone = trim((string) ($billing['phone'] ?? ''));

        $baseQuery = Party::withoutGlobalScopes()
            ->where('owner_company_id', $company->id)
            ->where('is_customer', true);

        if ($email !== '') {
            $existing = (clone $baseQuery)->where('email', $email)->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($phone !== '') {
            $existing = (clone $baseQuery)->where('phone', $phone)->first();

            if ($existing) {
                return $existing;
            }
        }

        $name = trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''));
        $name = $name !== '' ? $name : 'مشتری ووکامرس #'.($wcOrder['id'] ?? '');

        $party = Party::create([
            'owner_company_id' => $company->id,
            'name' => $name,
            'party_type' => 'individual',
            'is_customer' => true,
            'is_supplier' => false,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'economic_code' => null,
            'address' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        activity()
            ->causedBy(null)
            ->performedOn($party)
            ->withProperties(['event' => 'woocommerce_sync_auto_customer'])
            ->log('مشتری خودکار از سفارش ووکامرس ساخته شد');

        return $party;
    }

    /**
     * @param  array<string, mixed>  $wcOrder
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: ?string, 3: ?string}
     */
    private function buildLines(Company $company, array $wcOrder): array
    {
        $lines = [];
        $subtotal = '0';
        $currencyId = null;

        $wcCurrency = strtoupper(trim((string) ($wcOrder['currency'] ?? '')));
        $isToman = in_array($wcCurrency, ['', 'IRR', 'IRT', 'TOMAN'], true);

        if (! $isToman) {
            $currency = Currency::where('code', $wcCurrency)->first();

            if ($currency) {
                $currencyId = $currency->id;
            } else {
                Log::warning('ارز سفارش ووکامرس در سیستم ثبت نشده — به‌عنوان تومان درنظر گرفته شد.', [
                    'owner_company_id' => $company->id,
                    'currency' => $wcCurrency,
                    'external_order_id' => $wcOrder['id'] ?? null,
                ]);
            }
        }

        foreach ($wcOrder['line_items'] as $item) {
            $product = $this->resolveProduct($company, $item);

            $quantity = (string) ($item['quantity'] ?? '1');
            $lineTotal = Money::round((string) ($item['total'] ?? '0'));
            $unitSalePrice = Money::isGreaterThan($quantity, '0')
                ? Money::round(Money::divide($lineTotal, $quantity))
                : Money::round((string) $product->sale_price);

            $subtotal = Money::add($subtotal, $lineTotal);

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_sale_price_amount' => $unitSalePrice,
                'unit_cost_amount' => $product->cost_price !== null ? (string) $product->cost_price : null,
                'fulfillment_type' => $product->fulfillment_type,
                'line_total_amount' => $lineTotal,
            ];
        }

        $exchangeRateSnapshot = $currencyId !== null
            ? Money::round(app(ExchangeRateResolver::class)->rate($currencyId, now()))
            : null;

        return [$lines, $subtotal, $currencyId, $exchangeRateSnapshot];
    }

    /**
     * محصول ناشناخته (بدون woocommerce_product_id مطابق در سیستم) خودکار با
     * cost_price خالی ساخته می‌شود + هشدار — نه خطا، طبق بند ۲ این Session.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveProduct(Company $company, array $item): Product
    {
        $wcProductId = isset($item['product_id']) && $item['product_id'] !== null
            ? (string) $item['product_id']
            : null;

        if ($wcProductId !== null) {
            $existing = Product::withoutGlobalScopes()
                ->where('owner_company_id', $company->id)
                ->where('woocommerce_product_id', $wcProductId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $name = (string) ($item['name'] ?? 'محصول ووکامرس');
        $quantity = (string) ($item['quantity'] ?? '1');
        $lineTotal = (string) ($item['total'] ?? '0');
        $salePrice = Money::isGreaterThan($quantity, '0')
            ? Money::round(Money::divide($lineTotal, $quantity))
            : '0';

        $product = Product::create([
            'owner_company_id' => $company->id,
            'category_id' => null,
            'name' => $name,
            'sku' => null,
            'sale_price' => $salePrice,
            'cost_price' => null,
            'reorder_point' => null,
            'currency_id' => null,
            'fulfillment_type' => 'physical',
            'unit_of_measure' => 'piece',
            'woocommerce_product_id' => $wcProductId,
            'is_active' => true,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        Log::warning('محصول ووکامرس ناشناخته — خودکار با بهای تمام‌شده نامشخص ساخته شد.', [
            'owner_company_id' => $company->id,
            'product_name' => $name,
            'woocommerce_product_id' => $wcProductId,
        ]);

        activity()
            ->causedBy(null)
            ->performedOn($product)
            ->withProperties(['event' => 'woocommerce_sync_auto_product'])
            ->log('محصول ناشناخته از سفارش ووکامرس خودکار ساخته شد');

        return $product;
    }
}
