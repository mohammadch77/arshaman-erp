<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;

class ToggleWarehouseActive
{
    public function handle(Warehouse $warehouse, User $actor): Warehouse
    {
        Gate::forUser($actor)->authorize('update', Warehouse::class);

        $warehouse->update([
            'is_active' => ! $warehouse->is_active,
            'updated_by_user_id' => $actor->id,
        ]);

        return $warehouse;
    }
}
