<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سال مالی شمسی (اول فروردین تا آخر اسفند). فقط زیرساخت محدوده و قفل ساختاری —
 * منطق واقعی بستن دوره و انتقال مانده حسابداری در فاز ۶ اضافه می‌شود.
 */
class FiscalPeriod extends Model
{
    use BelongsToCompany, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'owner_company_id',
        'name',
        'start_date',
        'end_date',
        'is_closed',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
