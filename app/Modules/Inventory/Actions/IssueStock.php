<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class IssueStock
{
    /**
     * @param  array{owner_company_id: string, product_id: string, warehouse_id: string, quantity: int, reason: ?string}  $data
     */
    public function handle(array $data, User $actor): StockMovement
    {
        Gate::forUser($actor)->authorize('manage', [Stock::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor) {
            $stock = Stock::withoutGlobalScopes()->lockForUpdate()
                ->where('owner_company_id', $data['owner_company_id'])
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            if ($stock === null || (float) $stock->quantity_on_hand < $data['quantity']) {
                throw new InvalidArgumentException('موجودی کافی برای خروج وجود ندارد.');
            }

            $stock->decrement('quantity_on_hand', $data['quantity']);

            return StockMovement::create([
                'owner_company_id' => $data['owner_company_id'],
                'stock_id' => $stock->id,
                'movement_type' => MovementType::Out,
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }
}
