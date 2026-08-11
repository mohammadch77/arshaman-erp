<div>
    <x-header title="{{ $record->title }}" subtitle="محتوای صفحه — فقط مقادیر را پر کن، ساختار از دمو ثابت است" separator>
        <x-slot:actions>
            <x-badge value="{{ $record->demo->category->name }}" class="badge-ghost" />
        </x-slot:actions>
    </x-header>

    @if(! $this->canEditWidgetValues)
        <x-alert title="این صفحه منتشرشده است — فقط holding_admin می‌تواند محتوای ویجت‌ها را ویرایش کند." :icon="theme_icon('warning')" class="alert-warning mb-4" />
    @endif

    <x-form wire:submit="save" class="gap-6">
        <x-card shadow title="محتوای ویجت‌ها">
            <div class="flex flex-col gap-5">
                @forelse($this->editableNodes as $node)
                    <div class="border-b border-base-300 pb-4 last:border-b-0 last:pb-0">
                        <p class="mb-2 text-sm font-semibold text-base-content/70">{{ $node['section_label'] }}</p>

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
                            @else
                                <x-input
                                    label="{{ $field['label'] }}"
                                    wire:model="fieldValues.{{ $node['id'] }}.{{ $field['key'] }}"
                                    :disabled="! $this->canEditWidgetValues"
                                />
                            @endif
                        @endforeach
                    </div>
                @empty
                    <x-alert title="این دمو هیچ فیلد قابل‌ویرایشی ندارد." :icon="theme_icon('warning')" class="alert-warning" />
                @endforelse
            </div>
        </x-card>

        <x-card shadow title="کد اختصاصی صفحه">
            <x-textarea label="CSS اختصاصی" wire:model="extra_css" rows="4" />
            <x-textarea label="JS اختصاصی" wire:model="extra_js" rows="4" />
        </x-card>

        <x-card shadow title="وضعیت انتشار">
            <x-select
                label="وضعیت"
                wire:model="page_status"
                :options="collect(\App\Modules\SiteBuilder\Enums\PageStatus::cases())->map(fn($case) => ['id' => $case->value, 'name' => $case->label()])"
                option-value="id"
                option-label="name"
                :disabled="! $this->canPublish"
            />
        </x-card>

        <x-slot:actions>
            <x-button label="انصراف" link="{{ route('sitebuilder.pages.index') }}" class="btn-ghost" />
            <x-button label="ذخیره" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
