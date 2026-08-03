<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProduct
{
    /**
     * @param  array{name: string, category_id: ?string, sale_price: string, cost_price: ?string, currency_id: ?string, fulfillment_type: string, woocommerce_product_id: ?string, is_active: bool, owner_company_id: string}  $data
     */
    public function handle(array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('create', [Product::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor) {
            return Product::create([
                ...$data,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
