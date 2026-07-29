<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تعطیلی رسمی — عمداً بدون BelongsToCompany. owner_company_id=null یعنی
 * تعطیلی سراسری (روی همه شرکت‌ها اثر می‌گذارد)؛ Global Scope آن trait
 * رکوردهای NULL را حذف می‌کرد، پس اینجا فیلتر شرکت دستی در WorkCalendar انجام می‌شود.
 */
class Holiday extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'owner_company_id',
        'title',
        'holiday_date',
        'is_recurring_yearly',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring_yearly' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }
}
