<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * انبارگردانی/مغایرت — هم افزایشی هم کاهشی. برخلاف ReceiveStock، average_cost
 * را دست نمی‌زند (طبق تصمیم صریح کارفرما: میانگین موزون فقط از خرید واقعی
 * محاسبه می‌شود، نه از یک اصلاح دستی که نرخ خرید واقعی ندارد).
 */
class AdjustStock
{
    private const ALLOWED_TYPES = [MovementType::AdjustmentIn, MovementType::AdjustmentOut];

    /**
     * @param  array{owner_company_id: string, product_id: string, warehouse_id: string, quantity: float|string, movement_type: string, reference_note: string, occurred_at?: ?string}  $data
     */
    public function handle(array $data, User $actor): StockMovement
    {
        Gate::forUser($actor)->authorize('manage', [Stock::class, $data['owner_company_id']]);

        $movementType = MovementType::from($data['movement_type']);

        if (! in_array($movementType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('نوع حرکت وارد‌شده برای تعدیل موجودی مجاز نیست.');
        }

        if (trim((string) ($data['reference_note'] ?? '')) === '') {
            throw new InvalidArgumentException('ثبت دلیل تعدیل موجودی الزامی است.');
        }

        return DB::transaction(function () use ($data, $actor, $movementType) {
            $stock = Stock::withoutGlobalScopes()->lockForUpdate()
                ->where('owner_company_id', $data['owner_company_id'])
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            if ($movementType === MovementType::AdjustmentOut) {
                if ($stock === null || (float) $stock->quantity_on_hand < (float) $data['quantity']) {
                    throw new InvalidArgumentException('موجودی کافی برای تعدیل کاهشی وجود ندارد.');
                }

                $stock->quantity_on_hand = Money::round((string) Money::subtract((string) $stock->quantity_on_hand, (string) $data['quantity']), 4);
            } else {
                $stock ??= Stock::withoutGlobalScopes()->lockForUpdate()->firstOrCreate([
                    'owner_company_id' => $data['owner_company_id'],
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                ], [
                    'quantity_on_hand' => 0,
                ]);

                $stock->quantity_on_hand = Money::round((string) Money::add((string) $stock->quantity_on_hand, (string) $data['quantity']), 4);
            }

            $stock->save();

            $movement = StockMovement::create([
                'owner_company_id' => $data['owner_company_id'],
                'stock_id' => $stock->id,
                'movement_type' => $movementType,
                'quantity' => $data['quantity'],
                'reference_note' => $data['reference_note'],
                'created_by_user_id' => $actor->id,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($stock)
                ->withProperties([
                    'movement_type' => $movementType->value,
                    'quantity' => (string) $data['quantity'],
                    'reference_note' => $data['reference_note'],
                    'new_quantity_on_hand' => (string) $stock->quantity_on_hand,
                ])
                ->log('تعدیل موجودی');

            return $movement;
        });
    }
}
