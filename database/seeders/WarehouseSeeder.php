<?php

namespace Database\Seeders;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * انبار فیزیکاً مشترک هلدینگ (بند ۵.۸ CLAUDE.md) — بدون owner_company_id.
     */
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['name' => 'انبار مرکزی هلدینگ'],
            ['address' => null, 'is_active' => true]
        );
    }
}
