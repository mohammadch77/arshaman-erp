<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\ExchangeRateResolver;
use App\Modules\Sales\Enums\OrderSource;
use App\Modules\Sales\Models\Order;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * ثبت سفارش دستی (اینستاگرام/تلگرام/سایر) — نه ووکامرس. Snapshot سه‌گانه
 * (بند ۵.۲ CLAUDE.md) اینجا در لحظه‌ی ثبت گرفته می‌شود و هرگز به products وصل
 * نمی‌ماند. تولید order_number و idempotency هر دو داخل همین Action.
 */
class CreateManualOrder
{
    /**
     * @param  array{owner_company_id: string, party_id: string, source: string, external_order_id?: ?string, shipping_amount?: string|float|null, lines: array<int, array{product_id: string, quantity: string|float}>}  $data
     */
    public function handle(array $data, User $actor): Order
    {
        Gate::forUser($actor)->authorize('create', [Order::class, $data['owner_company_id']]);

        $source = OrderSource::from($data['source']);

        if (! $source->isManual()) {
            throw new InvalidArgumentException('این Action فقط برای ثبت سفارش دستی است؛ سفارش ووکامرس از مسیر sync می‌آید.');
        }

        if (empty($data['lines'])) {
            throw ValidationException::withMessages(['lines' => 'سفارش باید حداقل یک قلم داشته باشد.']);
        }

        $party = Party::withoutGlobalScopes()
            ->where('owner_company_id', $data['owner_company_id'])
            ->findOrFail($data['party_id']);

        if (! $party->is_customer) {
            throw new InvalidArgumentException('طرف‌حساب انتخاب‌شده مشتری نیست.');
        }

        try {
            return $this->createInTransaction($data, $actor, $source, $party);
        } catch (QueryException $exception) {
            if ($this->isDuplicateExternalOrderViolation($exception)) {
                throw ValidationException::withMessages([
                    'external_order_id' => 'سفارشی با همین شناسه خارجی برای این شرکت قبلاً ثبت شده است.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * روی MySQL واقعی پیام شامل نام قید (uq_orders_company_source_external) است؛
     * روی sqlite (محیط تست) پیام به‌جای نام قید فهرست ستون‌ها را می‌دهد
     * ("UNIQUE constraint failed: orders.source, orders.external_order_id")
     * — هر دو الگو اینجا پوشش داده می‌شوند.
     */
    private function isDuplicateExternalOrderViolation(QueryException $exception): bool
    {
        if ((int) $exception->getCode() !== 23000) {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'uq_orders_company_source_external')
            || (str_contains($message, 'orders.source') && str_contains($message, 'orders.external_order_id'));
    }

    /**
     * @param  array{owner_company_id: string, party_id: string, source: string, external_order_id?: ?string, shipping_amount?: string|float|null, lines: array<int, array{product_id: string, quantity: string|float}>}  $data
     */
    private function createInTransaction(array $data, User $actor, OrderSource $source, Party $party): Order
    {
        return DB::transaction(function () use ($data, $actor, $source, $party) {
            // قفل ردیف واقعی companies برای سریال‌سازی تولید order_number
            // به‌ازای هر شرکت — الگوی همان قفل ردیف واقعی ReceiveStock/IssueStock.
            $company = Company::query()->where('id', $data['owner_company_id'])->lockForUpdate()->first();

            if (! $company) {
                throw new InvalidArgumentException('شرکت یافت نشد.');
            }

            $nextOrderNumber = (int) Order::withoutGlobalScopes()
                ->where('owner_company_id', $company->id)
                ->max('order_number') + 1;

            [$lines, $subtotalAmount, $currencyId, $exchangeRateSnapshot] = $this->buildLines(
                $data['lines'],
                $company->id,
            );

            $shippingAmount = (string) ($data['shipping_amount'] ?? '0');
            $totalAmount = Money::round(Money::add($subtotalAmount, $shippingAmount));

            $order = Order::create([
                'owner_company_id' => $company->id,
                'order_number' => $nextOrderNumber,
                'party_id' => $party->id,
                'order_status' => 'received',
                'source' => $source,
                'external_order_id' => $data['external_order_id'] ?? null,
                'exchange_rate_snapshot' => $exchangeRateSnapshot,
                'currency_id' => $currencyId,
                'subtotal_amount' => Money::round($subtotalAmount),
                'shipping_amount' => Money::round($shippingAmount),
                'total_amount' => $totalAmount,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $order->lines()->create($line);
            }

            return $order;
        });
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string|float}>  $rawLines
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: ?string, 3: ?string}
     */
    private function buildLines(array $rawLines, string $companyId): array
    {
        $lines = [];
        $subtotal = '0';
        $currencyId = null;
        $currencyMismatch = false;

        foreach ($rawLines as $rawLine) {
            $product = Product::withoutGlobalScopes()
                ->where('owner_company_id', $companyId)
                ->findOrFail($rawLine['product_id']);

            $quantity = (string) $rawLine['quantity'];
            $unitSalePrice = (string) $product->sale_price;
            $lineTotal = Money::round(Money::multiply($quantity, $unitSalePrice));

            $subtotal = Money::add($subtotal, $lineTotal);

            if ($product->currency_id !== null) {
                if ($currencyId === null) {
                    $currencyId = $product->currency_id;
                } elseif ($currencyId !== $product->currency_id) {
                    $currencyMismatch = true;
                }
            }

            $lines[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_sale_price_amount' => $unitSalePrice,
                'unit_cost_amount' => $product->cost_price !== null ? (string) $product->cost_price : null,
                'fulfillment_type' => $product->fulfillment_type,
                'line_total_amount' => $lineTotal,
            ];
        }

        if ($currencyMismatch) {
            throw new InvalidArgumentException(
                'اقلام این سفارش بیش از یک ارز خارجی متفاوت دارند — سفارش دستی فقط از یک ارز پشتیبانی می‌کند.'
            );
        }

        $exchangeRateSnapshot = null;

        if ($currencyId !== null) {
            $exchangeRateSnapshot = Money::round(app(ExchangeRateResolver::class)->rate($currencyId, now()));
        }

        return [$lines, $subtotal, $currencyId, $exchangeRateSnapshot];
    }
}
