<div>
    <x-header title="دسته‌بندی‌های وبلاگ" subtitle="فهرست دسته‌بندی‌های محتوای وبلاگ" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام دسته‌بندی..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="دسته‌بندی جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('blog.categories.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'نام'],
                ['key' => 'slug', 'label' => 'اسلاگ'],
                ['key' => 'description', 'label' => 'توضیحات'],
            ]"
            :rows="$categories"
            with-pagination
        >
            @scope('cell_description', $category)
                {{ $category->description ?? '—' }}
            @endscope

            @scope('actions', $category)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('blog.categories.edit', $category->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
