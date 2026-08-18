<div>
    <x-header
        title="{{ $record ? 'ویرایش فرایند' : 'فرایند جدید' }}"
        subtitle="{{ $this->hasHistory ? 'فقط نام و وضعیت فعال/غیرفعال' : 'مرحله‌به‌مرحله جلو بروید — هر مرحله فقط با تکمیل معتبر همان مرحله باز می‌شود' }}"
        separator
    />

    @if($this->hasHistory)
        <x-alert
            title="این فرایند سابقه‌ی اجرا دارد"
            description="چون حداقل یک بار اجرا شده (در جریان یا تمام‌شده)، ساختار مراحل/گذارها دیگر قابل‌ویرایش نیست — تاریخچه‌ی واقعی نباید زیر پایش عوض شود. فقط نام و وضعیت فعال/غیرفعال قابل‌تغییر است. برای تغییر واقعی گردش‌کار، یک فرایند جدید بسازید و این را غیرفعال کنید."
            :icon="theme_icon('locked')"
            class="alert-warning mb-4"
        />

        <x-form wire:submit="save" class="gap-5">
            <x-card title="اطلاعات پایه" shadow>
                <x-input label="نام فرایند" wire:model="name" :icon="theme_icon('process')" required />
                <x-select
                    label="نوع"
                    wire:model="subjectType"
                    :options="array_merge([['value' => '', 'label' => 'فرایند آزاد (بدون اتصال به ماژول)']], $this->subjectTypeOptions)"
                    option-value="value"
                    option-label="label"
                    disabled
                />
                <x-checkbox label="فعال" wire:model="isActive" hint="غیرفعال یعنی فرایندهای تازه از این تعریف شروع نمی‌شوند" />
            </x-card>

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('processes.index') }}" class="btn-ghost" />
                <x-button label="ذخیره تغییرات" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
            </x-slot:actions>
        </x-form>
    @else

    {{-- نوار پیشرفت ویزارد — برگشت به مرحله‌ی قبلاً دیده‌شده آزاد است، پرش به
    جلو بدون تکمیل مرحله‌ی جاری مجاز نیست (goToStep این را چک می‌کند). --}}
    <ul class="steps steps-horizontal w-full mb-6">
        @foreach($this->wizardStepLabels as $num => $label)
            @continue($num === 2 && ! $this->isStep2Applicable)
            <li
                class="step {{ $currentStep >= $num ? 'step-primary' : '' }} {{ $num <= $maxReachedStep ? 'cursor-pointer' : '' }}"
                @if($num <= $maxReachedStep && $num !== $currentStep) wire:click="goToStep({{ $num }})" @endif
            >
                {{ $label }}
            </li>
        @endforeach
    </ul>

    @if($stepErrors)
        <x-alert title="این مرحله هنوز کامل نیست" :icon="theme_icon('warning')" class="alert-error mb-4">
            <ul class="list-disc ps-5">
                @foreach($stepErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if($graphErrors)
        <x-alert title="ساختار فرایند نامعتبر است" :icon="theme_icon('warning')" class="alert-error mb-4">
            <ul class="list-disc ps-5">
                @foreach($graphErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    {{-- ============================================================ --}}
    {{-- مرحله ۱ — اطلاعات پایه --}}
    {{-- ============================================================ --}}
    @if($currentStep === 1)
        <x-card title="۱. اطلاعات پایه" subtitle="نام فرایند و این‌که به یک ماژول وصل است یا آزاد است" shadow>
            <x-input label="نام فرایند" wire:model="name" :icon="theme_icon('process')" required />

            <x-select
                label="نوع"
                wire:model.live="subjectType"
                :options="array_merge([['value' => '', 'label' => 'فرایند آزاد (بدون اتصال به ماژول)']], $this->subjectTypeOptions)"
                option-value="value"
                option-label="label"
                hint="فرایند وصل‌به‌ماژول روی رکورد واقعی همان ماژول (مثل درخواست مرخصی) اجرا می‌شود؛ فرایند آزاد فرم درخواست خودش را دارد."
            />

            <x-checkbox label="فعال" wire:model="isActive" hint="غیرفعال یعنی فرایندهای تازه از این تعریف شروع نمی‌شوند" />
        </x-card>

        <div class="flex justify-end mt-4">
            <x-button label="مرحله بعد" :icon="theme_icon('next')" class="btn-primary" wire:click="nextStep" spinner="nextStep" />
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- مرحله ۲ — فرم درخواست (فقط فرایند آزاد) --}}
    {{-- ============================================================ --}}
    @if($currentStep === 2 && $this->isStep2Applicable)
        <x-card title="۲. فرم درخواست" subtitle="فیلدهایی که درخواست‌دهنده هنگام شروع فرایند پر می‌کند" shadow>
            @foreach($requestFormFields as $i => $field)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                    <x-input label="برچسب" wire:model="requestFormFields.{{ $i }}.label" />
                    <x-select
                        label="نوع"
                        wire:model="requestFormFields.{{ $i }}.type"
                        :options="[
                            ['value' => 'text', 'label' => 'متن کوتاه'],
                            ['value' => 'textarea', 'label' => 'متن چندخطی'],
                            ['value' => 'number', 'label' => 'عدد'],
                            ['value' => 'boolean', 'label' => 'بله/خیر'],
                        ]"
                        option-value="value"
                        option-label="label"
                    />
                    <x-button :icon="theme_icon('delete')" class="btn-circle btn-ghost btn-sm text-error" wire:click="removeRequestField({{ $i }})" />
                </div>
            @endforeach

            <x-button label="افزودن فیلد" :icon="theme_icon('add')" class="btn-ghost btn-sm" wire:click="addRequestField" />

            @if(empty($requestFormFields))
                <p class="text-sm text-base-content/60">این فرایند می‌تواند بدون هیچ فیلد اضافه‌ای هم شروع شود — افزودن فیلد اختیاری است.</p>
            @endif
        </x-card>

        <div class="flex justify-between mt-4">
            <x-button label="مرحله قبل" class="btn-ghost" wire:click="prevStep" />
            <x-button label="مرحله بعد" :icon="theme_icon('next')" class="btn-primary" wire:click="nextStep" spinner="nextStep" />
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- مرحله ۳ — مراحل --}}
    {{-- ============================================================ --}}
    @if($currentStep === 3)
        <x-card title="۳. مراحل" subtitle="هر فرایند دقیقاً یک مرحله‌ی شروع و حداقل یک مرحله‌ی پایان لازم دارد" shadow>
            <div class="rounded-box border border-dashed border-base-300 p-3 mb-4 flex flex-wrap gap-2">
                @foreach($steps as $outlineStep)
                    <x-badge :value="($outlineStep['name'] ?: $outlineStep['step_key']).' — '.\App\Modules\Process\Enums\StepType::from($outlineStep['step_type'])->label()" class="badge-ghost" />
                @endforeach
            </div>

            @foreach($steps as $i => $step)
                <div class="border border-base-300 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <x-input label="نام مرحله" wire:model="steps.{{ $i }}.name" />

                        <x-select
                            label="نوع مرحله"
                            wire:model.live="steps.{{ $i }}.step_type"
                            :options="$this->stepTypeOptions"
                            option-value="value"
                            option-label="label"
                        />
                    </div>

                    @if($step['step_type'] === 'approval')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <x-select
                                label="واگذاری به"
                                wire:model.live="steps.{{ $i }}.assignment_type"
                                :options="$this->assignmentTypeOptions"
                                option-value="value"
                                option-label="label"
                                placeholder="انتخاب کنید"
                            />

                            @if($step['assignment_type'] === 'role')
                                <x-select
                                    label="نقش"
                                    wire:model="steps.{{ $i }}.assigned_role"
                                    :options="\App\Livewire\Process\ProcessDefinitionForm::ROLE_OPTIONS"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                            @elseif($step['assignment_type'] === 'specific_user')
                                <x-select
                                    label="کاربر"
                                    wire:model="steps.{{ $i }}.assigned_user_id"
                                    :options="$this->companyUsers"
                                    option-value="id"
                                    option-label="full_name"
                                    placeholder="انتخاب کنید"
                                />
                            @endif
                        </div>

                        <div class="mt-3 border-t border-base-300 pt-3" x-data="{ open: {{ ! empty($step['step_form_fields']) ? 'true' : 'false' }} }">
                            <x-checkbox
                                label="این مرحله فرم اضافه دارد؟"
                                hint="فیلدهایی که مسئول این مرحله هنگام تأیید/رد پر می‌کند — کاملاً اختیاری"
                                x-model="open"
                            />

                            <div x-show="open" x-cloak class="mt-2">
                                @foreach($step['step_form_fields'] ?? [] as $fi => $stepField)
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                                        <x-input label="برچسب فیلد" wire:model="steps.{{ $i }}.step_form_fields.{{ $fi }}.label" />
                                        <x-select
                                            label="نوع"
                                            wire:model="steps.{{ $i }}.step_form_fields.{{ $fi }}.type"
                                            :options="[
                                                ['value' => 'text', 'label' => 'متن کوتاه'],
                                                ['value' => 'textarea', 'label' => 'متن چندخطی'],
                                                ['value' => 'number', 'label' => 'عدد'],
                                                ['value' => 'boolean', 'label' => 'بله/خیر'],
                                            ]"
                                            option-value="value"
                                            option-label="label"
                                        />
                                        <x-button :icon="theme_icon('delete')" class="btn-circle btn-ghost btn-sm text-error" wire:click="removeStepFormField({{ $i }}, {{ $fi }})" />
                                    </div>
                                @endforeach

                                <x-button label="افزودن فیلد فرم مرحله" :icon="theme_icon('add')" class="btn-ghost btn-sm" wire:click="addStepFormField({{ $i }})" />
                            </div>
                        </div>
                    @endif

                    @if($step['step_type'] === 'requester_input')
                        <div class="mt-3 border-t border-base-300 pt-3">
                            <x-alert
                                title="فرم این مرحله را فرستنده‌ی اصلی درخواست پر می‌کند"
                                description="هیچ نقش یا شخص مشخصی به این مرحله واگذار نمی‌شود — همیشه همان کسی که فرایند را شروع کرده. حداقل یک فیلد فرم الزامی است."
                                :icon="theme_icon('site-form')"
                                class="alert-info mb-3"
                            />

                            @foreach($step['step_form_fields'] ?? [] as $fi => $stepField)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                                    <x-input label="برچسب فیلد" wire:model="steps.{{ $i }}.step_form_fields.{{ $fi }}.label" />
                                    <x-select
                                        label="نوع"
                                        wire:model="steps.{{ $i }}.step_form_fields.{{ $fi }}.type"
                                        :options="[
                                            ['value' => 'text', 'label' => 'متن کوتاه'],
                                            ['value' => 'textarea', 'label' => 'متن چندخطی'],
                                            ['value' => 'number', 'label' => 'عدد'],
                                            ['value' => 'boolean', 'label' => 'بله/خیر'],
                                        ]"
                                        option-value="value"
                                        option-label="label"
                                    />
                                    <x-button :icon="theme_icon('delete')" class="btn-circle btn-ghost btn-sm text-error" wire:click="removeStepFormField({{ $i }}, {{ $fi }})" />
                                </div>
                            @endforeach

                            <x-button label="افزودن فیلد فرم" :icon="theme_icon('add')" class="btn-ghost btn-sm" wire:click="addStepFormField({{ $i }})" />
                        </div>
                    @endif

                    @if($step['step_type'] === 'condition')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                            <div>
                                <x-select
                                    label="فیلد شرط"
                                    wire:model.live="steps.{{ $i }}.condition_field"
                                    :options="$this->conditionFieldOptions"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                                @if($step['condition_field'] !== '' && isset($this->conditionFieldHints[$step['condition_field']]))
                                    <p class="text-xs text-base-content/60 mt-1">{{ $this->conditionFieldHints[$step['condition_field']] }}</p>
                                @endif
                            </div>
                            <x-select
                                label="عملگر"
                                wire:model="steps.{{ $i }}.condition_operator"
                                :options="$this->conditionOperatorOptions"
                                option-value="value"
                                option-label="label"
                                placeholder="انتخاب کنید"
                            />
                            <x-input label="مقدار مقایسه" wire:model="steps.{{ $i }}.condition_value" />
                        </div>
                    @endif

                    @if($step['step_type'] !== 'start')
                        <div class="mt-3 text-end">
                            <x-button :icon="theme_icon('delete')" label="حذف مرحله" class="btn-ghost btn-sm text-error" wire:click="removeStep({{ $i }})" />
                        </div>
                    @endif
                </div>
            @endforeach

            <x-button label="افزودن مرحله" :icon="theme_icon('add')" class="btn-primary btn-sm" wire:click="addStep" />
        </x-card>

        <div class="flex justify-between mt-4">
            <x-button label="مرحله قبل" class="btn-ghost" wire:click="prevStep" />
            <x-button label="مرحله بعد" :icon="theme_icon('next')" class="btn-primary" wire:click="nextStep" spinner="nextStep" />
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- مرحله ۴ — گذارها --}}
    {{-- ============================================================ --}}
    @if($currentStep === 4)
        <x-card title="۴. گذارها" subtitle="برای هر مرحله مشخص کنید نتیجه‌ی هر حالت به کدام مرحله می‌رود — با دستگیره‌ی کنار هر ردیف می‌توانید فقط ترتیب نمایش را جابه‌جا کنید" shadow>
            <div
                class="flex flex-col gap-3"
                wire:key="transition-order-list"
                x-init="window.initProcessTransitionSortable($el, {
                    onDrop: (stepKey, index) => $wire.moveTransitionRow(stepKey, index),
                })"
            >
                @foreach($this->orderedTransitionSteps as $step)
                    <div
                        class="border border-base-300 rounded-lg p-3"
                        data-step-key="{{ $step['step_key'] }}"
                        wire:key="transition-row-{{ $step['step_key'] }}"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <span class="pd-drag-handle cursor-grab text-base-content/40 transition hover:text-base-content/70 active:cursor-grabbing" title="جابه‌جایی ترتیب نمایش">
                                <x-icon :name="theme_icon('drag-handle')" class="h-4 w-4" />
                            </span>
                            <span class="font-bold">{{ $step['name'] ?: $step['step_key'] }}</span>
                            <x-badge :value="\App\Modules\Process\Enums\StepType::from($step['step_type'])->label()" class="badge-ghost badge-sm" />
                        </div>

                        @if($step['step_type'] === 'start')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <x-select
                                    label="مرحله‌ی بعد"
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.next"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                            </div>
                        @elseif($step['step_type'] === 'approval')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <x-select
                                    label="اگر تأیید شد"
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.approved"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                                <x-select
                                    label="اگر رد شد"
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.rejected"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                            </div>
                        @elseif($step['step_type'] === 'condition')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <x-select
                                    label="اگر شرط درست بود"
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.true"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                                <x-select
                                    label="اگر نادرست بود"
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.false"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                            </div>
                        @elseif($step['step_type'] === 'requester_input')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <x-select
                                    label="بعد از ارسال، برو به مرحله..."
                                    wire:model="transitionSelections.{{ $step['step_key'] }}.default"
                                    :options="$this->selectableSteps"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                />
                            </div>
                        @endif
                    </div>
                @endforeach

                @if(empty($this->orderedTransitionSteps))
                    <div class="rounded-box border border-dashed border-base-300 p-4 text-center text-xs text-base-content/40">
                        هیچ مرحله‌ای نیاز به تعریف گذار ندارد.
                    </div>
                @endif
            </div>
        </x-card>

        <div class="flex justify-between mt-4">
            <x-button label="مرحله قبل" class="btn-ghost" wire:click="prevStep" />
            <x-button label="مرحله بعد" :icon="theme_icon('next')" class="btn-primary" wire:click="nextStep" spinner="nextStep" />
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- مرحله ۵ — بازبینی و ذخیره --}}
    {{-- ============================================================ --}}
    @if($currentStep === 5)
        <x-card title="۵. بازبینی و ذخیره" subtitle="پیش از ساخت نهایی، کل گراف را مرور کنید" shadow>
            <div class="flex flex-wrap gap-4 mb-4">
                <div>
                    <div class="text-xs text-base-content/60">نام فرایند</div>
                    <div class="font-bold">{{ $name ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-base-content/60">نوع</div>
                    <div class="font-bold">
                        {{ $subjectType === '' ? 'فرایند آزاد' : (collect($this->subjectTypeOptions)->firstWhere('value', $subjectType)['label'] ?? $subjectType) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-base-content/60">وضعیت</div>
                    <x-badge :value="$isActive ? 'فعال' : 'غیرفعال'" class="{{ $isActive ? 'badge-success' : 'badge-ghost' }}" />
                </div>
            </div>

            @if($subjectType === '' && ! empty($requestFormFields))
                <div class="mb-4">
                    <div class="text-xs text-base-content/60 mb-1">فیلدهای فرم درخواست</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($requestFormFields as $field)
                            <x-badge :value="$field['label']" class="badge-outline" />
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="divider">مسیر گراف</div>

            <ul class="flex flex-col gap-2">
                <li class="font-bold">شروع</li>
                @foreach($this->reviewFlow as $line)
                    <li class="border-s-2 border-primary/30 ps-3">
                        <span class="font-semibold">{{ $line['label'] }}</span>
                        <span class="text-xs text-base-content/60">({{ $line['type'] }})</span>
                        <ul class="ps-4 mt-1 flex flex-col gap-1">
                            @foreach($line['targets'] as $target)
                                <li class="text-sm text-base-content/70">{{ $target['label'] }} ← {{ $target['to_label'] }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
                @foreach($this->reviewEndSteps as $endStep)
                    <li class="font-bold">{{ $endStep['name'] ?: $endStep['step_key'] }} (پایان)</li>
                @endforeach
            </ul>
        </x-card>

        <div class="flex justify-between mt-4">
            <x-button label="مرحله قبل" class="btn-ghost" wire:click="prevStep" />
            <x-button
                label="{{ $record ? 'ذخیره تغییرات' : 'ساخت فرایند' }}"
                type="button"
                class="btn-primary"
                :icon="theme_icon('save')"
                wire:click="save"
                spinner="save"
            />
        </div>
    @endif

    @endif
</div>
