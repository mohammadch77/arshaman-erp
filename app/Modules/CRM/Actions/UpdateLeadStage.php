<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Facades\Gate;

class UpdateLeadStage
{
    /**
     * ترنزیشن قیف طبق Lead::TRANSITIONS. مرحله تعریف‌نشده رد می‌شود
     * (بند ۶ CLAUDE.md).
     */
    public function handle(Lead $lead, string $newStage, User $actor): Lead
    {
        Gate::forUser($actor)->authorize('update', $lead);

        if (! Lead::canTransition($lead->pipeline_stage, $newStage)) {
            throw new \InvalidArgumentException('تغییر مرحله از '.$lead->pipeline_stage.' به '.$newStage.' مجاز نیست.');
        }

        $lead->update([
            'pipeline_stage' => $newStage,
            'updated_by_user_id' => $actor->id,
        ]);

        return $lead;
    }
}
