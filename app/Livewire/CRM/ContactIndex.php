<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Models\ContactSiteProfile;
use Livewire\Component;
use Livewire\WithPagination;

class ContactIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ContactSiteProfile::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getProfilesProperty()
    {
        return ContactSiteProfile::query()
            ->with('contact')
            ->whereHas('contact', fn ($query) => $query->when(
                $this->search,
                fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->latest('first_seen_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.crm.contact-index', [
            'profiles' => $this->profiles,
        ]);
    }
}
