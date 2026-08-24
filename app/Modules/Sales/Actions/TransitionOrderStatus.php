<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Catalog\Enums\FulfillmentType;
use App\Modules\Core\Models\User;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderLine;
use App\Modules\Sales\Services\OrderStateMachine;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * تنها مسیر مجاز تغییر order_status. رسیدن به preparing خودکار موجودی اقلام
 * فیزیکی را (از طریق IssueStock) کم می‌کند — طبق docs/PROJECT_04_INVENTORY.md.
 */
class TransitionOrderStatus
{
    public function handle(Order $order, OrderStatus $to, User $actor, ?string $note = null): Order
    {
        Gate::forUser($actor)->authorize('transition', [$order, $to]);

        $stateMachine = app(OrderStateMachine::class);

        if (! $stateMachine->isValidTransition($order, $to)) {
            throw new InvalidArgumentException('این تغییر وضعیت برای سفارش مجاز نیست.');
        }

        $from = $order->order_status;

        return DB::transaction(function () use ($order, $to, $from, $actor, $note, $stateMachine) {
            if ($to === OrderStatus::Preparing && $stateMachine->hasPhysicalLine($order)) {
                $this->issueStockForPhysicalLines($order, $actor, $note);
            }

            $order->update(['order_status' => $to]);

            activity()
                ->causedBy($actor)
                ->performedOn($order)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->log('تغییر وضعیت سفارش');

            return $order->refresh();
        });
    }

    private function issueStockForPhysicalLines(Order $order, User $actor, ?string $note): void
    {
        $issueStock = app(IssueStock::class);
        $referenceNote = $note ?? sprintf('خروج خودکار سفارش شماره %d', $order->order_number);

        /** @var OrderLine $line */
        foreach ($order->lines as $line) {
            if ($line->fulfillment_type !== FulfillmentType::Physical) {
                continue;
            }

            $this->allocateStockForLine($order, $line, $actor, $issueStock, $referenceNote);
        }
    }

    /**
     * موجودی یک قلم را از بین انبارهای همان محصول/شرکت تخصیص می‌دهد. اگر یک
     * انبار به‌تنهایی کافی باشد، فقط همان یک خروج ثبت می‌شود؛ وگرنه از چند
     * انبار (بیشترین موجودی اول) پشت‌سرهم خروج زده می‌شود تا کل مقدار قلم
     * تأمین شود. اگر مجموع همه‌ی انبارها هم کافی نبود، throw می‌کند —
     * DB::transaction بیرونی کل ترنزیشن (شامل خروج‌های قبلی همین لوپ) را
     * rollback می‌کند (all-or-nothing، تأیید صریح کارفرما).
     */
    private function allocateStockForLine(Order $order, OrderLine $line, User $actor, IssueStock $issueStock, string $referenceNote): void
    {
        // withoutGlobalScopes() طبق بند ۱۳ CLAUDE.md: این فراخوانی به شرکت هدف
        // صریح order.owner_company_id مقید است، نه شرکت فعال سوییچر session.
        $stocks = Stock::withoutGlobalScopes()
            ->where('owner_company_id', $order->owner_company_id)
            ->where('product_id', $line->product_id)
            ->orderByDesc('quantity_on_hand')
            ->get();

        $remaining = (string) $line->quantity;

        foreach ($stocks as $stock) {
            if (Money::isGreaterThan($remaining, '0') === false) {
                break;
            }

            $available = (string) $stock->quantity_on_hand;

            if (Money::isGreaterThan($available, '0') === false) {
                continue;
            }

            $quantityToIssue = Money::isGreaterThan($available, $remaining) ? $remaining : $available;

            $issueStock->handle([
                'owner_company_id' => $order->owner_company_id,
                'product_id' => $line->product_id,
                'warehouse_id' => $stock->warehouse_id,
                'quantity' => $quantityToIssue,
                'movement_type' => MovementType::SaleOut->value,
                'reference_note' => $referenceNote,
            ], $actor);

            $remaining = Money::subtract($remaining, $quantityToIssue);
        }

        if (Money::isGreaterThan($remaining, '0')) {
            throw new InvalidArgumentException('موجودی کافی برای یک یا چند قلم این سفارش وجود ندارد.');
        }
    }
}
