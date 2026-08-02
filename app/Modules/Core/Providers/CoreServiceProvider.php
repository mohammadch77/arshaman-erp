<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\PartyPolicy;
use App\Modules\Core\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->is_super_admin ? true : null);

        Gate::define('access-company', fn (User $user, string $companyId) => $user->hasRoleInCompany($companyId));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Party::class, PartyPolicy::class);
    }
}
