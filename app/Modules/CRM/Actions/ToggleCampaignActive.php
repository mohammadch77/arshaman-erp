<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Campaign;
use Illuminate\Support\Facades\Gate;

class ToggleCampaignActive
{
    public function handle(Campaign $campaign, User $actor): Campaign
    {
        Gate::forUser($actor)->authorize('update', $campaign);

        $campaign->update(['is_active' => ! $campaign->is_active]);

        return $campaign;
    }
}
