<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignRole
{
    public function handle(User $user, string $companyId, string $roleId, User $actor): UserCompanyRole
    {
        Gate::forUser($actor)->authorize('assignRole', User::class);

        return DB::transaction(function () use ($user, $companyId, $roleId, $actor) {
            $companyRole = UserCompanyRole::updateOrCreate(
                ['user_id' => $user->id, 'owner_company_id' => $companyId],
                ['assigned_role_id' => $roleId, 'created_by_user_id' => $actor->id],
            );

            $roleName = Role::find($roleId)?->display_name;

            activity()
                ->causedBy($actor)
                ->performedOn($user)
                ->withProperties(['owner_company_id' => $companyId, 'assigned_role_id' => $roleId, 'role' => $roleName])
                ->log('تخصیص نقش×شرکت');

            return $companyRole;
        });
    }
}
