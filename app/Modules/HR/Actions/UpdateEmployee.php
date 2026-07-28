<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateEmployee
{
    /**
     * @param  array{full_name?: string, national_id?: string, phone?: ?string, address?: ?string,
     *     position?: string, hire_date?: string, contract_type?: string, contract_start_date?: string,
     *     contract_end_date?: ?string, base_salary?: numeric-string|float}  $data
     */
    public function handle(Employee $employee, array $data, User $actor): Employee
    {
        Gate::forUser($actor)->authorize('update', $employee);

        if (array_key_exists('national_id', $data)) {
            Validator::make($data, [
                'national_id' => [
                    'required',
                    'string',
                    Rule::unique('employees', 'national_id')
                        ->where('owner_company_id', $employee->owner_company_id)
                        ->ignore($employee->id),
                ],
            ])->validate();
        }

        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee->fill([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);
            $employee->save();

            return $employee;
        });
    }
}
