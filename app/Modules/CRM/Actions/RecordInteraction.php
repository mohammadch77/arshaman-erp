<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Interaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class RecordInteraction
{
    /**
     * ثبت دستی یک تعامل (call/telegram/site_form). 'purchase' از این مسیر
     * ثبت نمی‌شود — آن نوع فقط از Interaction::createFromOrder() (فاز ۳) می‌آید.
     *
     * @param  array{interaction_type: string, notes: ?string, occurred_at: Carbon}  $data
     */
    public function handle(ContactSiteProfile $profile, array $data, User $actor): Interaction
    {
        Gate::forUser($actor)->authorize('create', [Interaction::class, $profile]);

        if (! in_array($data['interaction_type'], Interaction::MANUAL_TYPES, true)) {
            throw new \InvalidArgumentException('نوع تعامل برای ثبت دستی نامعتبر است.');
        }

        return Interaction::create([
            'owner_company_id' => $profile->owner_company_id,
            'contact_site_profile_id' => $profile->id,
            'interaction_type' => $data['interaction_type'],
            'notes' => $data['notes'],
            'occurred_at' => $data['occurred_at'],
            'created_by_user_id' => $actor->id,
        ]);
    }
}
