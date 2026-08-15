<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CompanySeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(HolidaySeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(FiscalPeriodSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(SiteBuilderSeeder::class);
        $this->call(SiteBuilderWidgetsExpansionSeeder::class);
        $this->call(SiteBuilderIntegratedWidgetsSeeder::class);
        $this->call(SiteBuilderDemosExpansionSeeder::class);
    }
}
