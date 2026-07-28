<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Exceptions\InvalidInvitationException;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcceptInvitation
{
    /**
     * @param  array{full_name: string, password: string}  $data
     */
    public function handle(string $token, array $data): User
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (! $invitation) {
            throw InvalidInvitationException::notFound();
        }

        if ($invitation->isAccepted()) {
            throw InvalidInvitationException::alreadyAccepted();
        }

        if ($invitation->isExpired()) {
            throw InvalidInvitationException::expired();
        }

        return DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'is_super_admin' => false,
            ]);

            $user->forceFill([
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ])->save();

            if ($invitation->owner_company_id && $invitation->assigned_role_id) {
                UserCompanyRole::create([
                    'user_id' => $user->id,
                    'owner_company_id' => $invitation->owner_company_id,
                    'assigned_role_id' => $invitation->assigned_role_id,
                    'created_by_user_id' => $invitation->invited_by_user_id,
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            activity()
                ->causedBy($invitation->invitedBy)
                ->performedOn($user)
                ->withProperties(['via' => 'invitation', 'invitation_id' => $invitation->id])
                ->log('پذیرش دعوت‌نامه');

            return $user;
        });
    }
}
