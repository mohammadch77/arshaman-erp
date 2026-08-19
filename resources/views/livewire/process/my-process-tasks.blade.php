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
                                            @if($item['is_file'] && $item['value'] !== '')
                                                <dd>
                                                    <a href="{{ \App\Modules\Process\Support\ProcessFileUploader::url($item['value']) }}" target="_blank" class="link link-primary inline-flex items-center gap-1">
                                                        <x-icon :name="theme_icon('download')" class="w-4 h-4" />
                                                        {{ \App\Modules\Process\Support\ProcessFileUploader::originalNameFromPath($item['value']) }}
                                                    </a>
                                                </dd>
                                            @else
                                                <dd class="font-medium">{{ $item['value'] }}</dd>
                                            @endif
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
        @if($this->commentStepFormFields->isNotEmpty())
            <div class="flex flex-col gap-3 mb-3">
                @foreach($this->commentStepFormFields as $field)
                    @include('livewire.process.partials.form-field-input', ['field' => $field, 'valuePrefix' => 'stepDataValues', 'filePrefix' => 'fileUploads'])
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
        @include('livewire.process.partials.history-list', ['events' => $this->history])

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
