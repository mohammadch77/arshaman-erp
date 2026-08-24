<?php

namespace App\Modules\Shipping\Providers;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Policies\ShipmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Shipment::class, ShipmentPolicy::class);
    }
}
