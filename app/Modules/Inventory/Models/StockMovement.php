<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToCompany, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'owner_company_id',
        'stock_id',
        'stock_transfer_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reference_note',
        'created_by_user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            $movement->created_at ??= now();
            $movement->occurred_at ??= now();
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * فقط برای دو رکورد transfer_out/transfer_in پر است — بقیه انواع حرکت
     * همیشه null دارند (نگاه کن migration add_stock_transfer_id...).
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
}
