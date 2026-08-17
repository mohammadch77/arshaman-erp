<div>
    <x-header title="کارهای من" subtitle="مراحل فرایندهایی که منتظر تصمیم شما هستند" separator />

    @if($this->reversibleDecisions->isNotEmpty())
        <x-card title="تصمیم‌های اخیر (قابل بازگردانی)" subtitle="چون هنوز هیچ اقدامی روی مرحله‌ی بعدی نرفته، می‌توانید تصمیمتان را تغییر دهید" shadow class="mb-4">
            <div class="flex flex-col gap-3">
                @foreach($this->reversibleDecisions as $row)
                    @php($instance = $row['instance'])
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-icon :name="theme_icon('process')" class="w-4 h-4 text-primary" />
                                <span class="font-medium">{{ $instance->definition->name }}</span>
                            </div>
                            <p class="text-xs text-base-content/60 mt-1">مرحله: {{ $instance->currentStep->name }}</p>
                        </div>

                        <x-button
                            label="بازگردانی تصمیم"
                            :icon="theme_icon('undo')"
                            class="btn-warning btn-sm"
                            wire:click="reverseDecision('{{ $instance->id }}')"
                            wire:confirm="تصمیم قبلی شما لغو می‌شود و این کار دوباره منتظر تصمیم شما می‌ماند. ادامه می‌دهید؟"
                            spinner="reverseDecision('{{ $instance->id }}')"
                        />
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    @if($this->tasks->isEmpty())
        <x-card shadow>
            <div class="flex flex-col items-center gap-2 py-10 text-base-content/60">
                <x-icon :name="theme_icon('inbox')" class="w-10 h-10" />
                <p>در حال حاضر هیچ کاری منتظر تصمیم شما نیست.</p>
            </div>
        </x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach($this->tasks as $task)
                @php($instance = $task['instance'])
                <x-card shadow>
                    @if($task['reminder'])
                        <x-alert
                            title="یادآوری از ادمین"
                            :description="$task['reminder']->comment"
                            :icon="theme_icon('reminder')"
                            class="alert-warning mb-3"
                        />
                    @endif

                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <x-icon :name="theme_icon('process')" class="w-5 h-5 text-primary" />
                                <span class="font-medium">{{ $instance->definition->name }}</span>
                                <span class="badge badge-ghost badge-sm">{{ $instance->currentStep->name }}</span>
                            </div>

                            <p class="text-xs text-base-content/60 mt-1">
                                شروع‌شده توسط {{ $instance->startedBy->full_name }}
                                در {{ \App\Support\Jalali::toDisplayDateTime($instance->started_at) }}
                            </p>

                            @if($task['summary'] !== [])
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 mt-3 text-sm">
                                    @foreach($task['summary'] as $item)
                                        <div class="flex gap-2">
                                            <dt class="text-base-content/60">{{ $item['label'] }}:</dt>
                                            <dd class="font-medium">{{ $item['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>

                        <div class="flex gap-2 shrink-0">
                            <x-button
                                :icon="theme_icon('history')"
                                tooltip-left="تاریخچه"
                                class="btn-circle btn-ghost btn-sm"
                                wire:click="openHistory('{{ $instance->id }}')"
                            />
                            <x-button
                                label="تأیید/رد"
                                :icon="theme_icon('approve')"
                                class="btn-primary btn-sm"
                                wire:click="openComment('{{ $instance->id }}')"
                            />
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="commentInstanceId" title="نظر (اختیاری)" separator>
        @if($this->commentStepFormFields !== [])
            <div class="flex flex-col gap-3 mb-3">
                @foreach($this->commentStepFormFields as $field)
                    @if($field['type'] === 'boolean')
                        <x-checkbox :label="$field['label']" wire:model="stepDataValues.{{ $field['key'] }}" />
                    @elseif($field['type'] === 'number')
                        <x-input type="number" :label="$field['label']" wire:model="stepDataValues.{{ $field['key'] }}" :icon="theme_icon('site-form')" />
                    @elseif($field['type'] === 'textarea')
                        <x-textarea :label="$field['label']" wire:model="stepDataValues.{{ $field['key'] }}" rows="3" />
                    @else
                        <x-input :label="$field['label']" wire:model="stepDataValues.{{ $field['key'] }}" :icon="theme_icon('site-form')" />
                    @endif
                @endforeach
            </div>
        @endif

        <x-textarea
            label="نظر شما"
            wire:model="comment"
            :icon="theme_icon('note')"
            rows="3"
            placeholder="در صورت تمایل توضیحی برای این تصمیم بنویسید..."
        />

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.commentInstanceId = null" />
            <x-button label="رد کردن" :icon="theme_icon('reject')" class="btn-error" wire:click="reject" spinner="reject" />
            <x-button label="تأیید کردن" :icon="theme_icon('approve')" class="btn-success" wire:click="approve" spinner="approve" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showHistoryModal" title="تاریخچه‌ی فرایند" separator>
        @if($this->history->isEmpty())
            <p class="text-base-content/60">هنوز هیچ رویدادی ثبت نشده است.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach($this->history as $event)
                    <li class="border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            <x-icon :name="theme_icon('history')" class="w-4 h-4 text-base-content/60" />
                            <span class="font-medium">{{ $event->step->name }}</span>
                            <span class="badge badge-ghost badge-sm">{{ $event->action->label() }}</span>
                            @if($event->reversed_at)
                                <span class="badge badge-warning badge-sm">بازگردانی‌شده</span>
                            @endif
                        </div>

                        <div class="text-sm mt-1 text-base-content/70">
                            {{ $event->actor?->full_name ?? 'خودکار (سیستم)' }}
                        </div>

                        @if($event->comment)
                            <p class="text-sm text-base-content/70 mt-1 whitespace-pre-line">{{ $event->comment }}</p>
                        @endif

                        @if($event->step_data)
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 mt-2 text-sm">
                                @foreach($event->step_data as $key => $value)
                                    @php($fieldLabel = collect($event->step->step_form_fields ?? [])->firstWhere('key', $key)['label'] ?? $key)
                                    <div class="flex gap-2">
                                        <dt class="text-base-content/60">{{ $fieldLabel }}:</dt>
                                        <dd class="font-medium">{{ is_bool($value) ? ($value ? 'بله' : 'خیر') : $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ \App\Support\Jalali::toDisplayDateTime($event->created_at) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
