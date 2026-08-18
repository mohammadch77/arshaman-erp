<div>
    <x-header title="درخواست جدید" subtitle="شروع یک فرایند آزاد (بدون اتصال به ماژول)" separator />

    @if($this->definitions->isEmpty())
        <x-card shadow>
            <div class="flex flex-col items-center gap-2 py-10 text-base-content/60">
                <x-icon :name="theme_icon('process')" class="w-10 h-10" />
                <p>در حال حاضر هیچ فرایند آزاد فعالی برای درخواست وجود ندارد.</p>
            </div>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-1">
                <x-card title="انتخاب فرایند" shadow>
                    <div class="flex flex-col gap-2">
                        @foreach($this->definitions as $definition)
                            <x-button
                                label="{{ $definition->name }}"
                                :icon="theme_icon('process')"
                                class="justify-start {{ $selectedDefinitionId === $definition->id ? 'btn-primary' : 'btn-ghost' }}"
                                wire:click="selectDefinition('{{ $definition->id }}')"
                            />
                        @endforeach
                    </div>
                </x-card>
            </div>

            <div class="md:col-span-2">
                @if($this->selectedDefinition === null)
                    <x-card shadow>
                        <p class="text-base-content/60 py-6 text-center">یک فرایند را از فهرست کنار انتخاب کنید.</p>
                    </x-card>
                @else
                    <x-form wire:submit="submit">
                        <x-card title="{{ $this->selectedDefinition->name }}" subtitle="فرم درخواست" shadow>
                            @php($fields = $this->selectedDefinition->request_form_fields ?? [])

                            @if($fields === [])
                                <p class="text-base-content/60">این فرایند فیلد درخواستی ندارد — همین که ارسال کنید کافی است.</p>
                            @else
                                <div class="flex flex-col gap-4">
                                    @foreach($fields as $field)
                                        @if($field['type'] === 'textarea')
                                            <x-textarea
                                                label="{{ $field['label'] }}"
                                                wire:model="formValues.{{ $field['key'] }}"
                                                rows="3"
                                            />
                                        @elseif($field['type'] === 'number')
                                            <x-input
                                                type="number"
                                                label="{{ $field['label'] }}"
                                                wire:model="formValues.{{ $field['key'] }}"
                                            />
                                        @elseif($field['type'] === 'boolean')
                                            <x-checkbox
                                                label="{{ $field['label'] }}"
                                                wire:model="formValues.{{ $field['key'] }}"
                                            />
                                        @elseif($field['type'] === 'file')
                                            <x-file
                                                label="{{ $field['label'] }}"
                                                wire:model="fileUploads.{{ $field['key'] }}"
                                                :icon="theme_icon('file')"
                                                hint="فرمت‌های مجاز: {{ implode('، ', config('processes.file_upload.allowed_extensions')) }} — حداکثر {{ round(config('processes.file_upload.max_kilobytes') / 1024, 1) }} مگابایت"
                                            />
                                        @else
                                            <x-input
                                                label="{{ $field['label'] }}"
                                                wire:model="formValues.{{ $field['key'] }}"
                                            />
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <x-slot:actions>
                                <x-button label="ارسال درخواست" :icon="theme_icon('send')" class="btn-primary" type="submit" spinner="submit" />
                            </x-slot:actions>
                        </x-card>
                    </x-form>
                @endif
            </div>
        </div>
    @endif
</div>
