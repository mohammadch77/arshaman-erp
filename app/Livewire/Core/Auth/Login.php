<?php

namespace App\Livewire\Core\Auth;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ];
    }

    public function authenticate(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'ایمیل یا رمز عبور نادرست است.');

            return;
        }

        session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.core.auth.login');
    }
}
