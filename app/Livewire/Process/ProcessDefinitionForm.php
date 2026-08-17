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

    /** '' یعنی فرایند آزاد (بدون subject_type). */
    public string $subjectType = '';

    public bool $isActive = true;

    /**
     * فقط برای فرایند آزاد — همان الگوی مفهومی editable_fields ماژول
     * SiteBuilder (کلید + برچسب + نوع)، ساده‌شده چون اینجا فقط یک فرم
     * درخواست تخت لازم است، نه ساختار تودرتوی ویجت. کلید (key) هرگز از کاربر
     * گرفته نمی‌شود (بخش ۵ Session جاری) — دقیقاً مثل process_key/step_key
     * یک‌بار در لحظه‌ی افزودن فیلد تولید می‌شود (نگاه کن newFieldKey()).
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
                    'step_form_fields' => $step->step_form_fields ?? [],
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

    /**
     * منبع فیلدهای مجاز شرط با تعویض subjectType کاملاً عوض می‌شود (از
     * config('processes.condition_fields') به فیلدهای فرم خودِ فرایند آزاد،
     * یا برعکس — بخش ۲ Session جاری) — مرحله‌ی شرط دیگر مثل قبل به approval
     * تبدیل نمی‌شود (فرایند آزاد هم حالا شرط را پشتیبانی می‌کند)، فقط انتخاب
     * قبلی فیلد/عملگر/مقدار که دیگر معتبر نیست پاک می‌شود.
     */
    public function updatedSubjectType(): void
    {
        foreach ($this->steps as $index => $step) {
            if ($step['step_type'] === StepType::Condition->value) {
                $this->steps[$index]['condition_field'] = '';
                $this->steps[$index]['condition_operator'] = '';
                $this->steps[$index]['condition_value'] = '';
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

    public function addRequestField(): void
    {
        $this->requestFormFields[] = ['key' => $this->newFieldKey(''), 'label' => '', 'type' => 'text'];
    }

    public function removeRequestField(int $index): void
    {
        unset($this->requestFormFields[$index]);
        $this->requestFormFields = array_values($this->requestFormFields);
    }

    /**
     * فرم اضافه‌ی اختیاری خودِ یک مرحله‌ی approval (بخش ۳ Session جاری) —
     * همان الگوی requestFormFields، فقط سطح مرحله.
     */
    public function addStepFormField(int $stepIndex): void
    {
        $this->steps[$stepIndex]['step_form_fields'][] = ['key' => $this->newFieldKey(''), 'label' => '', 'type' => 'text'];
    }

    public function removeStepFormField(int $stepIndex, int $fieldIndex): void
    {
        unset($this->steps[$stepIndex]['step_form_fields'][$fieldIndex]);
        $this->steps[$stepIndex]['step_form_fields'] = array_values($this->steps[$stepIndex]['step_form_fields']);
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
     * دو منبع موازی (بخش ۲ Session جاری): فرایند آزاد از فیلدهای فرم درخواست
     * خودِ همین تعریف (برچسب = همان چیزی که ادمین در فرم تایپ کرده)، فرایند
     * وصل‌به‌ماژول از config('processes.condition_fields') + برچسب فارسی
     * بخش ۷ (config('processes.condition_field_labels')).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getConditionFieldOptionsProperty(): array
    {
        if ($this->subjectType === '') {
            return collect($this->requestFormFields)
                ->filter(fn ($field) => ($field['key'] ?? '') !== '' && ($field['label'] ?? '') !== '')
                ->map(fn ($field) => ['value' => $field['key'], 'label' => $field['label']])
                ->values()
                ->all();
        }

        $labels = config("processes.condition_field_labels.{$this->subjectType}", []);

        return collect(config("processes.condition_fields.{$this->subjectType}", []))
            ->map(fn ($field) => ['value' => $field, 'label' => $labels[$field]['label'] ?? $field])
            ->values()
            ->all();
    }

    /**
     * راهنمای توضیحی فارسی هر فیلد شرط (بخش ۷ Session جاری) — فقط برای
     * فرایند وصل‌به‌ماژول معنا دارد؛ فرایند آزاد برچسبش را از خودِ فرم می‌گیرد،
     * راهنمای اضافه لازم نیست.
     *
     * @return array<string, string>
     */
    public function getConditionFieldHintsProperty(): array
    {
        if ($this->subjectType === '') {
            return [];
        }

        $labels = config("processes.condition_field_labels.{$this->subjectType}", []);

        return collect($labels)->map(fn ($meta) => $meta['hint'] ?? null)->filter()->all();
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'subjectType' => ['nullable', Rule::in(array_merge([''], config('processes.subject_types', [])))],
        ];
    }

    /**
     * process_key هرگز از کاربر گرفته نمی‌شود (بخش ۵ Session جاری) — همیشه از
     * روی نام تولید می‌شود، با پسوند عددی در صورت تصادم (همان الگوی autosave
     * اسلاگ ماژول Blog). برای تعریفی که سابقه دارد، UpdateProcessDefinition
     * اصلاً process_key را دست نمی‌زند (نگاه کن bend hasHistory آن Action)، پس
     * کلید موجود بدون تغییر می‌ماند.
     */
    public function save(CreateProcessDefinition $createAction, UpdateProcessDefinition $updateAction): void
    {
        $this->graphErrors = [];

        $this->validate();

        $companyId = $this->record?->owner_company_id ?? app(CompanyContext::class)->id();

        $processKey = $this->hasHistory
            ? $this->record->process_key
            : $this->resolveUniqueProcessKey($this->name, $companyId, $this->record?->id);

        $payload = $this->extractPayload($processKey);

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
    private function extractPayload(string $processKey): array
    {
        $steps = [];

        foreach ($this->steps as $step) {
            $isApproval = $step['step_type'] === StepType::Approval->value;
            $isCondition = $step['step_type'] === StepType::Condition->value;
            $isRoleAssignment = $isApproval && $step['assignment_type'] === AssignmentType::Role->value;
            $isUserAssignment = $isApproval && $step['assignment_type'] === AssignmentType::SpecificUser->value;

            $stepFormFields = array_values(array_filter(
                $step['step_form_fields'] ?? [],
                fn ($field) => ($field['label'] ?? '') !== ''
            ));

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
                'step_form_fields' => $isApproval && $stepFormFields !== [] ? $stepFormFields : null,
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
            'process_key' => $processKey,
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
            'step_form_fields' => [],
        ];
    }

    private function generateKey(string $source): string
    {
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::slug(Str::random(6));
    }

    /**
     * کلید یک فیلد فرم (requestFormFields/step_form_fields) — دقیقاً همان
     * الگوی emptyStep() برای step_key: یک‌بار در لحظه‌ی افزودن، از روی برچسب
     * موجود (اغلب هنوز خالی) + پسوند تصادفی، نه از روی ورودی دستی کاربر
     * (بخش ۵ Session جاری). چون پسوند همیشه تصادفی است، یکتایی درون همان
     * آرایه تضمین‌شده است.
     */
    private function newFieldKey(string $label): string
    {
        return $this->generateKey($label !== '' ? $label : 'field').'-'.substr((string) Str::uuid(), 0, 4);
    }

    /**
     * process_key کاربر را هرگز نمی‌بیند/تایپ نمی‌کند — همیشه از روی نام
     * تولید می‌شود، با پسوند عددی در صورت تصادم درون همان شرکت (uq_process_definitions_company_key).
     *
     * withTrashed() عمداً: uq_process_definitions_company_key یک UNIQUE
     * سطح دیتابیس روی ردیف فیزیکی است و بایگانی‌شدن (soft-delete) ردیف را
     * پاک نمی‌کند، فقط deleted_at را پر می‌کند — پس یک کلید بایگانی‌شده
     * همچنان برای همیشه رزرو می‌ماند. بدون withTrashed() اینجا، این تابع
     * چنین کلیدی را «آزاد» تشخیص می‌داد (چون SoftDeletes به‌صورت پیش‌فرض
     * بایگانی‌شده‌ها را از کوئری کنار می‌گذارد) و insert با یک
     * QueryException خام رد می‌شد. تصمیم: کلید برای همیشه رزرو می‌ماند
     * (نه آزادسازی بعد از بایگانی) — ساده‌تر، بدون نیاز به تغییر schema
     * ایندکس یکتا، و ابهام تاریخی بین دو تعریف با کلید یکسان را از بین
     * می‌برد؛ فرایند جدید با همان نام به‌جایش پسوند عددی می‌گیرد.
     */
    private function resolveUniqueProcessKey(string $name, ?string $companyId, ?string $excludeId): string
    {
        $base = $this->generateKey($name);
        $key = $base;
        $suffix = 1;

        while (
            ProcessDefinition::withoutGlobalScope('owner_company')
                ->withTrashed()
                ->where('owner_company_id', $companyId)
                ->where('process_key', $key)
                ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $suffix++;
            $key = $base.'-'.$suffix;
        }

        return $key;
    }

    public function getConditionOperatorOptionsProperty(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], ConditionOperator::cases());
    }

    /**
     * مرحله‌ی شرط برای هر دو نوع فرایند مجاز است (بخش ۲ Session جاری):
     * فرایند وصل‌به‌ماژول از config('processes.condition_fields')، فرایند
     * آزاد از فیلدهای فرم خودش — نگاه کن getConditionFieldOptionsProperty.
     */
    public function getStepTypeOptionsProperty(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], StepType::cases());
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
