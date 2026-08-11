<?php

namespace App\Modules\SiteBuilder\Actions;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Models\SiteSetting;
use Illuminate\Support\Facades\Gate;

class UpdateSiteSettings
{
    /**
     * @param  array{site_title: ?string, site_tagline: ?string, logo_path: ?string, favicon_path: ?string, homepage_page_id: ?string, blog_page_id: ?string, active_header_demo_id: ?string, active_footer_demo_id: ?string}  $data
     */
    public function handle(SiteSetting $siteSetting, array $data, User $actor): SiteSetting
    {
        Gate::forUser($actor)->authorize('update', $siteSetting);

        $siteSetting->update($data);

        return $siteSetting->refresh();
    }
}
