<?php

namespace Database\Factories;

use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * employee_id و owner_company_id باید صریح توسط caller ست شوند
     * (همان الگوی EmployeeFactory) چون به یک Employee/Company واقعی وابسته‌اند.
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'attendance_date' => $date->format('Y-m-d'),
            'check_in_at' => $date->format('Y-m-d').' 08:00:00',
            'check_out_at' => $date->format('Y-m-d').' 16:00:00',
            'recorded_by' => RecordedBy::Admin,
        ];
    }
}
