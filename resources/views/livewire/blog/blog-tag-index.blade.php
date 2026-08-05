<div>
    <x-header title="برچسب‌های وبلاگ" subtitle="فهرست برچسب‌های محتوای وبلاگ" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام برچسب..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="برچسب جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('blog.tags.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'نام'],
                ['key' => 'slug', 'label' => 'اسلاگ'],
            ]"
            :rows="$tags"
            with-pagination
        >
            @scope('actions', $tag)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('blog.tags.edit', $tag->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
