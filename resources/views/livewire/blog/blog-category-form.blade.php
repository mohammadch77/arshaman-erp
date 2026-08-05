<div>
    <x-header
        title="{{ $record ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید' }}"
        subtitle="اطلاعات دسته‌بندی محتوای وبلاگ"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام دسته‌بندی" wire:model.live="name" :icon="theme_icon('category')" required />

            <x-input label="اسلاگ" wire:model="slug" :icon="theme_icon('link-account')" required hint="در آدرس صفحه استفاده می‌شود، فقط حروف/اعداد انگلیسی و خط تیره" />

            <x-textarea label="توضیحات" wire:model="description" rows="3" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('blog.categories.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت دسته‌بندی' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
