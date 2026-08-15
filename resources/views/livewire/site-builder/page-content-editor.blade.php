<div>
    <x-header title="{{ $record->title }}" subtitle="محتوای صفحه — فقط مقادیر را پر کن، ساختار از دمو ثابت است" separator>
        <x-slot:actions>
            <x-badge value="{{ $record->demo->category->name }}" class="badge-ghost" />
        </x-slot:actions>
    </x-header>

    @if(! $this->canEditWidgetValues)
        <x-alert title="این صفحه منتشرشده است — فقط holding_admin می‌تواند محتوای ویجت‌ها را ویرایش کند." :icon="theme_icon('warning')" class="alert-warning mb-4" />
    @endif

    <x-form
        wire:submit="save"
        x-data="{
            previewTimer: null,
            previewInFlight: null,
            schedulePreview() {
                clearTimeout(this.previewTimer);
                this.previewTimer = setTimeout(() => {
                    this.previewInFlight = $wire.refreshPreview().finally(() => { this.previewInFlight = null; });
                }, 500);
            },
        }"
        x-on:livewire-upload-finish.window="schedulePreview()"
        class="gap-6"
    >
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <div class="flex flex-col gap-5">
        <div class="flex items-center gap-2 text-base-content/70">
            <x-icon :name="theme_icon('edit')" class="w-5 h-5" />
            <span class="font-medium">محتوای ویجت‌ها</span>
        </div>

                @forelse($this->editableNodes as $node)
                    <x-card shadow>
                        <x-slot:title>
                            <span class="text-sm font-semibold text-base-content/70">{{ $node['section_label'] }}</span>
                        </x-slot:title>

                        <div class="flex flex-col gap-4">
                        @foreach($node['fields'] as $field)
                            @if($field['type'] === 'image')
                                {{-- <x-file> فقط وقتی slot دارد که واقعاً یک <img> داخلش باشد؛ اگر
                                     خالی (حتی فقط فاصله/خط‌جدید) باشد Illuminate\View\ComponentSlot::isEmpty()
                                     رشته را trim نمی‌کند و آن را «پر» می‌داند، پس input واقعی type=file
                                     مخفی می‌شود و هیچ چیز قابل‌کلیک باقی نمی‌ماند. برای همین دو حالت کاملاً جدا نوشته شده. --}}
                                @if(! empty($node['values'][$field['key']]))
                                    <x-file
                                        label="{{ $field['label'] }}"
                                        wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}"
                                        accept="image/*"
                                        :disabled="! $this->canEditWidgetValues"
                                    ><img src="{{ \Illuminate\Support\Facades\Storage::url($node['values'][$field['key']]) }}" class="mt-2 h-24 rounded object-cover" /></x-file>
                                @else
                                    <x-file
                                        label="{{ $field['label'] }}"
                                        wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}"
                                        accept="image/*"
                                        :disabled="! $this->canEditWidgetValues"
                                    />
                                @endif
                            @elseif($field['type'] === 'select')
                                <x-select
                                    label="{{ $field['label'] }}"
                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
                                    x-on:change="schedulePreview()"
                                    :options="$field['options']"
                                    option-value="value"
                                    option-label="label"
                                    :disabled="! $this->canEditWidgetValues"
                                />
                            @elseif($field['type'] === 'textarea')
                                <x-textarea
                                    label="{{ $field['label'] }}"
                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
                                    x-on:input="schedulePreview()"
                                    rows="3"
                                    :disabled="! $this->canEditWidgetValues"
                                />
                            @elseif($field['type'] === 'lines')
                                <x-textarea
                                    label="{{ $field['label'] }}"
                                    wire:model="linesRaw.{{ $node['id'] }}.{{ $field['key'] }}"
                                    x-on:input="schedulePreview()"
                                    rows="4"
                                    :disabled="! $this->canEditWidgetValues"
                                />
                            @elseif($field['type'] === 'repeater')
                                <div class="rounded-box border border-base-300 p-3">
                                    <p class="mb-2 text-sm font-medium text-base-content/70">{{ $field['label'] }}</p>

                                    <div class="flex flex-col gap-3">
                                        @foreach(($this->fieldValues[$node['id']][$field['key']] ?? []) as $rowIndex => $row)
                                            <div class="flex flex-col gap-2 rounded-box bg-base-200/50 p-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="grid flex-1 gap-2">
                                                        @foreach($field['item_fields'] as $itemField)
                                                            @if($itemField['type'] === 'image')
                                                                @if(! empty($row[$itemField['key']]))
                                                                    <x-file
                                                                        label="{{ $itemField['label'] }}"
                                                                        wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                                        accept="image/*"
                                                                        :disabled="! $this->canEditWidgetValues"
                                                                    ><img src="{{ \Illuminate\Support\Facades\Storage::url($row[$itemField['key']]) }}" class="mt-2 h-16 rounded object-cover" /></x-file>
                                                                @else
                                                                    <x-file
                                                                        label="{{ $itemField['label'] }}"
                                                                        wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                                        accept="image/*"
                                                                        :disabled="! $this->canEditWidgetValues"
                                                                    />
                                                                @endif
                                                            @elseif($itemField['type'] === 'textarea')
                                                                <x-textarea
                                                                    label="{{ $itemField['label'] }}"
                                                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                                    x-on:input="schedulePreview()"
                                                                    rows="2"
                                                                    :disabled="! $this->canEditWidgetValues"
                                                                />
                                                            @elseif($itemField['type'] === 'select')
                                                                <x-select
                                                                    label="{{ $itemField['label'] }}"
                                                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                                    x-on:change="schedulePreview()"
                                                                    :options="$itemField['options']"
                                                                    option-value="value"
                                                                    option-label="label"
                                                                    :disabled="! $this->canEditWidgetValues"
                                                                />
                                                            @else
                                                                <x-input
                                                                    label="{{ $itemField['label'] }}"
                                                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                                    x-on:input="schedulePreview()"
                                                                    :disabled="! $this->canEditWidgetValues"
                                                                />
                                                            @endif
                                                        @endforeach
                                                    </div>

                                                    @if($this->canEditWidgetValues)
                                                        <x-button
                                                            :icon="theme_icon('delete')"
                                                            wire:click="removeRepeaterRow('{{ $node['id'] }}', '{{ $field['key'] }}', {{ $rowIndex }})"
                                                            class="btn-circle btn-ghost btn-sm"
                                                        />
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if($this->canEditWidgetValues)
                                        <x-button
                                            label="افزودن ردیف"
                                            :icon="theme_icon('add')"
                                            wire:click="addRepeaterRow('{{ $node['id'] }}', '{{ $field['key'] }}', {{ json_encode($field['item_fields']) }})"
                                            class="btn-ghost btn-sm mt-3"
                                        />
                                    @endif
                                </div>
                            @else
                                <x-input
                                    label="{{ $field['label'] }}"
                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
                                    x-on:input="schedulePreview()"
                                    :disabled="! $this->canEditWidgetValues"
                                />
                            @endif
                        @endforeach
                        </div>
                    </x-card>
                @empty
                    <x-alert title="این دمو هیچ فیلد قابل‌ویرایشی ندارد." :icon="theme_icon('warning')" class="alert-warning" />
                @endforelse

        <x-card shadow>
            <x-slot:title>
                <span class="inline-flex items-center gap-2">
                    <x-icon :name="theme_icon('settings')" class="w-4 h-4 text-base-content/60" />
                    کد اختصاصی صفحه
                </span>
            </x-slot:title>
            <x-textarea label="CSS اختصاصی" wire:model="extra_css" x-on:input="schedulePreview()" rows="4" />
            <x-textarea label="JS اختصاصی" wire:model="extra_js" rows="4" />
        </x-card>

        <x-card shadow>
            <x-slot:title>
                <span class="inline-flex items-center gap-2">
                    <x-icon :name="theme_icon('send')" class="w-4 h-4 text-base-content/60" />
                    وضعیت انتشار
                </span>
            </x-slot:title>
            <x-select
                label="وضعیت"
                wire:model="page_status"
                :options="collect(\App\Modules\SiteBuilder\Enums\PageStatus::cases())->map(fn($case) => ['id' => $case->value, 'name' => $case->label()])"
                option-value="id"
                option-label="name"
                :disabled="! $this->canPublish"
            />
        </x-card>
        </div>

        <div class="lg:sticky lg:top-4">
            <x-card shadow title="پیش‌نمایش زنده">
                <x-slot:menu>
                    <x-badge value="{{ $this->canEditWidgetValues ? 'بدون ذخیره' : 'فقط‌خواندنی' }}" class="badge-ghost" />
                </x-slot:menu>

                <iframe
                    srcdoc="{{ $this->previewDocument }}"
                    sandbox="allow-same-origin"
                    title="پیش‌نمایش صفحه"
                    class="w-full rounded-box border border-base-300"
                    style="height: 75vh;"
                ></iframe>
            </x-card>
        </div>
      </div>

        <x-slot:actions>
            <x-button label="انصراف" link="{{ route('sitebuilder.pages.index') }}" class="btn-ghost" />
            <x-button label="ذخیره" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
