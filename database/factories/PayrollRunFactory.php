<?php

namespace Database\Factories;

use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    /**
     * owner_company_id باید صریح توسط caller ست شود (همان الگوی
     * MonthlyAttendanceSummaryFactory) چون به یک Company واقعی وابسته است.
     */
    public function definition(): array
    {
        return [
            'period_month' => '1405-01',
            'payroll_status' => PayrollStatus::Draft,
        ];
    }
}
