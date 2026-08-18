<?php

namespace App\Livewire\Process;

use App\Modules\Process\Actions\CancelProcessInstance;
use App\Modules\Process\Actions\SubmitRequesterInput;
use App\Modules\Process\Actions\UpdateProcessInstanceRequest;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Support\ProcessFileUploader;
use App\Modules\Process\Support\ProcessSubjectSummary;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * تاریخچه‌ی درخواست‌های خودم — چه وصل‌به‌ماژول (مثلاً مرخصی) چه آزاد. تاریخچه‌ی
 * کامل لاگ (چه کسی، چه زمانی، چه نظری) دقیقاً همان الگوی modal تاریخچه‌ی
 * contact_submissions است.
 */
class MyProcessRequests extends Component
{
    use Toast, WithFileUploads;

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
     * فقط فیلدهای نوع file از step_form_fields مرحله‌ی requester_input —
     * همان الگوی NewProcessRequest::fileUploads.
     *
     * @var array<string, mixed>
     */
    public array $inputFileUploads = [];

    public ?string $editInstanceId = null;

    public bool $showEditModal = false;

    /**
     * مقادیر ویرایش‌شده‌ی request_data (فقط فرایند آزاد) — کلید = field key.
     *
     * @var array<string, mixed>
     */
    public array $editFormValues = [];

    /**
     * @var array<string, mixed>
     */
    public array $editFileUploads = [];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRequestsProperty(): Collection
    {
        $user = auth()->user();

        return ProcessInstance::query()
            ->where('started_by_user_id', $user->id)
            ->with(['definition', 'currentStep'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ProcessInstance $instance) => [
                'instance' => $instance,
                'summary' => ProcessSubjectSummary::forInstance($instance),
                // بخش ۳ Session جاری: فقط قبل از اقدام روی مرحله‌ی فعلی —
                // تنها منبع حقیقت همان دو متد Policy است، نه یک شرط تکراری اینجا.
                'can_edit' => $user->can('updateRequest', $instance),
                'can_cancel' => $user->can('cancel', $instance),
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
        $this->inputFileUploads = [];

        foreach ($instance->currentStep?->step_form_fields ?? [] as $field) {
            $this->inputStepDataValues[$field['key']] = $field['type'] === 'boolean' ? false : null;
            if ($field['type'] === 'file') {
                $this->inputFileUploads[$field['key']] = null;
            }
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

        foreach ($this->inputFileUploads as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $this->inputStepDataValues[$key] = ProcessFileUploader::store($file);
            }
        }

        $action->handle($instance, auth()->user(), $this->inputStepDataValues);

        $this->inputInstanceId = null;
        $this->showInputModal = false;
        $this->inputStepDataValues = [];
        $this->inputFileUploads = [];

        $this->success('اطلاعات شما ارسال شد — فرایند به مرحله‌ی بعد رفت.');
    }

    public function openEditForm(string $instanceId): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('updateRequest', $instance);

        $this->editInstanceId = $instanceId;
        $this->editFormValues = [];
        $this->editFileUploads = [];

        foreach ($instance->definition?->request_form_fields ?? [] as $field) {
            $this->editFormValues[$field['key']] = $instance->request_data[$field['key']] ?? ($field['type'] === 'boolean' ? false : null);
            if ($field['type'] === 'file') {
                $this->editFileUploads[$field['key']] = null;
            }
        }

        $this->showEditModal = true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEditFormFieldsProperty(): array
    {
        if ($this->editInstanceId === null) {
            return [];
        }

        return ProcessInstance::find($this->editInstanceId)?->definition?->request_form_fields ?? [];
    }

    /**
     * مسیر فایل از‌قبل‌آپلودشده‌ی هر فیلد نوع file — برای نمایش «فایل فعلی»
     * در فرم ویرایش وقتی کاربر فایل تازه انتخاب نکرده (آپلود مجدد اختیاری است).
     *
     * @return array<string, string>
     */
    public function getEditExistingFilesProperty(): array
    {
        if ($this->editInstanceId === null) {
            return [];
        }

        return ProcessInstance::find($this->editInstanceId)?->request_data ?? [];
    }

    public function saveEditRequest(UpdateProcessInstanceRequest $action): void
    {
        if ($this->editInstanceId === null) {
            return;
        }

        $instance = ProcessInstance::findOrFail($this->editInstanceId);

        $this->authorize('updateRequest', $instance);

        // فیلد نوع file اگر کاربر فایل تازه انتخاب نکرده باشد، مقدار (مسیر)
        // قبلی‌اش را حفظ می‌کند — آپلود مجدد اختیاری است، نه اجباری.
        foreach ($this->editFileUploads as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $this->editFormValues[$key] = ProcessFileUploader::store($file);
            } elseif (($this->editFormValues[$key] ?? null) === null) {
                $this->editFormValues[$key] = $instance->request_data[$key] ?? null;
            }
        }

        $action->handle(auth()->user(), $instance, $this->editFormValues);

        $this->editInstanceId = null;
        $this->showEditModal = false;
        $this->editFormValues = [];
        $this->editFileUploads = [];

        $this->success('درخواست شما ویرایش شد.');
    }

    public function cancelInstance(string $instanceId, CancelProcessInstance $action): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('cancel', $instance);

        $action->handle(auth()->user(), $instance);

        $this->success('درخواست شما لغو شد.');
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
