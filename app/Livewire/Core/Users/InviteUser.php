<?php

namespace App\Livewire\Core\Users;

use App\Modules\Core\Actions\InviteUser as InviteUserAction;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;

class InviteUser extends Component
{
    use Toast;

    public string $full_name = '';

    public string $email = '';

    public string $companyId = '';

    public string $roleId = '';

    public function mount(): void
    {
        $this->authorize('invite', User::class);
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'companyId' => ['nullable', 'uuid', 'exists:companies,id'],
            'roleId' => ['nullable', 'uuid', 'exists:roles,id'],
        ];
    }

    public function invite(InviteUserAction $action): void
    {
        $this->authorize('invite', User::class);

        $data = $this->validate();

        $action->handle([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'owner_company_id' => $data['companyId'] ?: null,
            'assigned_role_id' => $data['roleId'] ?: null,
        ], auth()->user());

        $this->success('دعوت‌نامه ارسال شد.', redirectTo: route('users.index'));
    }

    public function getCompaniesProperty()
    {
        return Company::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function getRolesProperty()
    {
        return Role::query()->orderBy('display_name')->get();
    }

    public function render()
    {
        return view('livewire.core.users.invite-user');
    }
}
