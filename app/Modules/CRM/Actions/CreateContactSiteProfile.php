<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateContactSiteProfile
{
    /**
     * @param  array{full_name: string, phone: string, email: ?string, site_full_name: ?string, owner_company_id: string}  $data
     */
    public function handle(array $data, User $actor, ContactMatcher $matcher): ContactSiteProfile
    {
        Gate::forUser($actor)->authorize('create', [ContactSiteProfile::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor, $matcher) {
            $contact = $matcher->findOrCreateContact($data['full_name'], $data['phone'], $data['email'], $actor);

            return ContactSiteProfile::create([
                'owner_company_id' => $data['owner_company_id'],
                'contact_id' => $contact->id,
                'site_full_name' => $data['site_full_name'],
                'first_seen_at' => now(),
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
