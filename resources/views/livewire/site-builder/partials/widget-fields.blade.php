{{--
    فرم فیلدهای قابل‌ویرایش یک نود — استخراج‌شده از PageContentEditor/PageCreateFlow
    (که هردو کاملاً یکسان بودند) تا هم در فرم مسطح قدیمی هم در کارت هر نود
    داخل درخت drag-and-drop (widget-tree-node) یک‌بار نوشته شود.

    متغیرهای ورودی:
    - $node: آیتمی از editableNodes()/treeNodesUi با کلیدهای id/fields/values
    - $canEdit: آیا این فیلدها ویرایش‌پذیرند (false روی صفحه‌ی published برای operator)
--}}
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
                :disabled="! $canEdit"
            ><img src="{{ \Illuminate\Support\Facades\Storage::url($node['values'][$field['key']]) }}" class="mt-2 h-24 rounded object-cover" /></x-file>
        @else
            <x-file
                label="{{ $field['label'] }}"
                wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}"
                accept="image/*"
                :disabled="! $canEdit"
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
            :disabled="! $canEdit"
        />
    @elseif($field['type'] === 'boolean')
        <x-checkbox
            label="{{ $field['label'] }}"
            wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
            x-on:change="schedulePreview()"
            :disabled="! $canEdit"
        />
    @elseif($field['type'] === 'textarea')
        <x-textarea
            label="{{ $field['label'] }}"
            wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
            x-on:input="schedulePreview()"
            rows="3"
            :disabled="! $canEdit"
        />
    @elseif($field['type'] === 'lines')
        <x-textarea
            label="{{ $field['label'] }}"
            wire:model="linesRaw.{{ $node['id'] }}.{{ $field['key'] }}"
            x-on:input="schedulePreview()"
            rows="4"
            :disabled="! $canEdit"
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
                                                :disabled="! $canEdit"
                                            ><img src="{{ \Illuminate\Support\Facades\Storage::url($row[$itemField['key']]) }}" class="mt-2 h-16 rounded object-cover" /></x-file>
                                        @else
                                            <x-file
                                                label="{{ $itemField['label'] }}"
                                                wire:model="imageUploads.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                                accept="image/*"
                                                :disabled="! $canEdit"
                                            />
                                        @endif
                                    @elseif($itemField['type'] === 'textarea')
                                        <x-textarea
                                            label="{{ $itemField['label'] }}"
                                            wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                            x-on:input="schedulePreview()"
                                            rows="2"
                                            :disabled="! $canEdit"
                                        />
                                    @elseif($itemField['type'] === 'select')
                                        <x-select
                                            label="{{ $itemField['label'] }}"
                                            wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                            x-on:change="schedulePreview()"
                                            :options="$itemField['options']"
                                            option-value="value"
                                            option-label="label"
                                            :disabled="! $canEdit"
                                        />
                                    @else
                                        <x-input
                                            label="{{ $itemField['label'] }}"
                                            wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}.{{ $rowIndex }}.{{ $itemField['key'] }}"
                                            x-on:input="schedulePreview()"
                                            :disabled="! $canEdit"
                                        />
                                    @endif
                                @endforeach
                            </div>

                            @if($canEdit)
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

            @if($canEdit)
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
            :disabled="! $canEdit"
        />
    @endif
@endforeach
