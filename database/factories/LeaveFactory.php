<?php

namespace Database\Factories;

use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Models\Leave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    /**
     * employee_id و owner_company_id باید صریح توسط caller ست شوند (همان الگوی AttendanceFactory).
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'leave_type' => LeaveType::Annual,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
            'days_count' => 1,
            'leave_status' => LeaveStatus::Pending,
            'reason' => fake()->sentence(),
        ];
    }
}
