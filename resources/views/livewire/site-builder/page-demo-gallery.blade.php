<div>
    <x-header title="صفحه جدید" subtitle="یک دموی آماده را انتخاب کن، محتوایش را بعداً پر می‌کنی" separator />

    <div class="flex flex-col gap-6">
        @forelse($this->categories as $category)
            <div>
                <h3 class="mb-3 text-lg font-semibold">{{ $category->name }}</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($category->demos as $demo)
                        <x-card
                            shadow
                            class="cursor-pointer transition {{ $selectedDemoId === $demo->id ? 'ring-2 ring-primary' : '' }}"
                            wire:click="selectDemo('{{ $demo->id }}')"
                        >
                            <div class="flex items-center gap-3">
                                <x-icon :name="theme_icon('template')" class="text-primary" />
                                <span class="font-medium">{{ $demo->name }}</span>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>
        @empty
            <x-alert title="هنوز هیچ دمویی ساخته نشده است." :icon="theme_icon('warning')" class="alert-warning" />
        @endforelse
    </div>

    @if($selectedDemoId)
        <x-card shadow class="mt-6 max-w-xl">
            <x-form wire:submit="create" class="gap-5">
                <x-input label="عنوان صفحه" wire:model.live="title" :icon="theme_icon('page')" required />
                <x-input label="نشانی صفحه (slug)" wire:model.live="slug" :icon="theme_icon('link-account')" required />

                <x-slot:actions>
                    <x-button label="انصراف" link="{{ route('sitebuilder.pages.index') }}" class="btn-ghost" />
                    <x-button label="ساخت صفحه" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="create" />
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif
</div>
