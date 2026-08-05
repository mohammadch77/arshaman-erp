<div>
    <x-header title="پست‌های وبلاگ" subtitle="فهرست محتوای وبلاگ" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی عنوان..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select
                wire:model.live="postStatus"
                :options="$this->postStatusOptions"
                option-value="id"
                option-label="name"
                placeholder="همه وضعیت‌ها"
                placeholder-value=""
            />
            <x-select
                wire:model.live="categoryId"
                :options="$this->categoryOptions"
                option-value="id"
                option-label="name"
                placeholder="همه دسته‌بندی‌ها"
                placeholder-value=""
            />
            <x-select
                wire:model.live="authorUserId"
                :options="$this->authorOptions"
                option-value="id"
                option-label="name"
                placeholder="همه نویسندگان"
                placeholder-value=""
            />
            <x-button label="پست جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('blog.posts.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'title', 'label' => 'عنوان'],
                ['key' => 'category', 'label' => 'دسته‌بندی'],
                ['key' => 'author', 'label' => 'نویسنده'],
                ['key' => 'post_status', 'label' => 'وضعیت'],
                ['key' => 'published_at', 'label' => 'تاریخ انتشار'],
            ]"
            :rows="$posts"
            with-pagination
        >
            @scope('cell_category', $post)
                {{ $post->category?->name ?? '—' }}
            @endscope

            @scope('cell_author', $post)
                {{ $post->author?->full_name ?? '—' }}
            @endscope

            @scope('cell_post_status', $post)
                <x-badge
                    :value="$post->post_status->label()"
                    class="{{ match ($post->post_status) {
                        \App\Modules\Blog\Enums\BlogPostStatus::Draft => 'badge-ghost',
                        \App\Modules\Blog\Enums\BlogPostStatus::Scheduled => 'badge-warning',
                        \App\Modules\Blog\Enums\BlogPostStatus::Published => 'badge-success',
                    } }}"
                />
            @endscope

            @scope('cell_published_at', $post)
                {{ \App\Support\Jalali::toDisplayDateTime($post->published_at) ?? '—' }}
            @endscope

            {{--
                @scope فقط متغیرهایی را که صریحاً به‌عنوان آرگومان اضافه پاس داده
                شوند capture می‌کند (نه کل scope بیرونی view را) — پس $activeCompanySlug
                باید همین‌جا صریح اضافه شود، وگرنه داخل closure این بلوک تعریف‌نشده است.
            --}}
            @scope('actions', $post, $activeCompanySlug)
                @can('view', $post)
                    <x-button
                        :icon="theme_icon('preview')"
                        tooltip-left="پیش‌نمایش"
                        class="btn-circle btn-ghost btn-sm"
                        link="{{ $post->post_status === \App\Modules\Blog\Enums\BlogPostStatus::Published && $activeCompanySlug ? route('public-blog.show', [$activeCompanySlug, $post->slug]) : route('blog.posts.preview', $post->id) }}"
                        target="_blank"
                    />
                @endcan

                @can('update', $post)
                    <x-button
                        :icon="theme_icon('edit')"
                        tooltip-left="ویرایش"
                        class="btn-circle btn-ghost btn-sm"
                        link="{{ route('blog.posts.edit', $post->id) }}"
                    />
                @endcan

                @can('delete', $post)
                    <x-button
                        :icon="theme_icon('delete')"
                        tooltip-left="حذف"
                        class="btn-circle btn-ghost btn-sm text-error"
                        wire:click="delete('{{ $post->id }}')"
                        wire:confirm="این پست حذف می‌شود. ادامه می‌دهید؟"
                        spinner="delete('{{ $post->id }}')"
                    />
                @endcan
            @endscope
        </x-table>
    </x-card>
</div>
