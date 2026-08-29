<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;

class UpdateWarehouse
{
    public function handle(Warehouse $warehouse, User $actor, array $data): Warehouse
    {
        Gate::forUser($actor)->authorize('update', Warehouse::class);

        $warehouse->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? $warehouse->is_active,
            'updated_by_user_id' => $actor->id,
        ]);

        return $warehouse;
    }
}
