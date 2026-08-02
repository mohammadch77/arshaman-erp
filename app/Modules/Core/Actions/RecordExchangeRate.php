<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\ExchangeRate;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordExchangeRate
{
    /**
     * @param  array{currency_id: string, rate_to_toman: string, effective_date: string}  $data
     *
     * ثبت دوباره برای همان (ارز، تاریخ) عمداً به‌جای رد‌شدن، نرخ را جایگزین می‌کند —
     * تنها راه اصلاح یک نرخ اشتباه‌واردشده در همان روز، بدون رکورد یتیم اضافه.
     */
    public function handle(array $data, User $actor): ExchangeRate
    {
        Gate::forUser($actor)->authorize('create', ExchangeRate::class);

        return DB::transaction(fn () => ExchangeRate::updateOrCreate(
            [
                'currency_id' => $data['currency_id'],
                'effective_date' => $data['effective_date'],
            ],
            [
                'rate_to_toman' => $data['rate_to_toman'],
                'created_by_user_id' => $actor->id,
            ]
        ));
    }
}
