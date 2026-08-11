<?php

namespace App\Modules\SiteBuilder\Providers;

use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Policies\PagePolicy;
use App\Modules\SiteBuilder\Policies\SiteSettingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SiteBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(SiteSetting::class, SiteSettingPolicy::class);
    }
}
