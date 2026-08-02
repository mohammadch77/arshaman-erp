<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Facades\Gate;

class CreateLead
{
    /**
     * @param  array{owner_company_id: string, contact_site_profile_id: ?string, source: string, estimated_value: ?string, notes: ?string}  $data
     */
    public function handle(array $data, User $actor): Lead
    {
        Gate::forUser($actor)->authorize('create', [Lead::class, $data['owner_company_id']]);

        if (! in_array($data['source'], Lead::SOURCES, true)) {
            throw new \InvalidArgumentException('منبع لید نامعتبر است.');
        }

        return Lead::create([
            'owner_company_id' => $data['owner_company_id'],
            'contact_site_profile_id' => $data['contact_site_profile_id'],
            'source' => $data['source'],
            'pipeline_stage' => Lead::STAGE_NEW,
            'estimated_value' => $data['estimated_value'],
            'notes' => $data['notes'],
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }
}
