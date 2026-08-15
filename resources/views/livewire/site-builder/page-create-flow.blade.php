<div
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
>
    <x-header title="صفحه جدید" subtitle="یک دموی آماده را انتخاب کن، محتوایش را همین‌جا پر کن، بعد ذخیره کن" separator />

    {{-- گام ۱ — گالری دموها --}}
    <div class="flex flex-col gap-8">
        @forelse($this->categories as $category)
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <x-icon :name="theme_icon('category')" class="text-primary/70 w-5 h-5" />
                    <h3 class="text-lg font-semibold">{{ $category->name }}</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($category->demos as $demo)
                        <x-card
                            shadow
                            wire:key="demo-{{ $demo->id }}"
                            wire:click="selectDemo('{{ $demo->id }}')"
                            class="group cursor-pointer transition hover:-translate-y-0.5 hover:shadow-lg {{ $selectedDemoId === $demo->id ? 'ring-2 ring-primary' : '' }}"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-primary-content">
                                    <x-icon :name="theme_icon('template')" class="w-5 h-5" />
                                </span>
                                <span class="font-medium leading-snug">{{ $demo->name }}</span>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>
        @empty
            <x-alert title="هنوز هیچ دمویی ساخته نشده است." :icon="theme_icon('warning')" class="alert-warning" />
        @endforelse
    </div>

    {{-- گام ۲ — پر کردن مقادیر + پیش‌نمایش زنده، فقط بعد از انتخاب دمو --}}
    @if($selectedDemoId)
        <x-form wire:submit="create" class="gap-6 mt-8">
            <div class="flex items-center justify-between border-t border-base-300 pt-6">
                <div class="flex items-center gap-2 text-base-content/70">
                    <x-icon :name="theme_icon('edit')" class="w-5 h-5" />
                    <span class="font-medium">محتوای دمو را پر کن</span>
                </div>
                <x-button label="انتخاب دموی دیگر" wire:click="backToDemoSelection" :icon="theme_icon('back')" class="btn-ghost btn-sm" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="flex flex-col gap-5">
                    <x-card shadow>
                        <x-slot:title>
                            <span class="inline-flex items-center gap-2">
                                <x-icon :name="theme_icon('page')" class="w-4 h-4 text-base-content/60" />
                                اطلاعات صفحه
                            </span>
                        </x-slot:title>
                        <x-input label="عنوان صفحه" wire:model.live="title" :icon="theme_icon('page')" required />
                        <x-input label="نشانی صفحه (slug)" wire:model.live="slug" :icon="theme_icon('link-account')" required />
                        <x-input label="عنوان متا (سئو، اختیاری)" wire:model="meta_title" />
                        <x-textarea label="توضیح متا (سئو، اختیاری)" wire:model="meta_description" rows="2" />
                    </x-card>

                    @include('livewire.site-builder.partials.widget-tree', ['nodes' => $this->widgetTreeUi, 'canEdit' => true])
                </div>

                <div class="lg:sticky lg:top-4">
                    <x-card shadow title="پیش‌نمایش زنده">
                        <x-slot:menu>
                            <x-badge value="بدون ذخیره" class="badge-ghost" />
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
                <x-button label="ذخیره و انتشار پیش‌نویس" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="create" />
            </x-slot:actions>
        </x-form>
    @endif
</div>
