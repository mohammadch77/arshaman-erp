<?php

namespace App\Livewire\HR;

use App\Modules\HR\Enums\ContractType;
use App\Modules\HR\Enums\EmploymentStatus;
use App\Modules\HR\Models\Employee;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $employmentStatus = '';

    public string $contractType = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Employee::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEmploymentStatus(): void
    {
        $this->resetPage();
    }

    public function updatedContractType(): void
    {
        $this->resetPage();
    }

    public function getEmploymentStatusOptionsProperty(): array
    {
        return array_map(fn (EmploymentStatus $case) => ['id' => $case->value, 'name' => $case->label()], EmploymentStatus::cases());
    }

    public function getContractTypeOptionsProperty(): array
    {
        return array_map(fn (ContractType $case) => ['id' => $case->value, 'name' => $case->label()], ContractType::cases());
    }

    public function getEmployeesProperty()
    {
        return Employee::query()
            ->when($this->search, fn ($query) => $query->where(
                fn ($q) => $q->where('full_name', 'like', "%{$this->search}%")
                    ->orWhere('national_id', 'like', "%{$this->search}%")
            ))
            ->when($this->employmentStatus, fn ($query) => $query->where('employment_status', $this->employmentStatus))
            ->when($this->contractType, fn ($query) => $query->where('contract_type', $this->contractType))
            ->orderBy('full_name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.hr.employee-index', [
            'employees' => $this->employees,
        ]);
    }
}
