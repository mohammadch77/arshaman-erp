<div>
    <x-header title="صفحات" subtitle="فهرست صفحات ساخته‌شده با سایت‌ساز" separator>
        <x-slot:actions>
            <x-button label="صفحه جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('sitebuilder.pages.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'title', 'label' => 'عنوان'],
                ['key' => 'category', 'label' => 'دسته'],
                ['key' => 'slug', 'label' => 'نشانی'],
                ['key' => 'page_status', 'label' => 'وضعیت'],
                ['key' => 'updated_at', 'label' => 'آخرین ویرایش'],
            ]"
            :rows="$pages"
            with-pagination
        >
            @scope('cell_category', $page)
                {{ $page->demo->category->name }}
            @endscope

            @scope('cell_page_status', $page)
                @if($page->page_status->value === 'published')
                    <x-badge value="منتشرشده" class="badge-success" />
                @else
                    <x-badge value="پیش‌نویس" class="badge-ghost" />
                @endif
            @endscope

            @scope('cell_updated_at', $page)
                {{ \App\Support\Jalali::toDisplayDateTime($page->updated_at) }}
            @endscope

            @scope('actions', $page)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش محتوا"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('sitebuilder.pages.edit', $page->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
