<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\EmploymentStatus;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TerminateEmployee
{
    public function handle(Employee $employee, string $terminationDate, User $actor): Employee
    {
        Gate::forUser($actor)->authorize('terminate', $employee);

        return DB::transaction(function () use ($employee, $terminationDate, $actor) {
            $employee->fill([
                'employment_status' => EmploymentStatus::Terminated,
                'termination_date' => $terminationDate,
                'updated_by_user_id' => $actor->id,
            ]);
            $employee->save();

            return $employee;
        });
    }
}
