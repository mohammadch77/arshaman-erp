<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * جابجایی موجودی همان محصول بین دو انبار، در همان شرکت مالک — کاهش مبدأ،
 * افزایش مقصد، در یک تراکنش؛ دو StockMovement (transfer_out روی مبدأ،
 * transfer_in روی مقصد) با یک stock_transfer_id مشترک.
 *
 * ترتیب قفل دو ردیف stocks بر اساس warehouse_id صعودی است، نه stock_id
 * (که نقشه اولیه پیشنهاد داده بود) و نه بر اساس مبدأ/مقصد جابجایی جاری.
 *
 * چرا نه stock_id: اگر این اولین‌بار باشد که این محصول به انبار مقصد
 * منتقل می‌شود، ردیف stocks مقصد هنوز وجود ندارد — دقیقاً همان چیزی که
 * firstOrCreate پایین می‌سازد. یعنی stock_id مقصد پیش از هر قفلی اصلاً
 * معلوم نیست، پس نمی‌شود بر اساس آن مرتب کرد.
 *
 * چرا warehouse_id همان تضمین را می‌دهد: product_id/owner_company_id بین
 * دو ردیف این جابجایی یکسان‌اند، پس warehouse_id تنها بخش متغیر کلید
 * طبیعی (product_id, warehouse_id, owner_company_id) است — و بر خلاف
 * stock_id، از همان ورودی تابع (from_warehouse_id/to_warehouse_id) از قبل
 * معلوم است، چه ردیف stocks وجود داشته باشد چه نه. collect([...])->sort()
 * روی مجموعه دو مقدار عمل می‌کند نه ترتیب پارامترها، پس دو جابجایی هم‌زمان
 * بین همان دو انبار (در هر جهتی) همیشه دقیقاً همان یک ترتیب را برای قفل
 * دنبال می‌کنند.
 *
 * فراتر از این pairwise: چون warehouse_id یک UUID ثابت و از پیش معلوم
 * است (نه وابسته به محتوای تراکنش)، این ترتیب یک ترتیب کلی سراسری روی
 * همه انبارهاست (الگوی استاندارد resource ordering) — حتی یک چرخه سه‌طرفه
 * هم‌زمان روی همان محصول/شرکت (A→B، B→C، C→A) هم safe می‌ماند، چون هر
 * تراکنش همیشه اول انبار با id کوچک‌تر را قفل می‌کند.
 */
class TransferStock
{
    /**
     * @param  array{owner_company_id: string, product_id: string, from_warehouse_id: string, to_warehouse_id: string, quantity: float|string, note?: ?string}  $data
     */
    public function handle(array $data, User $actor): StockTransfer
    {
        Gate::forUser($actor)->authorize('manage', [Stock::class, $data['owner_company_id']]);

        if ($data['from_warehouse_id'] === $data['to_warehouse_id']) {
            throw new InvalidArgumentException('انبار مبدأ و مقصد نمی‌توانند یکسان باشند.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $warehouseIdsAscending = collect([$data['from_warehouse_id'], $data['to_warehouse_id']])->sort()->values();

            $stocksByWarehouse = [];

            foreach ($warehouseIdsAscending as $warehouseId) {
                $stocksByWarehouse[$warehouseId] = Stock::withoutGlobalScopes()->lockForUpdate()->firstOrCreate([
                    'owner_company_id' => $data['owner_company_id'],
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $warehouseId,
                ], [
                    'quantity_on_hand' => 0,
                ]);
            }

            $fromStock = $stocksByWarehouse[$data['from_warehouse_id']];
            $toStock = $stocksByWarehouse[$data['to_warehouse_id']];

            if ((float) $fromStock->quantity_on_hand < (float) $data['quantity']) {
                throw new InvalidArgumentException('موجودی کافی برای جابجایی در انبار مبدأ وجود ندارد.');
            }

            $fromAverageCost = $fromStock->average_cost !== null ? (string) $fromStock->average_cost : null;
            $toQuantityBefore = (string) $toStock->quantity_on_hand;

            if (Money::isGreaterThan($toQuantityBefore, '0')) {
                // مقصد قبلاً موجودی داشت → میانگین موزون، با unit_cost = average_cost مبدأ.
                $toAverageCostBefore = $toStock->average_cost !== null ? (string) $toStock->average_cost : '0';
                $transferUnitCost = $fromAverageCost ?? '0';

                $oldValue = Money::multiply($toQuantityBefore, $toAverageCostBefore);
                $transferValue = Money::multiply((string) $data['quantity'], $transferUnitCost);
                $newToQuantity = Money::add($toQuantityBefore, (string) $data['quantity']);

                $newToAverageCost = Money::round(Money::divide(Money::add($oldValue, $transferValue), $newToQuantity));
            } else {
                // مقصد کاملاً خالی بود → مستقیم average_cost مبدأ را بگیرد.
                $newToAverageCost = $fromAverageCost;
            }

            $fromStock->quantity_on_hand = Money::round((string) Money::subtract((string) $fromStock->quantity_on_hand, (string) $data['quantity']), 4);
            $fromStock->save();

            $toStock->quantity_on_hand = Money::round((string) Money::add($toQuantityBefore, (string) $data['quantity']), 4);
            $toStock->average_cost = $newToAverageCost;
            $toStock->save();

            $transfer = StockTransfer::create([
                'owner_company_id' => $data['owner_company_id'],
                'product_id' => $data['product_id'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'quantity' => $data['quantity'],
                'note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            StockMovement::create([
                'owner_company_id' => $data['owner_company_id'],
                'stock_id' => $fromStock->id,
                'stock_transfer_id' => $transfer->id,
                'movement_type' => MovementType::TransferOut,
                'quantity' => $data['quantity'],
                'unit_cost' => null,
                'reference_note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            StockMovement::create([
                'owner_company_id' => $data['owner_company_id'],
                'stock_id' => $toStock->id,
                'stock_transfer_id' => $transfer->id,
                'movement_type' => MovementType::TransferIn,
                'quantity' => $data['quantity'],
                'unit_cost' => $fromAverageCost,
                'reference_note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($transfer)
                ->withProperties([
                    'product_id' => $data['product_id'],
                    'from_warehouse_id' => $data['from_warehouse_id'],
                    'to_warehouse_id' => $data['to_warehouse_id'],
                    'quantity' => (string) $data['quantity'],
                    'new_from_quantity_on_hand' => (string) $fromStock->quantity_on_hand,
                    'new_to_quantity_on_hand' => (string) $toStock->quantity_on_hand,
                    'new_to_average_cost' => $toStock->average_cost,
                ])
                ->log('جابجایی موجودی');

            return $transfer;
        });
    }
}
