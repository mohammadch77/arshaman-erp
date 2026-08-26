<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Gate;

class CreateWarehouse
{
    public function handle(User $actor, array $data): Warehouse
    {
        Gate::forUser($actor)->authorize('create', Warehouse::class);

        return Warehouse::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }
}
