<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreatePartyRecord
{
    /**
     * @param  array{name: string, party_type: string, is_customer: bool, is_supplier: bool, phone: ?string, email: ?string, economic_code: ?string, address: ?string, owner_company_id: string}  $data
     */
    public function handle(array $data, User $actor): Party
    {
        Gate::forUser($actor)->authorize('create', [Party::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor) {
            return Party::create([
                ...$data,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
