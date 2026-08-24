<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک رکورد رویداد است (مثل StockMovement)، نه یک موجودیت قابل‌ویرایش —
 * بدون updated_at/soft delete. هر جابجایی موفق دقیقاً دو StockMovement
 * (transfer_out روی مبدأ، transfer_in روی مقصد) با همین id می‌سازد.
 */
class StockTransfer extends Model
{
    use BelongsToCompany, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'owner_company_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'quantity',
        'note',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transfer) {
            $transfer->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
