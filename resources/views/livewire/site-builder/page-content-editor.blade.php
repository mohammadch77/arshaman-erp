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

                @include('livewire.site-builder.partials.widget-add-panel', [
                    'quickAddWidgets' => $this->quickAddWidgets,
                    'activeContainerId' => $activeContainerId,
                    'activeContainerLabel' => $this->activeContainerLabel,
                    'canEdit' => $this->canEditWidgetValues,
                ])

                @include('livewire.site-builder.partials.widget-tree', ['nodes' => $this->widgetTreeUi, 'canEdit' => $this->canEditWidgetValues, 'activeContainerId' => $activeContainerId])

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
