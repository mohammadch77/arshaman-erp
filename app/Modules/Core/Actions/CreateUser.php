<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class CreateUser
{
    /**
     * @param  array{full_name: string, email: string, password: string}  $data
     */
    public function handle(array $data, User $actor): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($data, $actor) {
            return User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'is_super_admin' => false,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
