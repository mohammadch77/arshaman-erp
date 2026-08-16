<?php

namespace App\Modules\Process\Providers;

use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Policies\ProcessDefinitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ProcessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ProcessDefinition::class, ProcessDefinitionPolicy::class);
    }
}
