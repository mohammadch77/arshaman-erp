<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نرخ روزانه یک ارز به تومان — بدون owner_company_id، چون currencies هم
 * مشترک بین همه شرکت‌های هلدینگ است (نه مالکیتی طبق ExchangeRateResolver).
 */
class ExchangeRate extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'currency_id',
        'rate_to_toman',
        'effective_date',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rate_to_toman' => 'decimal:2',
            'effective_date' => 'date:Y-m-d',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
