<?php

namespace App\Livewire\Process;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Actions\UpdateProcessDefinition;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * فرم ساختاریافته‌ی طراحی فرایند — عمداً نه یک canvas گرافیکی آزاد (مثل
 * GrapesJS در SiteBuilder که تجربه‌ی خوبی نداشت)، بلکه فهرست مراحل + برای هر
 * مرحله انتخاب گذارهای خروجی از یک select. تنها holding_admin دسترسی دارد
 * (ProcessDefinitionPolicy::create/update).
 */
class ProcessDefinitionForm extends Component
{
    use Toast;

    public ?ProcessDefinition $record = null;

    public string $name = '';

    public string $processKey = '';

    public bool $processKeyManuallyEdited = false;

    /** '' یعنی فرایند آزاد (بدون subject_type). */
    public string $subjectType = '';

    public bool $isActive = true;

    /**
     * فقط برای فرایند آزاد — همان الگوی مفهومی editable_fields ماژول
     * SiteBuilder (کلید + برچسب + نوع)، ساده‌شده چون اینجا فقط یک فرم
     * درخواست تخت لازم است، نه ساختار تودرتوی ویجت.
     *
     * @var array<int, array{key: string, label: string, type: string}>
     */
    public array $requestFormFields = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $steps = [];

    /**
     * انتخاب گذار خروجی هر مرحله، کلید = step_key: start→['next'],
     * approval→['approved','rejected'], condition→['true','false'].
     *
     * @var array<string, array<string, string>>
     */
    public array $transitionSelections = [];

    /** @var array<int, string> */
    public array $graphErrors = [];

    public function mount(?string $processDefinition = null): void
    {
        if ($processDefinition !== null) {
            $this->record = ProcessDefinition::with('steps.outgoingTransitions.toStep')->findOrFail($processDefinition);
            $this->authorize('update', $this->record);

            $this->name = $this->record->name;
            $this->processKey = $this->record->process_key;
            $this->processKeyManuallyEdited = true;
            $this->subjectType = (string) ($this->record->subject_type ?? '');
            $this->isActive = $this->record->is_active;
            $this->requestFormFields = $this->record->request_form_fields ?? [];

            foreach ($this->record->steps as $step) {
                $this->steps[] = [
                    'step_key' => $step->step_key,
                    'name' => $step->name,
                    'step_type' => $step->step_type->value,
                    'assignment_type' => $step->assignment_type?->value ?? '',
                    'assigned_role' => $step->assigned_role ?? '',
                    'assigned_user_id' => $step->assigned_user_id ?? '',
                    'condition_field' => $step->condition_field ?? '',
                    'condition_operator' => $step->condition_operator?->value ?? '',
                    'condition_value' => $step->condition_value ?? '',
                ];

                foreach ($step->outgoingTransitions as $transition) {
                    $toKey = $transition->toStep->step_key;

                    match ($step->step_type) {
                        StepType::Start => $this->transitionSelections[$step->step_key]['next'] = $toKey,
                        StepType::Approval => $this->transitionSelections[$step->step_key][
                            $transition->on_result === TransitionResult::Approved ? 'approved' : 'rejected'
                        ] = $toKey,
                        StepType::Condition => $this->transitionSelections[$step->step_key][
                            $transition->on_result === TransitionResult::ConditionTrue ? 'true' : 'false'
                        ] = $toKey,
                        default => null,
                    };
                }
            }

            return;
        }

        $this->authorize('create', ProcessDefinition::class);

        // یک مرحله‌ی شروع پیش‌فرض — هر فرایند دقیقاً یک مرحله‌ی شروع لازم دارد،
        // شروع خالی سردرگم‌کننده بود.
        $this->steps[] = $this->emptyStep(StepType::Start->value, 'شروع');
    }

    public function updatedName(): void
    {
        if (! $this->processKeyManuallyEdited) {
            $this->processKey = $this->generateKey($this->name);
        }
    }

    public function updatedProcessKey(): void
    {
        $this->processKeyManuallyEdited = true;
    }

    public function updatedSubjectType(): void
    {
        // مرحله‌ی شرط فقط برای فرایند وصل‌شده به ماژول مجاز است — با تعویض به
        // فرایند آزاد، هر مرحله‌ی شرط موجود دیگر معنا ندارد و پاک می‌شود.
        if ($this->subjectType === '') {
            foreach ($this->steps as $index => $step) {
                if ($step['step_type'] === StepType::Condition->value) {
                    $this->steps[$index]['step_type'] = StepType::Approval->value;
                    $this->steps[$index]['condition_field'] = '';
                    $this->steps[$index]['condition_operator'] = '';
                    $this->steps[$index]['condition_value'] = '';
                }
            }
        }
    }

    public function addStep(): void
    {
        $this->steps[] = $this->emptyStep(StepType::Approval->value, '');
    }

    public function removeStep(int $index): void
    {
        $removedKey = $this->steps[$index]['step_key'] ?? null;

        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);

        if ($removedKey !== null) {
            unset($this->transitionSelections[$removedKey]);
        }
    }

    public function generateStepKey(int $index): void
    {
        $this->steps[$index]['step_key'] = $this->generateKey($this->steps[$index]['name'] ?? '').'-'.substr((string) Str::uuid(), 0, 4);
    }

    public function addRequestField(): void
    {
        $this->requestFormFields[] = ['key' => '', 'label' => '', 'type' => 'text'];
    }

    public function removeRequestField(int $index): void
    {
        unset($this->requestFormFields[$index]);
        $this->requestFormFields = array_values($this->requestFormFields);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getSubjectTypeOptionsProperty(): array
    {
        $labels = config('processes.subject_type_labels', []);

        return array_map(fn ($class) => [
            'value' => $class,
            'label' => $labels[$class] ?? class_basename($class),
        ], config('processes.subject_types', []));
    }

    /**
     * @return array<int, string>
     */
    public function getConditionFieldOptionsProperty(): array
    {
        if ($this->subjectType === '') {
            return [];
        }

        return config("processes.condition_fields.{$this->subjectType}", []);
    }

    /**
     * @return array<int, User>
     */
    public function getCompanyUsersProperty()
    {
        $companyId = $this->record?->owner_company_id ?? app(CompanyContext::class)->id();

        return User::query()
            ->whereHas('companyRoles', fn ($query) => $query->where('owner_company_id', $companyId))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectableStepsProperty(): array
    {
        return array_map(fn ($step) => ['value' => $step['step_key'], 'label' => $step['name'] ?: $step['step_key']], $this->steps);
    }

    public function getHasHistoryProperty(): bool
    {
        return $this->record !== null && $this->record->instances()->withoutGlobalScope('owner_company')->exists();
    }

    protected function rules(): array
    {
        $companyId = $this->record?->owner_company_id ?? app(CompanyContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:100'],
            'processKey' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('process_definitions', 'process_key')
                    ->where('owner_company_id', $companyId)
                    ->ignore($this->record?->id),
            ],
            'subjectType' => ['nullable', Rule::in(array_merge([''], config('processes.subject_types', [])))],
        ];
    }

    public function save(CreateProcessDefinition $createAction, UpdateProcessDefinition $updateAction): void
    {
        $this->graphErrors = [];

        $this->validate();

        $payload = $this->extractPayload();

        try {
            if ($this->record === null) {
                $createAction->handle(auth()->user(), app(CompanyContext::class)->id(), $payload);
            } else {
                $updateAction->handle(auth()->user(), $this->record, $payload);
            }
        } catch (ValidationException $e) {
            $this->graphErrors = array_merge(...array_values($e->errors()));
            $this->error('ساختار فرایند نامعتبر است — پایین صفحه را ببینید.');

            return;
        }

        $this->success('فرایند ذخیره شد.', redirectTo: route('processes.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(): array
    {
        $steps = [];

        foreach ($this->steps as $step) {
            $isApproval = $step['step_type'] === StepType::Approval->value;
            $isCondition = $step['step_type'] === StepType::Condition->value;
            $isRoleAssignment = $isApproval && $step['assignment_type'] === AssignmentType::Role->value;
            $isUserAssignment = $isApproval && $step['assignment_type'] === AssignmentType::SpecificUser->value;

            $steps[] = [
                'step_key' => $step['step_key'],
                'name' => $step['name'],
                'step_type' => $step['step_type'],
                'assignment_type' => $isApproval && $step['assignment_type'] !== '' ? $step['assignment_type'] : null,
                'assigned_role' => $isRoleAssignment && $step['assigned_role'] !== '' ? $step['assigned_role'] : null,
                'assigned_user_id' => $isUserAssignment && $step['assigned_user_id'] !== '' ? $step['assigned_user_id'] : null,
                'condition_field' => $isCondition && $step['condition_field'] !== '' ? $step['condition_field'] : null,
                'condition_operator' => $isCondition && $step['condition_operator'] !== '' ? $step['condition_operator'] : null,
                'condition_value' => $isCondition && $step['condition_value'] !== '' ? $step['condition_value'] : null,
            ];
        }

        $transitions = [];

        foreach ($this->steps as $step) {
            $key = $step['step_key'];
            $selection = $this->transitionSelections[$key] ?? [];

            if ($step['step_type'] === StepType::Start->value && ! empty($selection['next'])) {
                $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['next'], 'on_result' => TransitionResult::Approved->value];
            }

            if ($step['step_type'] === StepType::Approval->value) {
                if (! empty($selection['approved'])) {
                    $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['approved'], 'on_result' => TransitionResult::Approved->value];
                }
                if (! empty($selection['rejected'])) {
                    $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['rejected'], 'on_result' => TransitionResult::Rejected->value];
                }
            }

            if ($step['step_type'] === StepType::Condition->value) {
                if (! empty($selection['true'])) {
                    $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['true'], 'on_result' => TransitionResult::ConditionTrue->value];
                }
                if (! empty($selection['false'])) {
                    $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['false'], 'on_result' => TransitionResult::ConditionFalse->value];
                }
            }
        }

        $requestFormFields = array_values(array_filter(
            $this->requestFormFields,
            fn ($field) => ($field['key'] ?? '') !== '' && ($field['label'] ?? '') !== ''
        ));

        return [
            'name' => $this->name,
            'process_key' => $this->processKey,
            'subject_type' => $this->subjectType !== '' ? $this->subjectType : null,
            'request_form_fields' => $this->subjectType === '' && $requestFormFields !== [] ? $requestFormFields : null,
            'is_active' => $this->isActive,
            'steps' => $steps,
            'transitions' => $transitions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStep(string $type, string $name): array
    {
        return [
            'step_key' => $this->generateKey($name !== '' ? $name : $type).'-'.substr((string) Str::uuid(), 0, 4),
            'name' => $name,
            'step_type' => $type,
            'assignment_type' => '',
            'assigned_role' => '',
            'assigned_user_id' => '',
            'condition_field' => '',
            'condition_operator' => '',
            'condition_value' => '',
        ];
    }

    private function generateKey(string $source): string
    {
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::slug(Str::random(6));
    }

    public function getConditionOperatorOptionsProperty(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], ConditionOperator::cases());
    }

    public function getStepTypeOptionsProperty(): array
    {
        $cases = StepType::cases();

        if ($this->subjectType === '') {
            $cases = array_filter($cases, fn ($case) => $case !== StepType::Condition);
        }

        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    public function getAssignmentTypeOptionsProperty(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], AssignmentType::cases());
    }

    public const ROLE_OPTIONS = [
        ['value' => 'holding_admin', 'label' => 'مدیر ارشد هلدینگ'],
        ['value' => 'accountant', 'label' => 'حسابدار'],
        ['value' => 'operator', 'label' => 'عملیات'],
        ['value' => 'viewer', 'label' => 'بیننده'],
    ];

    public function render()
    {
        return view('livewire.process.process-definition-form');
    }
}
