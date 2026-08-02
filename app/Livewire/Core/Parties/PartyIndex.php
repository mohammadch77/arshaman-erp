<?php

namespace App\Livewire\Core\Parties;

use App\Modules\Core\Models\Party;
use Livewire\Component;
use Livewire\WithPagination;

class PartyIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Party::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function getTypeFilterOptionsProperty(): array
    {
        return [
            ['id' => 'customer', 'name' => 'مشتری'],
            ['id' => 'supplier', 'name' => 'تأمین‌کننده'],
            ['id' => 'both', 'name' => 'هردو'],
        ];
    }

    public function getPartiesProperty()
    {
        return Party::query()
            ->when($this->search, fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
            ))
            ->when($this->typeFilter === 'customer', fn ($query) => $query->where('is_customer', true))
            ->when($this->typeFilter === 'supplier', fn ($query) => $query->where('is_supplier', true))
            ->when($this->typeFilter === 'both', fn ($query) => $query->where('is_customer', true)->where('is_supplier', true))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.core.parties.party-index', [
            'parties' => $this->parties,
        ]);
    }
}
