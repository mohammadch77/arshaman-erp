<div>
    <x-header
        title="{{ $record ? 'ویرایش برچسب' : 'برچسب جدید' }}"
        subtitle="اطلاعات برچسب محتوای وبلاگ"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام برچسب" wire:model.live="name" :icon="theme_icon('blog-tag')" required />

            <x-input label="اسلاگ" wire:model="slug" :icon="theme_icon('link-account')" required hint="در آدرس صفحه استفاده می‌شود، فقط حروف/اعداد انگلیسی و خط تیره" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('blog.tags.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت برچسب' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
