<?php

namespace App\Modules\Sales\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderSource;
use App\Modules\Sales\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    /**
     * ستون‌های مالی که بعد از تحویل/بسته‌شدن سفارش دیگر قابل تغییر نیستند —
     * طبق بند ۶ CLAUDE.md («بعد از delivered فیلدهای مالی قفل‌اند»).
     */
    private const LOCKED_FINANCIAL_FIELDS = [
        'subtotal_amount',
        'shipping_amount',
        'total_amount',
        'exchange_rate_snapshot',
        'currency_id',
    ];

    private const LOCKING_STATUSES = [
        OrderStatus::Delivered,
        OrderStatus::DeliveredInstant,
        OrderStatus::Closed,
    ];

    protected $fillable = [
        'owner_company_id',
        'order_number',
        'party_id',
        'order_status',
        'source',
        'external_order_id',
        'exchange_rate_snapshot',
        'currency_id',
        'subtotal_amount',
        'shipping_amount',
        'total_amount',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'order_status' => OrderStatus::class,
            'source' => OrderSource::class,
            'exchange_rate_snapshot' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * قفل مالی در سطح مدل — دقیقاً الگوی Payslip::booted() در HR (بند ۹
     * CLAUDE.md: Action تنها caller نیست). وضعیت *قبل* از این ویرایش
     * (getRawOriginal) ملاک است، نه مقدار جدید — چون خودِ
     * TransitionOrderStatus باید بتواند order_status را از delivered به
     * closed/returned ببرد؛ آن ستون در فهرست قفل‌شده نیست، پس دست‌نخورده
     * می‌ماند.
     */
    protected static function booted(): void
    {
        static::updating(function (self $order) {
            $originalStatus = $order->getRawOriginal('order_status');

            if ($originalStatus === null) {
                return;
            }

            $isLocked = collect(self::LOCKING_STATUSES)
                ->contains(fn (OrderStatus $status) => $status->value === $originalStatus);

            if (! $isLocked) {
                return;
            }

            foreach (self::LOCKED_FINANCIAL_FIELDS as $field) {
                if ($order->isDirty($field)) {
                    throw ValidationException::withMessages([
                        $field => 'این سفارش تحویل/بسته شده است و فیلدهای مالی آن قابل ویرایش نیستند.',
                    ]);
                }
            }
        });
    }
}
