<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\EmploymentStatus;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateEmployee
{
    /**
     * @param  array{owner_company_id: string, full_name: string, national_id: string, phone?: ?string,
     *     address?: ?string, position: string, hire_date: string, contract_type: string,
     *     contract_start_date: string, contract_end_date?: ?string, base_salary: numeric-string|float}  $data
     */
    public function handle(array $data, User $actor): Employee
    {
        Gate::forUser($actor)->authorize('create', [Employee::class, $data['owner_company_id']]);

        Validator::make($data, [
            'national_id' => [
                'required',
                'string',
                Rule::unique('employees', 'national_id')
                    ->where('owner_company_id', $data['owner_company_id']),
            ],
        ])->validate();

        return DB::transaction(function () use ($data, $actor) {
            return Employee::create([
                ...$data,
                'employment_status' => EmploymentStatus::Active,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
