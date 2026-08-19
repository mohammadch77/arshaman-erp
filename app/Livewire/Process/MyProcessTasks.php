<?php

namespace App\Livewire\Process;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Actions\ReverseProcessDecision;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Support\ProcessFileUploader;
use App\Modules\Process\Support\ProcessSubjectSummary;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * صندوق کارهای من — فهرست process_instance هایی که مرحله‌ی فعلی‌شان به این
 * کاربر واگذار شده (نقش یا کاربر مشخص)، در دسترس هر کاربری با هر نقش. authorize
 * واقعی تأیید/رد داخل خودِ ApproveProcessStep/RejectProcessStep است (بند ۹
 * CLAUDE.md)؛ اینجا فقط برای بازخورد فوری UI هم authorize صدا زده می‌شود.
 */
class MyProcessTasks extends Component
{
    use Toast, WithFileUploads;

    public ?string $commentInstanceId = null;

    public string $comment = '';

    /**
     * مقادیر فرم اضافه‌ی خودِ مرحله (step_form_fields، بخش ۳ Session جاری) —
     * فقط وقتی مرحله‌ی فعلی چنین فرمی داشته باشد پر می‌شود؛ کلید = field key.
     *
     * @var array<string, mixed>
     */
    public array $stepDataValues = [];

    /**
     * فقط فیلدهای نوع file از همان step_form_fields — کلید = field key، مقدار
     * = TemporaryUploadedFile تا انتخاب شود؛ در act() به مسیر ذخیره‌شده تبدیل
     * می‌شود (همان الگوی NewProcessRequest::fileUploads).
     *
     * @var array<string, mixed>
     */
    public array $fileUploads = [];

    public ?string $historyInstanceId = null;

    public bool $showHistoryModal = false;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getTasksProperty(): Collection
    {
        $user = auth()->user();
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null) {
            return collect();
        }

        $roleNames = $this->userRoleNamesInCompany($companyId);

        return ProcessInstance::query()
            ->where('status', ProcessStatus::InProgress->value)
            ->whereHas('currentStep', function ($query) use ($user, $roleNames) {
                $query->where('step_type', StepType::Approval->value)
                    ->where(function ($q) use ($user, $roleNames) {
                        $q->where(function ($q2) use ($user) {
                            $q2->where('assignment_type', AssignmentType::SpecificUser->value)
                                ->where('assigned_user_id', $user->id);
                        });

                        if ($roleNames !== []) {
                            $q->orWhere(function ($q2) use ($roleNames) {
                                $q2->where('assignment_type', AssignmentType::Role->value)
                                    ->whereIn('assigned_role', $roleNames);
                            });
                        }
                    });
            })
            ->with(['definition', 'currentStep', 'startedBy'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ProcessInstance $instance) => [
                'instance' => $instance,
                'summary' => ProcessSubjectSummary::forInstance($instance),
                'reminder' => $this->latestReminder($instance),
            ]);
    }

    /**
     * تصمیم‌های اخیر خودِ این کاربر که هنوز قابل بازگردانی‌اند — یعنی مرحله‌ی
     * بعدی از قبل هیچ اقدامی نداشته. تنها منبع حقیقتِ این شرط
     * ProcessEngine::canReverseLastDecision است (بند ۴ Session جاری).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getReversibleDecisionsProperty(): Collection
    {
        $user = auth()->user();
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null) {
            return collect();
        }

        $engine = app(ProcessEngine::class);

        $candidateInstanceIds = ProcessInstanceLog::query()
            ->where('owner_company_id', $companyId)
            ->where('actor_user_id', $user->id)
            ->whereIn('action', [LogAction::Approved->value, LogAction::Rejected->value])
            ->whereNull('reversed_at')
            ->pluck('process_instance_id')
            ->unique();

        if ($candidateInstanceIds->isEmpty()) {
            return collect();
        }

        return ProcessInstance::query()
            ->whereIn('id', $candidateInstanceIds)
            ->where('status', ProcessStatus::InProgress->value)
            ->with(['definition', 'currentStep'])
            ->get()
            ->filter(fn (ProcessInstance $instance) => $engine->canReverseLastDecision($instance, $user))
            ->map(fn (ProcessInstance $instance) => [
                'instance' => $instance,
                'summary' => ProcessSubjectSummary::forInstance($instance),
            ])
            ->values();
    }

    public function openComment(string $instanceId): void
    {
        $this->commentInstanceId = $instanceId;
        $this->comment = '';
        $this->stepDataValues = [];
        $this->fileUploads = [];

        foreach ($this->commentStepFormFields as $field) {
            $this->stepDataValues[$field->field_key] = $field->field_type === 'boolean' ? false : null;
            if ($field->field_type === 'file') {
                $this->fileUploads[$field->field_key] = null;
            }
        }
    }

    /**
     * فیلدهای فرم اضافه‌ی مرحله‌ی فعلی همان instance که مودال تأیید/رد برایش
     * باز است — خالی اگر این مرحله فرم اضافه‌ای تعریف نکرده باشد.
     *
     * @return Collection<int, ProcessFormField>
     */
    public function getCommentStepFormFieldsProperty()
    {
        if ($this->commentInstanceId === null) {
            return collect();
        }

        return ProcessInstance::find($this->commentInstanceId)?->currentStep?->formFields ?? collect();
    }

    public function approve(ApproveProcessStep $action): void
    {
        $this->act($action, 'approve', 'درخواست تأیید شد.');
    }

    public function reject(RejectProcessStep $action): void
    {
        $this->act($action, 'reject', 'درخواست رد شد.');
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
            ->with(['step', 'actor', 'fieldValues.formField'])
            ->orderBy('created_at')
            ->get();
    }

    public function reverseDecision(string $instanceId, ReverseProcessDecision $action): void
    {
        $instance = ProcessInstance::findOrFail($instanceId);

        $this->authorize('reverseLastDecision', $instance);

        $action->handle($instance, auth()->user());

        $this->success('تصمیم شما بازگردانی شد — دوباره در فهرست کارهای منتظر تصمیم قرار گرفت.');
    }

    private function act(ApproveProcessStep|RejectProcessStep $action, string $ability, string $successMessage): void
    {
        if ($this->commentInstanceId === null) {
            return;
        }

        $instance = ProcessInstance::findOrFail($this->commentInstanceId);

        $this->authorize($ability, $instance);

        foreach ($this->fileUploads as $key => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $this->stepDataValues[$key] = ProcessFileUploader::store($file);
            }
        }

        $action->handle($instance, auth()->user(), $this->comment !== '' ? $this->comment : null, $this->stepDataValues);

        $this->commentInstanceId = null;
        $this->comment = '';
        $this->stepDataValues = [];
        $this->fileUploads = [];

        $this->success($successMessage);
    }

    private function latestReminder(ProcessInstance $instance): ?ProcessInstanceLog
    {
        $lastLog = app(ProcessEngine::class)->lastLog($instance);

        return $lastLog?->action === LogAction::Reminder ? $lastLog : null;
    }

    /**
     * @return array<int, string>
     */
    private function userRoleNamesInCompany(string $companyId): array
    {
        $user = auth()->user();

        if ($user->is_super_admin) {
            return Role::query()->pluck('name')->all();
        }

        return $user->companyRoles()
            ->where('owner_company_id', $companyId)
            ->with('role')
            ->get()
            ->pluck('role.name')
            ->filter()
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.process.my-process-tasks');
    }
}
