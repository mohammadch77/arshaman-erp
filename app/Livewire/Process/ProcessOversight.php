<?php

namespace App\Livewire\Process;

use App\Modules\Process\Actions\RecordProcessReminder;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Services\ProcessEngine;
use App\Support\Farsi;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * صفحه‌ی نظارت holding_admin (بخش ۱ Session جاری) — فهرست همه‌ی
 * process_instance های در جریان/تمام‌شده‌ی کل شرکت فعال (نه فقط کارهای خودش)،
 * با مرحله‌ی فعلی، مدت‌زمان سپری‌شده در همان مرحله، و دسترسی به تاریخچه‌ی کامل
 * + قابلیت یادآوری. authorize واقعی (ProcessInstancePolicy::oversight/remind)
 * طبق بند ۹ CLAUDE.md، مستقل از این‌که کامپوننت قبلش چک کرده یا نه.
 */
class ProcessOversight extends Component
{
    use Toast, WithPagination;

    public ?string $reminderInstanceId = null;

    public string $reminderComment = '';

    public ?string $historyInstanceId = null;

    public bool $showHistoryModal = false;

    public function mount(): void
    {
        $this->authorize('oversight', ProcessInstance::class);
    }

    public function openReminder(string $instanceId): void
    {
        $this->reminderInstanceId = $instanceId;
        $this->reminderComment = '';
    }

    public function sendReminder(RecordProcessReminder $action): void
    {
        if ($this->reminderInstanceId === null || trim($this->reminderComment) === '') {
            $this->error('متن یادآوری نمی‌تواند خالی باشد.');

            return;
        }

        $instance = ProcessInstance::findOrFail($this->reminderInstanceId);

        $this->authorize('remind', $instance);

        $action->handle($instance, auth()->user(), trim($this->reminderComment));

        $this->reminderInstanceId = null;
        $this->reminderComment = '';

        $this->success('یادآوری ثبت شد — در «کارهای من» مسئول مرحله‌ی فعلی نمایش داده می‌شود.');
    }

    public function openHistory(string $instanceId): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('view', $instance);

        $this->historyInstanceId = $instanceId;
        $this->showHistoryModal = true;
    }

    /**
     * @return Collection<int, ProcessInstanceLog>
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

    /**
     * مدت‌زمان سپری‌شده در مرحله‌ی فعلی — از آخرین رویداد ثبت‌شده‌ی این
     * instance (نزدیک‌ترین تقریب موجود به «چه زمانی وارد این مرحله شد»، بدون
     * نیاز به ستون جدید)، یا از started_at اگر هنوز هیچ لاگی نداشته.
     */
    public function durationInCurrentStep(ProcessInstance $instance): string
    {
        $since = app(ProcessEngine::class)->lastLog($instance)?->created_at ?? $instance->started_at;

        return Farsi::duration((int) $since->diffInMinutes(now()));
    }

    public function render()
    {
        $instances = ProcessInstance::query()
            ->whereIn('status', [ProcessStatus::InProgress->value, ProcessStatus::Approved->value, ProcessStatus::Rejected->value, ProcessStatus::Cancelled->value])
            ->with(['definition', 'currentStep', 'startedBy'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('livewire.process.process-oversight', [
            'instances' => $instances,
        ]);
    }
}
