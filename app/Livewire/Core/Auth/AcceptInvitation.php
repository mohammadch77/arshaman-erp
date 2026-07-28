<?php

namespace App\Livewire\Core\Auth;

use App\Modules\Core\Actions\AcceptInvitation as AcceptInvitationAction;
use App\Modules\Core\Exceptions\InvalidInvitationException;
use App\Modules\Core\Models\UserInvitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvitation extends Component
{
    public string $token = '';

    public string $full_name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $invitationIsValid = false;

    public string $invalidMessage = '';

    public function mount(string $token): void
    {
        if (Auth::check()) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        $this->token = $token;

        $invitation = UserInvitation::where('token', $token)->first();

        if (! $invitation) {
            $this->invalidMessage = InvalidInvitationException::notFound()->getMessage();

            return;
        }

        if ($invitation->isAccepted()) {
            $this->invalidMessage = InvalidInvitationException::alreadyAccepted()->getMessage();

            return;
        }

        if ($invitation->isExpired()) {
            $this->invalidMessage = InvalidInvitationException::expired()->getMessage();

            return;
        }

        $this->full_name = $invitation->full_name;
        $this->invitationIsValid = true;
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function accept(AcceptInvitationAction $action): void
    {
        if (! $this->invitationIsValid) {
            return;
        }

        $data = $this->validate();

        try {
            $user = $action->handle($this->token, $data);
        } catch (InvalidInvitationException $e) {
            $this->invitationIsValid = false;
            $this->invalidMessage = $e->getMessage();

            return;
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.core.auth.accept-invitation');
    }
}
