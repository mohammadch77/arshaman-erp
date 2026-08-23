<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Models\Stock;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class StockMovementLedger extends Component
{
    use WithPagination;

    public Stock $stock;

    /**
     * پارامتر route عمداً stockId نام‌گذاری شده، نه stock — همنام‌بودن با
     * property تایپ‌شده $stock باعث می‌شود Livewire پیش از اجرای mount()
     * مقدار خام رشته‌ای route را مستقیم روی همان property بنشاند و با خطای
     * type mismatch شکست بخورد (همان الگوی مستندشده ContactProfile در CRM).
     *
     * پیدا‌کردن با withoutGlobalScopes صریح — holding_admin باید بتواند دفترچه
     * حرکت موجودی هر شرکتی را ببیند، نه فقط شرکت فعال سوییچر (الگوی WarehouseIndex).
     */
    public function mount(string $stockId): void
    {
        // بند ۹.۱۳ CLAUDE.md: چون Stock بدون global scope خوانده می‌شود (holding_admin
        // ممکن است دفترچه یک شرکت غیرفعال را ببیند)، رابطه product هم باید صریح
        // withoutGlobalScopes بگیرد — وگرنه BelongsToCompany روی Product به شرکت فعال
        // سوییچر مقید می‌ماند و برای شرکت دیگر بی‌صدا null برمی‌گرداند.
        $this->stock = Stock::withoutGlobalScopes()
            ->with([
                'product' => fn ($query) => $query->withoutGlobalScopes(),
                'warehouse',
            ])
            ->findOrFail($stockId);

        Gate::authorize('view', $this->stock);
    }

    /**
     * همان دلیل withoutGlobalScopes روی رابطه product در mount(): StockMovement هم
     * BelongsToCompany دارد و بدون این، برای holding_admin روی یک شرکت غیرفعال
     * بی‌صدا خالی برمی‌گردد (بند ۹.۱۳ CLAUDE.md).
     */
    public function getMovementsProperty()
    {
        return $this->stock->movements()
            ->withoutGlobalScopes()
            ->with('createdBy')
            ->orderByDesc('occurred_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.inventory.stock-movement-ledger', [
            'movements' => $this->movements,
        ]);
    }
}
