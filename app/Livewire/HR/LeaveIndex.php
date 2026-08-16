<?php

namespace App\Livewire\HR;

use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class LeaveIndex extends Component
{
    use Toast, WithPagination;

    /**
     * پیش‌فرض «همه» — نه 'pending'. با پیش‌فرض pending، صفحه در نگاه اول
     * فیلترشده به‌نظر می‌رسید و کاربر فکر می‌کرد مرخصی‌های دیگر وجود ندارند.
     */
    public string $filterStatus = '';

    public string $filterEmployeeId = '';

    public bool $showReasonModal = false;

    public string $reasonModalTitle = '';

    public string $reasonModalBody = '';

    public bool $showRejectModal = false;

    public ?string $rejectingLeaveId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Leave::class);
    }

    public function approve(string $leaveId, ApproveLeave $action): void
    {
        $leave = Leave::findOrFail($leaveId);

        try {
            $action->handle($leave, auth()->user());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->success('مرخصی تأیید شد.');
    }

    public function openReject(string $leaveId): void
    {
        $this->authorize('review', Leave::class);

        $this->rejectingLeaveId = $leaveId;
        $this->rejectionReason = '';
        $this->resetErrorBag();
        $this->showRejectModal = true;
    }

    public function reject(RejectLeave $action): void
    {
        $leave = Leave::findOrFail($this->rejectingLeaveId);

        try {
            $action->handle($leave, auth()->user(), $this->rejectionReason);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return;
        }

        $this->showRejectModal = false;
        $this->rejectingLeaveId = null;
        $this->rejectionReason = '';
        $this->success('مرخصی رد شد.');
    }

    public function showReason(string $leaveId): void
    {
        $leave = Leave::findOrFail($leaveId);

        $this->reasonModalTitle = 'دلیل درخواست — '.$leave->employee->full_name;
        $this->reasonModalBody = (string) $leave->reason;
        $this->showReasonModal = true;
    }

    public function showRejectionReason(string $leaveId): void
    {
        $leave = Leave::findOrFail($leaveId);

        $this->reasonModalTitle = 'دلیل رد — '.$leave->employee->full_name;
        $this->reasonModalBody = (string) $leave->rejection_reason;
        $this->showReasonModal = true;
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
            ['id' => '', 'name' => 'همه وضعیت‌ها'],
            ['id' => 'pending', 'name' => 'در انتظار'],
            ['id' => 'approved', 'name' => 'تأییدشده'],
            ['id' => 'rejected', 'name' => 'ردشده'],
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
        $leaves = $this->leaves;

        // فقط برای درخواست‌های در انتظار معنا دارد؛ چون approve/reject خودش
        // فقط روی pending اثر می‌کند، محدودکردن کوئری به همان‌ها کافی است.
        $pendingIds = $leaves->getCollection()
            ->where('leave_status', LeaveStatus::Pending)
            ->pluck('id')
            ->all();

        $activeProcessLeaveIds = app(ProcessEngine::class)->activeInstanceSubjectIds(Leave::class, $pendingIds);

        return view('livewire.hr.leave-index', [
            'leaves' => $leaves,
            'activeProcessLeaveIds' => $activeProcessLeaveIds,
        ]);
    }
}
