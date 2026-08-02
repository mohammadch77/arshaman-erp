<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ارز جهانی هلدینگ — بدون owner_company_id: طبق طراحی سند (جدول ۲)، تومان ارز
 * پایه سیستم است و ارزهای این جدول مشترک بین همه شرکت‌ها هستند، نه مالکیتی.
 */
class Currency extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }
}
