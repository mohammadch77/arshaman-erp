<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use BelongsToCompany, HasUuids;

    protected $fillable = [
        'owner_company_id',
        'order_id',
        'carrier',
        'tracking_code',
        'status',
        'shipped_at',
        'delivered_at',
        'shipping_cost_amount',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'shipping_cost_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
