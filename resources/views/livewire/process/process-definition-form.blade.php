<div>
    <x-header
        title="{{ $record ? 'ویرایش فرایند' : 'فرایند جدید' }}"
        subtitle="فهرست مراحل و گذارهای خروجی هر مرحله را مشخص کنید"
        separator
    />

    @if($this->hasHistory)
        <x-alert
            title="این فرایند سابقه‌ی اجرا دارد"
            description="چون حداقل یک بار اجرا شده (در جریان یا تمام‌شده)، ساختار مراحل/گذارها دیگر قابل‌ویرایش نیست — تاریخچه‌ی واقعی نباید زیر پایش عوض شود. فقط نام و وضعیت فعال/غیرفعال قابل‌تغییر است. برای تغییر واقعی گردش‌کار، یک فرایند جدید بسازید و این را غیرفعال کنید."
            :icon="theme_icon('locked')"
            class="alert-warning mb-4"
        />
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

    <x-form wire:submit="save" class="gap-5">
        <x-card title="اطلاعات پایه" shadow>
            <x-input label="نام فرایند" wire:model="name" :icon="theme_icon('process')" required />

            <x-select
                label="نوع"
                wire:model.live="subjectType"
                :options="array_merge([['value' => '', 'label' => 'فرایند آزاد (بدون اتصال به ماژول)']], $this->subjectTypeOptions)"
                option-value="value"
                option-label="label"
                :disabled="$this->hasHistory"
            />

            <x-checkbox label="فعال" wire:model="isActive" hint="غیرفعال یعنی فرایندهای تازه از این تعریف شروع نمی‌شوند" />
        </x-card>

        @if($subjectType === '')
            <x-card title="فرم درخواست (فرایند آزاد)" subtitle="فیلدهایی که درخواست‌دهنده هنگام شروع فرایند پر می‌کند" shadow>
                @foreach($requestFormFields as $i => $field)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                        <x-input label="برچسب" wire:model="requestFormFields.{{ $i }}.label" :disabled="$this->hasHistory" />
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
                            :disabled="$this->hasHistory"
                        />
                        @if(! $this->hasHistory)
                            <x-button :icon="theme_icon('delete')" class="btn-circle btn-ghost btn-sm text-error" wire:click="removeRequestField({{ $i }})" />
                        @endif
                    </div>
                @endforeach

                @if(! $this->hasHistory)
                    <x-button label="افزودن فیلد" :icon="theme_icon('add')" class="btn-ghost btn-sm" wire:click="addRequestField" />
                @endif
            </x-card>
        @endif

        <x-card title="مراحل" subtitle="هر فرایند دقیقاً یک مرحله‌ی شروع و حداقل یک مرحله‌ی پایان لازم دارد" shadow>
            @foreach($steps as $i => $step)
                <div class="border border-base-300 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <x-input label="نام مرحله" wire:model="steps.{{ $i }}.name" :disabled="$this->hasHistory" />

                        <x-select
                            label="نوع مرحله"
                            wire:model.live="steps.{{ $i }}.step_type"
                            :options="$this->stepTypeOptions"
                            option-value="value"
                            option-label="label"
                            :disabled="$this->hasHistory"
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
                                :disabled="$this->hasHistory"
                            />

                            @if($step['assignment_type'] === 'role')
                                <x-select
                                    label="نقش"
                                    wire:model="steps.{{ $i }}.assigned_role"
                                    :options="\App\Livewire\Process\ProcessDefinitionForm::ROLE_OPTIONS"
                                    option-value="value"
                                    option-label="label"
                                    placeholder="انتخاب کنید"
                                    :disabled="$this->hasHistory"
                                />
                            @elseif($step['assignment_type'] === 'specific_user')
                                <x-select
                                    label="کاربر"
                                    wire:model="steps.{{ $i }}.assigned_user_id"
                                    :options="$this->companyUsers"
                                    option-value="id"
                                    option-label="full_name"
                                    placeholder="انتخاب کنید"
                                    :disabled="$this->hasHistory"
                                />
                            @endif
                        </div>

                        <div class="mt-3 border-t border-base-300 pt-3" x-data="{ open: {{ ! empty($step['step_form_fields']) ? 'true' : 'false' }} }">
                            <x-checkbox
                                label="این مرحله فرم اضافه دارد؟"
                                hint="فیلدهایی که مسئول این مرحله هنگام تأیید/رد پر می‌کند — کاملاً اختیاری"
                                x-model="open"
                                :disabled="$this->hasHistory"
                            />

                            <div x-show="open" x-cloak class="mt-2">
                                @foreach($step['step_form_fields'] ?? [] as $fi => $stepField)
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                                        <x-input label="برچسب فیلد" wire:model="steps.{{ $i }}.step_form_fields.{{ $fi }}.label" :disabled="$this->hasHistory" />
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
                                            :disabled="$this->hasHistory"
                                        />
                                        @if(! $this->hasHistory)
                                            <x-button :icon="theme_icon('delete')" class="btn-circle btn-ghost btn-sm text-error" wire:click="removeStepFormField({{ $i }}, {{ $fi }})" />
                                        @endif
                                    </div>
                                @endforeach

                                @if(! $this->hasHistory)
                                    <x-button label="افزودن فیلد فرم مرحله" :icon="theme_icon('add')" class="btn-ghost btn-sm" wire:click="addStepFormField({{ $i }})" />
                                @endif
                            </div>
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
                                    :disabled="$this->hasHistory"
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
                                :disabled="$this->hasHistory"
                            />
                            <x-input label="مقدار مقایسه" wire:model="steps.{{ $i }}.condition_value" :disabled="$this->hasHistory" />
                        </div>
                    @endif

                    @if(! $this->hasHistory && $step['step_type'] !== 'start')
                        <div class="mt-3 text-end">
                            <x-button :icon="theme_icon('delete')" label="حذف مرحله" class="btn-ghost btn-sm text-error" wire:click="removeStep({{ $i }})" />
                        </div>
                    @endif
                </div>
            @endforeach

            @if(! $this->hasHistory)
                <x-button label="افزودن مرحله" :icon="theme_icon('add')" class="btn-primary btn-sm" wire:click="addStep" />
            @endif
        </x-card>

        <x-card title="گذارها" subtitle="برای هر مرحله مشخص کنید نتیجه‌ی هر حالت به کدام مرحله می‌رود" shadow>
            @foreach($steps as $step)
                @if($step['step_type'] === 'start')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                        <div class="self-center font-bold">{{ $step['name'] ?: $step['step_key'] }} (شروع)</div>
                        <x-select
                            label="مرحله‌ی بعد"
                            wire:model="transitionSelections.{{ $step['step_key'] }}.next"
                            :options="$this->selectableSteps"
                            option-value="value"
                            option-label="label"
                            placeholder="انتخاب کنید"
                            :disabled="$this->hasHistory"
                        />
                    </div>
                @elseif($step['step_type'] === 'approval')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                        <div class="self-center font-bold">{{ $step['name'] ?: $step['step_key'] }} (تأیید)</div>
                        <x-select
                            label="اگر تأیید شد"
                            wire:model="transitionSelections.{{ $step['step_key'] }}.approved"
                            :options="$this->selectableSteps"
                            option-value="value"
                            option-label="label"
                            placeholder="انتخاب کنید"
                            :disabled="$this->hasHistory"
                        />
                        <x-select
                            label="اگر رد شد"
                            wire:model="transitionSelections.{{ $step['step_key'] }}.rejected"
                            :options="$this->selectableSteps"
                            option-value="value"
                            option-label="label"
                            placeholder="انتخاب کنید"
                            :disabled="$this->hasHistory"
                        />
                    </div>
                @elseif($step['step_type'] === 'condition')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end border-b border-base-300 pb-3 mb-3">
                        <div class="self-center font-bold">{{ $step['name'] ?: $step['step_key'] }} (شرط)</div>
                        <x-select
                            label="اگر شرط درست بود"
                            wire:model="transitionSelections.{{ $step['step_key'] }}.true"
                            :options="$this->selectableSteps"
                            option-value="value"
                            option-label="label"
                            placeholder="انتخاب کنید"
                            :disabled="$this->hasHistory"
                        />
                        <x-select
                            label="اگر نادرست بود"
                            wire:model="transitionSelections.{{ $step['step_key'] }}.false"
                            :options="$this->selectableSteps"
                            option-value="value"
                            option-label="label"
                            placeholder="انتخاب کنید"
                            :disabled="$this->hasHistory"
                        />
                    </div>
                @endif
            @endforeach
        </x-card>

        <x-slot:actions>
            <x-button label="انصراف" link="{{ route('processes.index') }}" class="btn-ghost" />
            <x-button label="{{ $record ? 'ذخیره تغییرات' : 'ساخت فرایند' }}" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
