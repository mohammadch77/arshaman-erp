<?php

namespace App\Livewire\Process;

use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Support\ProcessSubjectSummary;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * تاریخچه‌ی درخواست‌های خودم — چه وصل‌به‌ماژول (مثلاً مرخصی) چه آزاد. تاریخچه‌ی
 * کامل لاگ (چه کسی، چه زمانی، چه نظری) دقیقاً همان الگوی modal تاریخچه‌ی
 * contact_submissions است.
 */
class MyProcessRequests extends Component
{
    public ?string $historyInstanceId = null;

    public bool $showHistoryModal = false;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRequestsProperty(): Collection
    {
        return ProcessInstance::query()
            ->where('started_by_user_id', auth()->id())
            ->with(['definition', 'currentStep'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ProcessInstance $instance) => [
                'instance' => $instance,
                'summary' => ProcessSubjectSummary::forInstance($instance),
            ]);
    }

    public function openHistory(string $instanceId): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('view', $instance);

        $this->historyInstanceId = $instanceId;
        $this->showHistoryModal = true;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Modules\Process\Models\ProcessInstanceLog>
     */
    public function getHistoryProperty(): Collection
    {
        if ($this->historyInstanceId === null) {
            return collect();
        }

        return ProcessInstance::findOrFail($this->historyInstanceId)
            ->logs()
            ->with(['step', 'actor'])
            ->orderBy('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.process.my-process-requests');
    }
}
