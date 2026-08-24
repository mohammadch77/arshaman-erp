<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عمداً بدون owner_company_id/BelongsToCompany و بدون هیچ ستون timestamp/audit —
 * طبق docs/schema_inventory_mysql.sql (جدول ۶)، شرکت از طریق order.owner_company_id
 * مشخص است. سه ستون snapshot اینجا هرگز به products وصل نمی‌مانند (بند ۵.۲ CLAUDE.md).
 */
class OrderLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_sale_price_amount',
        'unit_cost_amount',
        'fulfillment_type',
        'line_total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_sale_price_amount' => 'decimal:2',
            'unit_cost_amount' => 'decimal:2',
            'fulfillment_type' => FulfillmentType::class,
            'line_total_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
