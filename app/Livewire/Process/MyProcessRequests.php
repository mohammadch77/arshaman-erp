<?php

namespace App\Livewire\Process;

use App\Modules\Process\Actions\SubmitRequesterInput;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Support\ProcessSubjectSummary;
use Illuminate\Support\Collection;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * تاریخچه‌ی درخواست‌های خودم — چه وصل‌به‌ماژول (مثلاً مرخصی) چه آزاد. تاریخچه‌ی
 * کامل لاگ (چه کسی، چه زمانی، چه نظری) دقیقاً همان الگوی modal تاریخچه‌ی
 * contact_submissions است.
 */
class MyProcessRequests extends Component
{
    use Toast;

    public ?string $historyInstanceId = null;

    public bool $showHistoryModal = false;

    public ?string $inputInstanceId = null;

    public bool $showInputModal = false;

    /**
     * مقادیر فرم مرحله‌ی requester_input فعلی — کلید = field key.
     *
     * @var array<string, mixed>
     */
    public array $inputStepDataValues = [];

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

    /**
     * درخواست‌های خودِ کاربر که مرحله‌ی فعلی‌شان منتظر تکمیل فرم توسط همین
     * کاربر (فرستنده‌ی اصلی) است — بند ۴ CLAUDE.md: مستقیم مدل ماژول دیگر
     * import نمی‌شود، فقط شرط step_type روی همین مدل Process.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getNeedsInputProperty(): Collection
    {
        return ProcessInstance::query()
            ->where('started_by_user_id', auth()->id())
            ->where('status', ProcessStatus::InProgress->value)
            ->whereHas('currentStep', fn ($query) => $query->where('step_type', StepType::RequesterInput->value))
            ->with(['definition', 'currentStep'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ProcessInstance $instance) => [
                'instance' => $instance,
                'summary' => ProcessSubjectSummary::forInstance($instance),
            ]);
    }

    public function openInputForm(string $instanceId): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('submitRequesterInput', $instance);

        $this->inputInstanceId = $instanceId;
        $this->inputStepDataValues = [];

        foreach ($instance->currentStep?->step_form_fields ?? [] as $field) {
            $this->inputStepDataValues[$field['key']] = $field['type'] === 'boolean' ? false : null;
        }

        $this->showInputModal = true;
    }

    /**
     * فیلدهای فرم مرحله‌ی requester_input همان instance که مودال برایش باز است.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInputStepFormFieldsProperty(): array
    {
        if ($this->inputInstanceId === null) {
            return [];
        }

        return ProcessInstance::find($this->inputInstanceId)?->currentStep?->step_form_fields ?? [];
    }

    public function submitInput(SubmitRequesterInput $action): void
    {
        if ($this->inputInstanceId === null) {
            return;
        }

        $instance = ProcessInstance::findOrFail($this->inputInstanceId);

        $this->authorize('submitRequesterInput', $instance);

        $action->handle($instance, auth()->user(), $this->inputStepDataValues);

        $this->inputInstanceId = null;
        $this->showInputModal = false;
        $this->inputStepDataValues = [];

        $this->success('اطلاعات شما ارسال شد — فرایند به مرحله‌ی بعد رفت.');
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
