<?php

namespace App\Modules\Sales\Services;

use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;

/**
 * نقشه‌ی ترنزیشن‌های مجاز برای دو چرخه‌ی وضعیت سفارش (فیزیکی/دیجیتال)،
 * طبق docs/PROJECT_04_INVENTORY.md بخش «دو چرخه وضعیت سفارش» و بند ۵.۳/۶
 * CLAUDE.md. سفارش ترکیبی (حداقل یک قلم فیزیکی) همیشه چرخه‌ی فیزیکی می‌گیرد.
 */
class OrderStateMachine
{
    /** @var array<string, array<int, string>> */
    private const PHYSICAL_TRANSITIONS = [
        'received' => ['paid', 'cancelled'],
        'paid' => ['preparing', 'cancelled'],
        'preparing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['closed', 'returned'],
        'closed' => [],
        'cancelled' => [],
        'returned' => [],
    ];

    /** @var array<string, array<int, string>> */
    private const DIGITAL_TRANSITIONS = [
        'received' => ['paid', 'cancelled'],
        'paid' => ['delivered_instant', 'cancelled'],
        'delivered_instant' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public function hasPhysicalLine(Order $order): bool
    {
        return $order->lines->contains(
            fn ($line) => $line->fulfillment_type === FulfillmentType::Physical
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function cycleFor(Order $order): array
    {
        return $this->hasPhysicalLine($order) ? self::PHYSICAL_TRANSITIONS : self::DIGITAL_TRANSITIONS;
    }

    /**
     * @return array<int, OrderStatus>
     */
    public function allowedTransitions(Order $order): array
    {
        $cycle = $this->cycleFor($order);
        $currentKey = $order->order_status->value;

        if (! array_key_exists($currentKey, $cycle)) {
            return [];
        }

        return array_map(fn (string $status) => OrderStatus::from($status), $cycle[$currentKey]);
    }

    public function isValidTransition(Order $order, OrderStatus $to): bool
    {
        foreach ($this->allowedTransitions($order) as $allowed) {
            if ($allowed === $to) {
                return true;
            }
        }

        return false;
    }
}
