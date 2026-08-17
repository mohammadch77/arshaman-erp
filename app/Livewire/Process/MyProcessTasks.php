<?php

namespace App\Livewire\Process;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Actions\ApproveProcessStep;
use App\Modules\Process\Actions\RejectProcessStep;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Support\ProcessSubjectSummary;
use Illuminate\Support\Collection;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * صندوق کارهای من — فهرست process_instance هایی که مرحله‌ی فعلی‌شان به این
 * کاربر واگذار شده (نقش یا کاربر مشخص)، در دسترس هر کاربری با هر نقش. authorize
 * واقعی تأیید/رد داخل خودِ ApproveProcessStep/RejectProcessStep است (بند ۹
 * CLAUDE.md)؛ اینجا فقط برای بازخورد فوری UI هم authorize صدا زده می‌شود.
 */
class MyProcessTasks extends Component
{
    use Toast;

    public ?string $commentInstanceId = null;

    public string $comment = '';

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
            ]);
    }

    public function openComment(string $instanceId): void
    {
        $this->commentInstanceId = $instanceId;
        $this->comment = '';
    }

    public function approve(ApproveProcessStep $action): void
    {
        $this->act($action, 'approve', 'درخواست تأیید شد.');
    }

    public function reject(RejectProcessStep $action): void
    {
        $this->act($action, 'reject', 'درخواست رد شد.');
    }

    private function act(ApproveProcessStep|RejectProcessStep $action, string $ability, string $successMessage): void
    {
        if ($this->commentInstanceId === null) {
            return;
        }

        $instance = ProcessInstance::findOrFail($this->commentInstanceId);

        $this->authorize($ability, $instance);

        $action->handle($instance, auth()->user(), $this->comment !== '' ? $this->comment : null);

        $this->commentInstanceId = null;
        $this->comment = '';

        $this->success($successMessage);
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
