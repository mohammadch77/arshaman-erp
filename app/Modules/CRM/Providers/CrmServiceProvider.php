<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Policies\ContactPolicy;
use App\Modules\CRM\Policies\ContactSiteProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ContactSiteProfile::class, ContactSiteProfilePolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
    }
}
