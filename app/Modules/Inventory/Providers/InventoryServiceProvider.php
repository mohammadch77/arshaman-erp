<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Policies\StockPolicy;
use App\Modules\Inventory\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);
    }
}
