<?php

namespace Database\Factories;

use App\Modules\HR\Models\MonthlyAttendanceSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyAttendanceSummary>
 */
class MonthlyAttendanceSummaryFactory extends Factory
{
    protected $model = MonthlyAttendanceSummary::class;

    /**
     * employee_id و owner_company_id باید صریح توسط caller ست شوند
     * (همان الگوی AttendanceFactory) چون به یک Employee/Company واقعی وابسته‌اند.
     */
    public function definition(): array
    {
        return [
            'period_month' => '1405-01',
            'total_worked_days' => 0,
            'total_absent_days' => 0,
            'total_late_minutes' => 0,
            'total_overtime_minutes' => 0,
            'total_leave_days' => 0,
            'calculated_at' => now(),
        ];
    }
}
