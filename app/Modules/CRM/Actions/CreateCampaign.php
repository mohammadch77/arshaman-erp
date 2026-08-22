<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Campaign;
use Illuminate\Support\Facades\Gate;

class CreateCampaign
{
    /**
     * @param  array{owner_company_id: string, name: string, trigger_type: string, channel: string, message_template: string, is_active: bool}  $data
     */
    public function handle(array $data, User $actor): Campaign
    {
        Gate::forUser($actor)->authorize('create', [Campaign::class, $data['owner_company_id']]);

        return Campaign::create([
            'owner_company_id' => $data['owner_company_id'],
            'name' => $data['name'],
            'trigger_type' => $data['trigger_type'],
            'channel' => $data['channel'],
            'message_template' => $data['message_template'],
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $actor->id,
        ]);
    }
}
