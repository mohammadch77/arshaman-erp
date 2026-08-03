<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateProduct
{
    /**
     * @param  array{name: string, category_id: ?string, sale_price: string, cost_price: ?string, currency_id: ?string, fulfillment_type: string, woocommerce_product_id: ?string, is_active: bool}  $data
     */
    public function handle(Product $product, array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('update', $product);

        DB::transaction(function () use ($product, $data, $actor) {
            $product->update([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $product->refresh();
    }
}
