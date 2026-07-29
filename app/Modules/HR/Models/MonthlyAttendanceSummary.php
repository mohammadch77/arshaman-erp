<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use Database\Factories\MonthlyAttendanceSummaryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceSummary extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'owner_company_id',
        'period_month',
        'total_worked_days',
        'total_absent_days',
        'total_late_minutes',
        'total_overtime_minutes',
        'total_leave_days',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MonthlyAttendanceSummaryFactory
    {
        return MonthlyAttendanceSummaryFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
