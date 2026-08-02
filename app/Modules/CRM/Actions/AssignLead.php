<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Facades\Gate;

class AssignLead
{
    public function handle(Lead $lead, ?string $assignedToUserId, User $actor): Lead
    {
        Gate::forUser($actor)->authorize('update', $lead);

        $lead->update([
            'assigned_to_user_id' => $assignedToUserId,
            'updated_by_user_id' => $actor->id,
        ]);

        return $lead;
    }
}
