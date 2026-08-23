<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Catalog\Enums\UnitOfMeasure;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_company_id',
        'category_id',
        'name',
        'sku',
        'sale_price',
        'cost_price',
        'currency_id',
        'fulfillment_type',
        'unit_of_measure',
        'woocommerce_product_id',
        'is_active',
        'reorder_point',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'fulfillment_type' => FulfillmentType::class,
            'unit_of_measure' => UnitOfMeasure::class,
            'is_active' => 'boolean',
            'reorder_point' => 'integer',
        ];
    }

    /**
     * بند ۵.۳ CLAUDE.md: بهای تمام‌شده نامشخص هرگز نباید در UI صفر فرض شود —
     * این متد هر جایی که محصول نمایش داده می‌شود، مبنای هشدار صریح است.
     */
    public function needsCostReview(): bool
    {
        return $this->cost_price === null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
