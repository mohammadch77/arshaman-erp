<?php

use App\Modules\Core\Models\Currency;
use App\Support\Farsi;

it('formats an amount with a foreign currency using its symbol, not toman', function () {
    $usd = Currency::create(['code' => 'USD', 'name' => 'دلار آمریکا', 'symbol' => '$', 'is_active' => true]);

    expect(Farsi::toMoney('120', $usd))->toBe('۱۲۰ $');
});

it('falls back to the currency code when the currency has no symbol', function () {
    $currency = Currency::create(['code' => 'AED', 'name' => 'درهم امارات', 'symbol' => null, 'is_active' => true]);

    expect(Farsi::toMoney('50', $currency))->toBe('۵۰ AED');
});
