<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Stock extends Model
{
    use BelongsToCompany, HasUuids;

    protected $fillable = [
        'owner_company_id',
        'product_id',
        'warehouse_id',
        'quantity_on_hand',
        'reorder_point',
        'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'average_cost' => 'decimal:2',
        ];
    }

    /**
     * دفاع دولایه در برابر موجودی منفی — لایه اول اینجا (مدل)، لایه دوم
     * CHECK دیتابیس (migration 2026_08_23_100002). IssueStock هم از قبل
     * سطح Action چک می‌کند؛ این‌جا یک سد مستقل است اگر جایی مستقیم روی
     * مدل save شود.
     */
    protected static function booted(): void
    {
        static::saving(function (self $stock) {
            if ($stock->quantity_on_hand !== null && (float) $stock->quantity_on_hand < 0) {
                throw new InvalidArgumentException('موجودی یک کالا هرگز نمی‌تواند منفی شود.');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * اگر این ردیف موجودی خودش یک نقطه سفارش اختصاصی دارد (این انبار
     * خاص باید زودتر/دیرتر از بقیه شارژ شود)، همان اولویت دارد؛ وگرنه
     * نقطه سفارش پیش‌فرض محصول (سطح شرکت، مستقل از انبار) استفاده می‌شود.
     */
    public function reorderThreshold(): ?string
    {
        return $this->reorder_point ?? $this->product->reorder_point;
    }

    public function isBelowReorderPoint(): bool
    {
        $threshold = $this->reorderThreshold();

        return $threshold !== null && (float) $this->quantity_on_hand < (float) $threshold;
    }
}
