<?php

namespace App\Livewire\Core\Users;

use App\Modules\Core\Actions\CreateUser;
use App\Modules\Core\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;

class UserCreate extends Component
{
    use Toast;

    public string $full_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function save(CreateUser $action): void
    {
        $this->authorize('create', User::class);

        $data = $this->validate();

        $action->handle($data, auth()->user());

        $this->success('کاربر جدید ساخته شد.', redirectTo: route('users.index'));
    }

    public function render()
    {
        return view('livewire.core.users.user-create');
    }
}
