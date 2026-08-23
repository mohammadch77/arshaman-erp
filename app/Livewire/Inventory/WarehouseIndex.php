<?php

namespace App\Livewire\Inventory;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use Livewire\Component;

class WarehouseIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Warehouse::class);
    }

    public function getWarehousesProperty()
    {
        return Warehouse::query()->orderBy('name')->get();
    }

    /**
     * Warehouse بین‌شرکتی است (بند ۵.۸ CLAUDE.md)، پس فقط holding_admin
     * موجودی همه شرکت‌ها را در یک انبار کنار هم می‌بیند (الگوی
     * ContactProfile/RfmSegmentIndex) — بقیه نقش‌ها فقط موجودی شرکت
     * فعال خودشان را می‌بینند، از طریق همان global scope عادی Stock.
     */
    public function getStocksByWarehouseProperty()
    {
        $user = auth()->user();
        $canSeeAllCompanies = $user->is_super_admin || $user->hasRole('holding_admin');

        // وقتی موجودی بدون global scope خوانده می‌شود (holding_admin)، رابطه product
        // هم باید صریح withoutGlobalScopes بگیرد — وگرنه global scope خودِ Product
        // (BelongsToCompany، مقید به شرکت فعال سوییچر) محصولات شرکت‌های دیگر را null
        // برمی‌گرداند، حتی وقتی خودِ ردیف Stock درست خوانده شده.
        $query = Stock::query()->with([
            'product' => fn ($productQuery) => $canSeeAllCompanies ? $productQuery->withoutGlobalScopes() : $productQuery,
            'warehouse',
            'ownerCompany',
        ]);

        if ($canSeeAllCompanies) {
            $query->withoutGlobalScopes();
        }

        return $query->get()->groupBy('warehouse_id');
    }

    public function render()
    {
        return view('livewire.inventory.warehouse-index', [
            'warehouses' => $this->warehouses,
            'stocksByWarehouse' => $this->stocksByWarehouse,
        ]);
    }
}
