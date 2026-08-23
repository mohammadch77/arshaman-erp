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

class ReceiveStock
{
    private const ALLOWED_TYPES = [MovementType::PurchaseIn, MovementType::ReturnIn];

    /**
     * @param  array{owner_company_id: string, product_id: string, warehouse_id: string, quantity: float|string, movement_type?: string, unit_cost?: float|string|null, reference_note?: ?string, occurred_at?: ?string}  $data
     */
    public function handle(array $data, User $actor): StockMovement
    {
        Gate::forUser($actor)->authorize('manage', [Stock::class, $data['owner_company_id']]);

        $movementType = MovementType::from($data['movement_type'] ?? MovementType::PurchaseIn->value);

        if (! in_array($movementType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('نوع حرکت وارد‌شده برای دریافت موجودی مجاز نیست.');
        }

        return DB::transaction(function () use ($data, $actor, $movementType) {
            $stock = Stock::withoutGlobalScopes()->lockForUpdate()->firstOrCreate([
                'owner_company_id' => $data['owner_company_id'],
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
            ], [
                'quantity_on_hand' => 0,
            ]);

            $unitCost = $data['unit_cost'] ?? null;
            $newAverageCost = $stock->average_cost;

            if ($unitCost !== null && $unitCost !== '') {
                $oldQuantity = (string) $stock->quantity_on_hand;
                $oldAverageCost = $stock->average_cost !== null ? (string) $stock->average_cost : '0';
                $receivedQuantity = (string) $data['quantity'];

                $oldValue = Money::multiply($oldQuantity, $oldAverageCost);
                $receivedValue = Money::multiply($receivedQuantity, (string) $unitCost);
                $newQuantity = Money::add($oldQuantity, $receivedQuantity);

                $newAverageCost = Money::round(Money::divide(Money::add($oldValue, $receivedValue), $newQuantity));
            }

            $stock->quantity_on_hand = Money::round((string) Money::add((string) $stock->quantity_on_hand, (string) $data['quantity']), 4);
            $stock->average_cost = $newAverageCost;
            $stock->save();

            $movement = StockMovement::create([
                'owner_company_id' => $data['owner_company_id'],
                'stock_id' => $stock->id,
                'movement_type' => $movementType,
                'quantity' => $data['quantity'],
                'unit_cost' => $unitCost !== '' ? $unitCost : null,
                'reference_note' => $data['reference_note'] ?? null,
                'created_by_user_id' => $actor->id,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($stock)
                ->withProperties([
                    'movement_type' => $movementType->value,
                    'quantity' => (string) $data['quantity'],
                    'unit_cost' => $unitCost,
                    'new_quantity_on_hand' => (string) $stock->quantity_on_hand,
                    'new_average_cost' => $stock->average_cost,
                ])
                ->log('دریافت موجودی');

            return $movement;
        });
    }
}
