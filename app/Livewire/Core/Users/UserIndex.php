<?php

namespace App\Livewire\Core\Users;

use App\Modules\Core\Actions\ToggleUserActive;
use App\Modules\Core\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class UserIndex extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(string $userId, ToggleUserActive $action): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $action->handle($user, auth()->user());

        $this->success('وضعیت کاربر به‌روزرسانی شد.');
    }

    public function getUsersProperty()
    {
        return User::query()
            ->with(['companyRoles.role', 'companyRoles.company'])
            ->when($this->search, fn ($query) => $query->where(
                fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->orderBy('full_name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.core.users.user-index', [
            'users' => $this->users,
        ]);
    }
}
