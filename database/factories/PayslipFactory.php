<?php

namespace Database\Factories;

use App\Modules\HR\Enums\ExpensePostingStatus;
use App\Modules\HR\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * payroll_run_id, employee_id و owner_company_id باید صریح توسط caller ست شوند.
     */
    public function definition(): array
    {
        return [
            'gross_salary_amount' => '0',
            'overtime_amount' => '0',
            'absence_deduction_amount' => '0',
            'unpaid_leave_deduction_amount' => '0',
            'insurance_amount' => '0',
            'tax_amount' => '0',
            'benefits_amount' => '0',
            'net_amount' => '0',
            'expense_posting_status' => ExpensePostingStatus::Pending,
        ];
    }
}
