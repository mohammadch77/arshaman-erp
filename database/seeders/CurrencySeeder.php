<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * ارزهای رایج مورد استفاده هلدینگ — تومان چون ارز پایه است رکورد ندارد.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'دلار آمریکا', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'یورو', 'symbol' => '€'],
            ['code' => 'AED', 'name' => 'درهم امارات', 'symbol' => 'د.إ'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
