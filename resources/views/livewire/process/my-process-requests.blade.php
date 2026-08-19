<div>
    <x-header title="درخواست‌های من" subtitle="تاریخچه‌ی فرایندهایی که خودتان شروع کرده‌اید" separator>
        <x-slot:actions>
            <x-button label="درخواست جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('processes.request') }}" />
        </x-slot:actions>
    </x-header>

    @if($this->needsInput->isNotEmpty())
        <x-card title="نیاز به تکمیل اطلاعات شما" subtitle="فرایند منتظر شماست — این مرحله را فقط شما (فرستنده‌ی اصلی درخواست) می‌توانید تکمیل کنید" shadow class="mb-4">
            <div class="flex flex-col gap-3">
                @foreach($this->needsInput as $row)
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
                            label="تکمیل اطلاعات"
                            :icon="theme_icon('site-form')"
                            class="btn-primary btn-sm"
                            wire:click="openInputForm('{{ $instance->id }}')"
                        />
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    @if($this->requests->isEmpty())
        <x-card shadow>
            <div class="flex flex-col items-center gap-2 py-10 text-base-content/60">
                <x-icon :name="theme_icon('history')" class="w-10 h-10" />
                <p>هنوز هیچ درخواستی ثبت نکرده‌اید.</p>
            </div>
        </x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach($this->requests as $row)
                @php($instance = $row['instance'])
                @php($statusBadge = match ($instance->status) {
                    \App\Modules\Process\Enums\ProcessStatus::InProgress => 'badge-info',
                    \App\Modules\Process\Enums\ProcessStatus::Approved => 'badge-success',
                    \App\Modules\Process\Enums\ProcessStatus::Rejected => 'badge-error',
                    \App\Modules\Process\Enums\ProcessStatus::Cancelled => 'badge-ghost',
                })
                <x-card shadow>
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <x-icon :name="theme_icon('process')" class="w-5 h-5 text-primary" />
                                <span class="font-medium">{{ $instance->definition->name }}</span>
                                <span class="badge {{ $statusBadge }} badge-sm">{{ $instance->status->label() }}</span>
                            </div>

                            <p class="text-xs text-base-content/60 mt-1">
                                شروع در {{ \App\Support\Jalali::toDisplayDateTime($instance->started_at) }}
                                @if($instance->status === \App\Modules\Process\Enums\ProcessStatus::InProgress)
                                    — مرحله‌ی فعلی: {{ $instance->currentStep?->name }}
                                @endif
                            </p>

                            @if($row['summary'] !== [])
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 mt-3 text-sm">
                                    @foreach($row['summary'] as $item)
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
                            @if($row['can_edit'])
                                <x-button
                                    :icon="theme_icon('edit')"
                                    tooltip-left="ویرایش درخواست"
                                    class="btn-circle btn-ghost btn-sm"
                                    wire:click="openEditForm('{{ $instance->id }}')"
                                />
                            @endif
                            @if($row['can_cancel'])
                                <x-button
                                    :icon="theme_icon('cancel')"
                                    tooltip-left="لغو درخواست"
                                    class="btn-circle btn-ghost btn-sm text-error"
                                    wire:click="cancelInstance('{{ $instance->id }}')"
                                    wire:confirm="این درخواست لغو می‌شود و دیگر ادامه پیدا نمی‌کند. مطمئنید؟"
                                />
                            @endif
                            <x-button
                                :icon="theme_icon('history')"
                                tooltip-left="تاریخچه"
                                class="btn-circle btn-ghost btn-sm"
                                wire:click="openHistory('{{ $instance->id }}')"
                            />
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="showInputModal" title="تکمیل اطلاعات" separator>
        @if($this->inputStepFormFields->isEmpty())
            <p class="text-base-content/60">این مرحله فیلدی برای تکمیل ندارد — همین که ارسال کنید کافی است.</p>
        @else
            <div class="flex flex-col gap-4">
                @foreach($this->inputStepFormFields as $field)
                    @include('livewire.process.partials.form-field-input', ['field' => $field, 'valuePrefix' => 'inputStepDataValues', 'filePrefix' => 'inputFileUploads'])
                @endforeach
            </div>
        @endif

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showInputModal = false" />
            <x-button label="ارسال" :icon="theme_icon('send')" class="btn-primary" wire:click="submitInput" spinner="submitInput" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showEditModal" title="ویرایش درخواست" subtitle="فقط تا قبل از اقدام مسئول مرحله‌ی فعلی قابل‌ویرایش است" separator>
        @if($this->editFormFields->isEmpty())
            <p class="text-base-content/60">این فرایند فیلد درخواستی ندارد.</p>
        @else
            <div class="flex flex-col gap-4">
                @foreach($this->editFormFields as $field)
                    @if($field->field_type === 'file')
                        @php($existingPath = $this->editExistingFiles[$field->field_key] ?? null)
                        @if($existingPath)
                            <div class="text-sm">
                                <span class="text-base-content/60">فایل فعلی:</span>
                                <a href="{{ \App\Modules\Process\Support\ProcessFileUploader::url($existingPath) }}" target="_blank" class="link link-primary">
                                    {{ \App\Modules\Process\Support\ProcessFileUploader::originalNameFromPath($existingPath) }}
                                </a>
                            </div>
                        @endif
                        <x-file
                            label="{{ $field->label }} (برای تعویض، فایل جدید انتخاب کنید)"
                            wire:model="editFileUploads.{{ $field->field_key }}"
                            :icon="theme_icon('file')"
                            hint="فرمت‌های مجاز: {{ implode('، ', config('processes.file_upload.allowed_extensions')) }} — حداکثر {{ round(config('processes.file_upload.max_kilobytes') / 1024, 1) }} مگابایت"
                        />
                    @else
                        @include('livewire.process.partials.form-field-input', ['field' => $field, 'valuePrefix' => 'editFormValues', 'filePrefix' => 'editFileUploads'])
                    @endif
                @endforeach
            </div>
        @endif

        <x-slot:actions>
            <x-button label="انصراف" @click="$wire.showEditModal = false" />
            <x-button label="ذخیره تغییرات" :icon="theme_icon('save')" class="btn-primary" wire:click="saveEditRequest" spinner="saveEditRequest" />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="showHistoryModal" title="تاریخچه‌ی فرایند" separator>
        @include('livewire.process.partials.history-list', ['events' => $this->history])

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
