<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LinkEmployeeToUser
{
    public function handle(Employee $employee, User $user, User $actor): Employee
    {
        Gate::forUser($actor)->authorize('link', $employee);

        Validator::make(['user_id' => $user->id], [
            'user_id' => ['required', 'uuid', Rule::unique('employees', 'user_id')],
        ])->validate();

        return DB::transaction(function () use ($employee, $user, $actor) {
            $employee->update([
                'user_id' => $user->id,
                'updated_by_user_id' => $actor->id,
            ]);

            return $employee;
        });
    }
}
