<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Gate;

class ToggleUserActive
{
    public function handle(User $user, User $actor): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        $user->update([
            'is_active' => ! $user->is_active,
            'updated_by_user_id' => $actor->id,
        ]);

        return $user;
    }
}
