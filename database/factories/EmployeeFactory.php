<?php

namespace Database\Factories;

use App\Modules\HR\Enums\ContractType;
use App\Modules\HR\Enums\EmployeePosition;
use App\Modules\HR\Enums\EmploymentStatus;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * owner_company_id باید صریح توسط caller ست شود (مثلاً
     * Employee::factory()->create(['owner_company_id' => $company->id]))
     * چون مدل Company فکتوری ندارد و BelongsToCompany بدون session فعال
     * چیزی برای پرکردن خودکار آن ندارد.
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'national_id' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('09#########'),
            'address' => fake()->address(),
            'position' => fake()->randomElement(EmployeePosition::cases())->value,
            'hire_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'employment_status' => EmploymentStatus::Active,
            'contract_type' => ContractType::Permanent,
            'contract_start_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'base_salary' => fake()->numberBetween(150, 800) * 100000,
        ];
    }
}
