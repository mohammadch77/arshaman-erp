<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdatePartyRecord
{
    /**
     * @param  array{name: string, party_type: string, is_customer: bool, is_supplier: bool, phone: ?string, email: ?string, economic_code: ?string, address: ?string}  $data
     */
    public function handle(Party $party, array $data, User $actor): Party
    {
        Gate::forUser($actor)->authorize('update', $party);

        DB::transaction(function () use ($party, $data, $actor) {
            $party->update([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $party->refresh();
    }
}
