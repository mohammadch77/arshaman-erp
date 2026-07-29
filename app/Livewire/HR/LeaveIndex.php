<?php

namespace App\Livewire\HR;

use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class LeaveIndex extends Component
{
    use Toast, WithPagination;

    public string $filterStatus = 'pending';

    public string $filterEmployeeId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Leave::class);
    }

    public function approve(string $leaveId, ApproveLeave $action): void
    {
        $leave = Leave::findOrFail($leaveId);

        $action->handle($leave, auth()->user());

        $this->success('مرخصی تأیید شد.');
    }

    public function reject(string $leaveId, RejectLeave $action): void
    {
        $leave = Leave::findOrFail($leaveId);

        $action->handle($leave, auth()->user());

        $this->success('مرخصی رد شد.');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEmployeeId(): void
    {
        $this->resetPage();
    }

    public function getStatusOptionsProperty(): array
    {
        return [
            ['id' => 'pending', 'name' => 'در انتظار'],
            ['id' => 'approved', 'name' => 'تأییدشده'],
            ['id' => 'rejected', 'name' => 'ردشده'],
            ['id' => '', 'name' => 'همه'],
        ];
    }

    public function getEmployeeOptionsProperty()
    {
        return Employee::orderBy('full_name')->get(['id', 'full_name']);
    }

    public function getLeavesProperty()
    {
        return Leave::query()
            ->with('employee')
            ->when($this->filterStatus !== '', fn ($query) => $query->where('leave_status', $this->filterStatus))
            ->when($this->filterEmployeeId, fn ($query) => $query->where('employee_id', $this->filterEmployeeId))
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.hr.leave-index', [
            'leaves' => $this->leaves,
        ]);
    }
}
