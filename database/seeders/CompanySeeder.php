<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\BusinessType;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Seed the six companies of the holding.
     */
    public function run(): void
    {
        $companies = [
            ['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => BusinessType::ProjectServices],
            ['name' => 'Verifex', 'slug' => 'verifex', 'business_type' => BusinessType::Hybrid],
            ['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => BusinessType::PhysicalGoods],
            ['name' => 'دعانو', 'slug' => 'doano', 'business_type' => BusinessType::PhysicalGoods],
            ['name' => 'Pixentry', 'slug' => 'pixentry', 'business_type' => BusinessType::DigitalProduct],
            ['name' => 'ستاد مشترک', 'slug' => 'shared', 'business_type' => BusinessType::SharedOverhead],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(['slug' => $company['slug']], $company);
        }
    }
}
