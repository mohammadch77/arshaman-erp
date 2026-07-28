<?php

namespace App\Modules\Core\Actions;

use App\Mail\UserInvitationMail;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteUser
{
    protected const EXPIRY_HOURS = 72;

    /**
     * @param  array{email: string, full_name: string, owner_company_id?: ?string, assigned_role_id?: ?string}  $data
     */
    public function handle(array $data, User $actor): UserInvitation
    {
        Gate::forUser($actor)->authorize('invite', User::class);

        $invitation = DB::transaction(function () use ($data, $actor) {
            $invitation = UserInvitation::create([
                'email' => $data['email'],
                'full_name' => $data['full_name'],
                'token' => $this->generateUniqueToken(),
                'owner_company_id' => $data['owner_company_id'] ?? null,
                'assigned_role_id' => $data['assigned_role_id'] ?? null,
                'invited_by_user_id' => $actor->id,
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
                'created_at' => now(),
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($invitation)
                ->withProperties(['email' => $invitation->email, 'owner_company_id' => $invitation->owner_company_id])
                ->log('ارسال دعوت‌نامه');

            return $invitation;
        });

        $acceptUrl = route('invitations.accept', ['token' => $invitation->token]);

        Mail::to($invitation->email)->send(new UserInvitationMail($invitation, $acceptUrl));

        return $invitation;
    }

    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (UserInvitation::where('token', $token)->exists());

        return $token;
    }
}
