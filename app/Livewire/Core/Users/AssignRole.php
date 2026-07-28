<?php

namespace App\Livewire\Core\Users;

use App\Modules\Core\Actions\AssignRole as AssignRoleAction;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Livewire\Component;
use Mary\Traits\Toast;

class AssignRole extends Component
{
    use Toast;

    public string $userId = '';

    public string $companyId = '';

    public string $roleId = '';

    public function mount(): void
    {
        $this->authorize('assignRole', User::class);
    }

    protected function rules(): array
    {
        return [
            'userId' => ['required', 'uuid', 'exists:users,id'],
            'companyId' => ['required', 'uuid', 'exists:companies,id'],
            'roleId' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }

    public function assign(AssignRoleAction $action): void
    {
        $this->authorize('assignRole', User::class);

        $this->validate();

        $user = User::findOrFail($this->userId);

        $action->handle($user, $this->companyId, $this->roleId, auth()->user());

        $this->success('نقش×شرکت تخصیص داده شد.');

        $this->reset('roleId');
    }

    public function getUsersProperty()
    {
        return User::query()->orderBy('full_name')->get();
    }

    public function getCompaniesProperty()
    {
        return Company::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function getRolesProperty()
    {
        return Role::query()->orderBy('display_name')->get();
    }

    public function getCurrentRolesProperty()
    {
        if (! $this->userId) {
            return collect();
        }

        return UserCompanyRole::query()
            ->where('user_id', $this->userId)
            ->with(['company', 'role'])
            ->get();
    }

    public function render()
    {
        return view('livewire.core.users.assign-role');
    }
}
