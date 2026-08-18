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

    /**
     * خطاهای همان مرحله‌ی جاری ویزارد (بخش «هر مرحله فقط با تکمیل معتبر آن
     * مرحله اجازه‌ی بعدی بدهد» — پیام‌ها فقط تا رفع‌شدن روی همان مرحله می‌مانند،
     * برخلاف graphErrors که خروجی نهایی ProcessGraphValidator است).
     *
     * @var array<int, string>
     */
    public array $stepErrors = [];

    /**
     * شماره‌ی مرحله‌ی فعلی ویزارد (۱ تا ۵). فقط برای تعریف بدون سابقه‌ی اجرا
     * معنا دارد — تعریف دارای سابقه (hasHistory) اصلاً ویزارد نمی‌بیند، یک
     * فرم ساده‌ی نام/فعال جایگزینش می‌شود.
     */
    public int $currentStep = 1;

    /**
     * بالاترین مرحله‌ای که کاربر تا این لحظه با اعتبارسنجی موفق به آن رسیده —
     * برگشت به عقب همیشه آزاد است، پرش به جلو بدون تکمیل مراحل قبلی مجاز
     * نیست (goToStep این را چک می‌کند).
     */
    public int $maxReachedStep = 1;

    /**
     * ترتیب نمایش ردیف‌های «گذارها» در مرحله ۴ (کلید = step_key هر مرحله‌ی
     * start/approval/condition) — فقط نمایش/display_order، مستقل از ترتیب
     * واقعی $steps و بدون هیچ اثر روی نتیجه‌ی اجرای ProcessEngine (که همیشه
     * از روی from_step_id/on_result کار می‌کند، نه ترتیب). با درگ‌اند‌دراپ
     * (moveTransitionRow) قابل‌تغییر است؛ با syncTransitionOrder() همیشه با
     * مجموعه‌ی واقعی مراحل شرط‌پذیر هماهنگ نگه داشته می‌شود.
     *
     * @var array<int, string>
     */
    public array $transitionOrder = [];

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
                        StepType::RequesterInput => $this->transitionSelections[$step->step_key]['default'] = $toKey,
                        default => null,
                    };
                }
            }

            // تعریف دارای سابقه اصلاً ویزارد نمی‌بیند (فقط فرم ساده‌ی نام/فعال)،
            // پس ماندن روی مرحله‌ی ۱ برایش بی‌اثر است؛ برای تعریف بدون سابقه در
            // حال ویرایش، کل ویزارد از ابتدا با مقادیر موجود قابل‌عبور است.
            $this->maxReachedStep = 5;
            $this->syncTransitionOrder();

            return;
        }

        $this->authorize('create', ProcessDefinition::class);

        // یک مرحله‌ی شروع پیش‌فرض — هر فرایند دقیقاً یک مرحله‌ی شروع لازم دارد،
        // شروع خالی سردرگم‌کننده بود.
        $this->steps[] = $this->emptyStep(StepType::Start->value, 'شروع');
        $this->syncTransitionOrder();
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

    // ===================================================================
    // ناوبری ویزارد — فقط برای تعریف بدون سابقه (hasHistory=false) معنا
    // دارد؛ blade برای hasHistory=true اصلاً این متدها را صدا نمی‌زند.
    // ===================================================================

    /**
     * مرحله ۲ (فرم درخواست) فقط برای فرایند آزاد معنا دارد — فرایند
     * وصل‌به‌ماژول کامل از آن رد می‌شود (نگاه کن nextStep/prevStep).
     */
    public function getIsStep2ApplicableProperty(): bool
    {
        return $this->subjectType === '';
    }

    /**
     * @return array<int, string>
     */
    public function getWizardStepLabelsProperty(): array
    {
        return [
            1 => 'اطلاعات پایه',
            2 => 'فرم درخواست',
            3 => 'مراحل',
            4 => 'گذارها',
            5 => 'بازبینی و ذخیره',
        ];
    }

    /**
     * برگشت به هر مرحله‌ی قبلاً دیده‌شده آزاد است؛ پرش به جلو بدون تکمیل
     * مراحل قبلی مجاز نیست (maxReachedStep این را تضمین می‌کند).
     */
    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > 5 || $step > $this->maxReachedStep) {
            return;
        }

        if ($step === 2 && ! $this->isStep2Applicable) {
            return;
        }

        $this->currentStep = $step;
    }

    public function nextStep(): void
    {
        $this->stepErrors = $this->validateCurrentStep();

        if ($this->stepErrors !== []) {
            return;
        }

        $next = $this->currentStep + 1;

        if ($next === 2 && ! $this->isStep2Applicable) {
            $next = 3;
        }

        if ($next > 5) {
            return;
        }

        $this->currentStep = $next;
        $this->maxReachedStep = max($this->maxReachedStep, $next);
    }

    public function prevStep(): void
    {
        $this->stepErrors = [];

        $prev = $this->currentStep - 1;

        if ($prev === 2 && ! $this->isStep2Applicable) {
            $prev = 1;
        }

        if ($prev < 1) {
            return;
        }

        $this->currentStep = $prev;
    }

    /**
     * @return array<int, string>
     */
    private function validateCurrentStep(): array
    {
        return match ($this->currentStep) {
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            3 => $this->validateStep3(),
            4 => $this->validateStep4(),
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function validateStep1(): array
    {
        $errors = [];

        if (trim($this->name) === '') {
            $errors[] = 'نام فرایند نمی‌تواند خالی باشد.';
        }

        if ($this->subjectType !== '' && ! in_array($this->subjectType, config('processes.subject_types', []), true)) {
            $errors[] = 'نوع انتخاب‌شده معتبر نیست.';
        }

        return $errors;
    }

    /**
     * برچسب تکراری بین فیلدهای فرم درخواست همین‌جا رد می‌شود، نه در پایان
     * (وگرنه دو فیلد هم‌نام بعداً در فیلد شرط مرحله ۳ قابل‌تفکیک نیستند).
     *
     * @return array<int, string>
     */
    private function validateStep2(): array
    {
        $errors = [];
        $seenLabels = [];

        foreach ($this->requestFormFields as $field) {
            $label = trim($field['label'] ?? '');

            if ($label === '') {
                $errors[] = 'همه‌ی فیلدهای فرم درخواست باید برچسب داشته باشند.';

                continue;
            }

            if (in_array($label, $seenLabels, true)) {
                $errors[] = "برچسب «{$label}» در فرم درخواست تکراری است.";

                continue;
            }

            $seenLabels[] = $label;
        }

        return $errors;
    }

    /**
     * فقط بررسی‌های ساختاری سطح مرحله (تعداد start/end، فیلدهای اختصاصی هر
     * نوع مرحله) — بررسی کامل گراف (گذارها/دسترس‌پذیری/چرخه) در مرحله ۴ و
     * نهایتاً ProcessGraphValidator در save() انجام می‌شود.
     *
     * @return array<int, string>
     */
    private function validateStep3(): array
    {
        $errors = [];

        $startCount = collect($this->steps)->where('step_type', StepType::Start->value)->count();
        $endCount = collect($this->steps)->where('step_type', StepType::End->value)->count();

        if ($startCount !== 1) {
            $errors[] = 'فرایند باید دقیقاً یک مرحله‌ی «شروع» داشته باشد.';
        }

        if ($endCount < 1) {
            $errors[] = 'فرایند باید حداقل یک مرحله‌ی «پایان» داشته باشد.';
        }

        foreach ($this->steps as $step) {
            $label = ($step['name'] ?? '') !== '' ? $step['name'] : $step['step_key'];

            if ($step['step_type'] === StepType::Approval->value) {
                if (! in_array($step['assignment_type'], [AssignmentType::Role->value, AssignmentType::SpecificUser->value], true)) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید نوع واگذاری (نقش یا کاربر مشخص) داشته باشد.";
                } elseif ($step['assignment_type'] === AssignmentType::Role->value && $step['assigned_role'] === '') {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید یک نقش برای واگذاری داشته باشد.";
                } elseif ($step['assignment_type'] === AssignmentType::SpecificUser->value && $step['assigned_user_id'] === '') {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید یک کاربر مشخص برای واگذاری داشته باشد.";
                }
            }

            if ($step['step_type'] === StepType::Condition->value) {
                if ($step['condition_field'] === '') {
                    $errors[] = "مرحله‌ی شرط «{$label}» باید یک فیلد شرط انتخاب‌شده داشته باشد.";
                }

                if ($step['condition_operator'] === '') {
                    $errors[] = "مرحله‌ی شرط «{$label}» باید یک عملگر انتخاب‌شده داشته باشد.";
                }

                if ($step['condition_value'] === '') {
                    $errors[] = "مرحله‌ی شرط «{$label}» باید یک مقدار مقایسه داشته باشد.";
                }
            }

            if ($step['step_type'] === StepType::RequesterInput->value) {
                $fields = array_filter($step['step_form_fields'] ?? [], fn ($field) => ($field['label'] ?? '') !== '');

                if ($fields === []) {
                    $errors[] = "مرحله‌ی «{$label}» باید حداقل یک فیلد فرم داشته باشد.";
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function validateStep4(): array
    {
        $errors = [];

        foreach ($this->steps as $step) {
            $key = $step['step_key'];
            $label = ($step['name'] ?? '') !== '' ? $step['name'] : $key;
            $selection = $this->transitionSelections[$key] ?? [];

            if ($step['step_type'] === StepType::Start->value && empty($selection['next'])) {
                $errors[] = "مرحله‌ی شروع «{$label}» باید مرحله‌ی بعد مشخص شود.";
            }

            if ($step['step_type'] === StepType::Approval->value) {
                if (empty($selection['approved'])) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» مقصد «اگر تأیید شد» ندارد.";
                }
                if (empty($selection['rejected'])) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» مقصد «اگر رد شد» ندارد.";
                }
            }

            if ($step['step_type'] === StepType::Condition->value) {
                if (empty($selection['true'])) {
                    $errors[] = "مرحله‌ی شرط «{$label}» مقصد «اگر شرط درست بود» ندارد.";
                }
                if (empty($selection['false'])) {
                    $errors[] = "مرحله‌ی شرط «{$label}» مقصد «اگر نادرست بود» ندارد.";
                }
            }

            if ($step['step_type'] === StepType::RequesterInput->value && empty($selection['default'])) {
                $errors[] = "مرحله‌ی «{$label}» باید مرحله‌ی بعد (بعد از ارسال) مشخص شود.";
            }
        }

        return $errors;
    }

    /**
     * پیام‌های ProcessGraphValidator را بر اساس واژه‌ی کلیدی به مرحله‌ی
     * دقیق مسئول (۳ = ساختار مراحل، ۴ = گذارها/دسترس‌پذیری/چرخه) نگاشت
     * می‌دهد تا save() بتواند کاربر را مستقیم به همان مرحله برگرداند، نه
     * فقط یک پیام کلی نشان بدهد.
     */
    private function graphErrorStep(string $message): int
    {
        foreach (['گذار', 'یتیم', 'چرخه'] as $keyword) {
            if (str_contains($message, $keyword)) {
                return 4;
            }
        }

        return 3;
    }

    // ===================================================================
    // ترتیب نمایش ردیف‌های «گذارها» (مرحله ۴) — فقط display_order، بدون
    // اثر روی نتیجه‌ی اجرای واقعی (بخش «جابه‌جایی گذارها» Session جاری).
    // ===================================================================

    /**
     * مجموعه‌ی کلیدهای مراحلی که واقعاً به تعریف گذار نیاز دارند
     * (start/approval/condition — end هرگز گذار خروجی ندارد).
     *
     * @return array<int, string>
     */
    private function transitionableStepKeys(): array
    {
        return collect($this->steps)
            ->filter(fn ($step) => in_array($step['step_type'], [
                StepType::Start->value, StepType::Approval->value, StepType::Condition->value, StepType::RequesterInput->value,
            ], true))
            ->pluck('step_key')
            ->values()
            ->all();
    }

    /**
     * transitionOrder را با مجموعه‌ی واقعی مراحل شرط‌پذیر هماهنگ نگه
     * می‌دارد: ترتیب موجود برای کلیدهای باقی‌مانده حفظ می‌شود، کلیدهای
     * تازه (مرحله‌ی جدید) به انتها اضافه، کلیدهای حذف‌شده کنار گذاشته
     * می‌شوند. در mount() و ابتدای render() صدا زده می‌شود تا همیشه، حتی
     * بدون عبور از ویزارد (مثل تست‌هایی که مستقیم save() را صدا می‌زنند)،
     * synced بماند.
     */
    private function syncTransitionOrder(): void
    {
        $relevant = $this->transitionableStepKeys();
        $existing = array_values(array_intersect($this->transitionOrder, $relevant));
        $missing = array_values(array_diff($relevant, $existing));

        $this->transitionOrder = array_merge($existing, $missing);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderedTransitionStepsProperty(): array
    {
        $stepsByKey = collect($this->steps)->keyBy('step_key');

        return collect($this->transitionOrder)
            ->map(fn ($key) => $stepsByKey[$key] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * فراخوانی سمت کلاینت از resources/js/process-sortable.js بعد از
     * رهاشدن یک ردیف — فقط ترتیب $transitionOrder را عوض می‌کند، هرگز
     * transitionSelections/steps (یعنی مقصد واقعی هر نتیجه) را دست نمی‌زند.
     */
    public function moveTransitionRow(string $stepKey, int $newIndex): void
    {
        $order = array_values(array_filter($this->transitionOrder, fn ($key) => $key !== $stepKey));
        $newIndex = max(0, min($newIndex, count($order)));

        array_splice($order, $newIndex, 0, [$stepKey]);

        $this->transitionOrder = $order;
    }

    /**
     * خلاصه‌ی خوانای کل گراف برای مرحله ۵ (بازبینی) — یک ردیف به‌ازای هر
     * مرحله‌ی شرط‌پذیر با مقصد هر نتیجه‌ی ممکنش، به همان ترتیب transitionOrder.
     *
     * @return array<int, array{label: string, type: string, targets: array<int, array{label: string, to_label: string}>}>
     */
    public function getReviewFlowProperty(): array
    {
        $stepsByKey = collect($this->steps)->keyBy('step_key');

        return collect($this->orderedTransitionSteps)->map(function ($step) use ($stepsByKey) {
            $key = $step['step_key'];
            $label = ($step['name'] ?? '') !== '' ? $step['name'] : $key;
            $selection = $this->transitionSelections[$key] ?? [];

            $targets = match ($step['step_type']) {
                StepType::Start->value => [
                    ['label' => 'مرحله‌ی بعد', 'to' => $selection['next'] ?? null],
                ],
                StepType::Approval->value => [
                    ['label' => 'اگر تأیید شد', 'to' => $selection['approved'] ?? null],
                    ['label' => 'اگر رد شد', 'to' => $selection['rejected'] ?? null],
                ],
                StepType::Condition->value => [
                    ['label' => 'اگر شرط درست بود', 'to' => $selection['true'] ?? null],
                    ['label' => 'اگر نادرست بود', 'to' => $selection['false'] ?? null],
                ],
                StepType::RequesterInput->value => [
                    ['label' => 'بعد از ارسال', 'to' => $selection['default'] ?? null],
                ],
                default => [],
            };

            return [
                'label' => $label,
                'type' => StepType::from($step['step_type'])->label(),
                'targets' => array_map(function ($target) use ($stepsByKey) {
                    $toStep = $target['to'] ? ($stepsByKey[$target['to']] ?? null) : null;

                    return [
                        'label' => $target['label'],
                        'to_label' => $toStep ? (($toStep['name'] ?? '') !== '' ? $toStep['name'] : $toStep['step_key']) : '—',
                    ];
                }, $targets),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReviewEndStepsProperty(): array
    {
        return collect($this->steps)->where('step_type', StepType::End->value)->values()->all();
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
        $this->stepErrors = [];

        try {
            $this->validate();
        } catch (ValidationException $e) {
            // خطای نام/نوع همیشه مربوط به مرحله‌ی ۱ است — کاربر را همان‌جا
            // نگه می‌داریم تا x-input/x-select مرحله‌ی ۱ (که فقط وقتی
            // currentStep===1 رندر می‌شود) پیام inline را نشان بدهد.
            $this->currentStep = 1;
            $this->maxReachedStep = max($this->maxReachedStep, 1);

            throw $e;
        }

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
            $messages = array_merge(...array_values($e->errors()));
            $this->graphErrors = $messages;

            // کاربر را مستقیم به اولین مرحله‌ی دارای خطا برمی‌گردانیم — مراحل
            // ساختاری (۳) بر گذارها (۴) اولویت دارند چون تا وقتی مراحل خودشان
            // ناقص‌اند، خطای گذار معمولاً پیامد همان مشکل عمیق‌تر است.
            $targetStep = collect($messages)->contains(fn ($m) => $this->graphErrorStep($m) === 3) ? 3 : 4;
            $this->currentStep = $targetStep;
            $this->maxReachedStep = max($this->maxReachedStep, $targetStep);

            $this->error('ساختار فرایند نامعتبر است — به مرحله‌ی مشکل‌دار برگشتید.');

            return;
        }

        $this->success('فرایند ذخیره شد.', redirectTo: route('processes.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(string $processKey): array
    {
        $this->syncTransitionOrder();

        $steps = [];

        foreach ($this->steps as $step) {
            $isApproval = $step['step_type'] === StepType::Approval->value;
            $isCondition = $step['step_type'] === StepType::Condition->value;
            $isRequesterInput = $step['step_type'] === StepType::RequesterInput->value;
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
                'step_form_fields' => ($isApproval || $isRequesterInput) && $stepFormFields !== [] ? $stepFormFields : null,
            ];
        }

        $transitions = [];

        // ترتیب تکرار از transitionOrder می‌آید (نه $this->steps خام) تا
        // display_order گذارها بازتاب ترتیب واقعی درگ‌شده در مرحله ۴ ویزارد
        // باشد — مقصد هر نتیجه (transitionSelections) کاملاً مستقل می‌ماند.
        foreach ($this->orderedTransitionSteps as $step) {
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

            if ($step['step_type'] === StepType::RequesterInput->value && ! empty($selection['default'])) {
                $transitions[] = ['from_step_key' => $key, 'to_step_key' => $selection['default'], 'on_result' => TransitionResult::Default->value];
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
        $this->syncTransitionOrder();

        return view('livewire.process.process-definition-form');
    }
}
