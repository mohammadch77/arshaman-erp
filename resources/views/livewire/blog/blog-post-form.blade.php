<div>
    <x-header
        title="{{ $record ? 'ویرایش پست' : 'پست جدید' }}"
        subtitle="اطلاعات پست وبلاگ"
        separator
    />

    <x-card shadow class="max-w-3xl">
        <x-form @submit.prevent="window.saveBlogEditor('{{ $editorId }}').then(() => $wire.save())" class="gap-5">
            <x-input label="عنوان" wire:model.live="title" :icon="theme_icon('blog')" required />

            <x-input label="اسلاگ" wire:model="slug" :icon="theme_icon('link-account')" required hint="در آدرس صفحه استفاده می‌شود، فقط حروف/اعداد انگلیسی و خط تیره" />

            <div>
                <x-input label="عنوان متا (سئو)" wire:model.live="meta_title" :icon="theme_icon('report')" />
                <div class="fieldset-label mt-1 {{ $this->metaTitleRemaining < 0 ? 'text-error' : '' }}">
                    {{ \App\Support\Farsi::toDigits($this->metaTitleRemaining) }} کاراکتر باقی‌مانده
                </div>
            </div>

            <div>
                <x-textarea label="توضیحات متا (سئو)" wire:model.live="meta_description" rows="2" />
                <div class="fieldset-label mt-1 {{ $this->metaDescriptionRemaining < 0 ? 'text-error' : '' }}">
                    {{ \App\Support\Farsi::toDigits($this->metaDescriptionRemaining) }} کاراکتر باقی‌مانده
                </div>
            </div>

            <x-select
                label="دسته‌بندی"
                wire:model="category_id"
                :options="$this->categoryOptions"
                option-value="id"
                option-label="name"
                placeholder="بدون دسته‌بندی"
                placeholder-value=""
            />

            <x-choices
                label="برچسب‌ها"
                wire:model="tag_ids"
                :options="$this->tagOptions"
                option-value="id"
                option-label="name"
                searchable
                :icon="theme_icon('blog-tag')"
            />

            <x-select
                label="نویسنده"
                wire:model="author_user_id"
                :options="$this->authorOptions"
                option-value="id"
                option-label="name"
                :disabled="! $this->canPublish"
                :icon="theme_icon('user')"
                required
            />

            <div>
                <x-file label="عکس شاخص" wire:model="featuredImage" accept="image/*" hint="حداکثر ۲ مگابایت" />
                @if($existingFeaturedImagePath && ! $featuredImage)
                    <img src="{{ $existingFeaturedImageUrl }}" class="mt-2 h-24 rounded-lg object-cover" alt="عکس شاخص فعلی" />
                @endif
            </div>

            @if($this->canPublish)
                <x-select
                    label="وضعیت"
                    wire:model.live="post_status"
                    :options="$this->postStatusOptions"
                    option-value="id"
                    option-label="name"
                    required
                />

                @if($post_status === \App\Modules\Blog\Enums\BlogPostStatus::Scheduled->value)
                    <x-jalali-date-select field="scheduled_date" label="تاریخ انتشار" required />
                    <x-input label="ساعت انتشار" wire:model="scheduled_time" type="time" required :icon="theme_icon('calendar')" />
                @endif
            @else
                <div>
                    <div class="fieldset-legend mb-0.5">وضعیت</div>
                    <x-badge value="پیش‌نویس" class="badge-ghost" :icon="theme_icon('note')" />
                    <div class="fieldset-label mt-1">فقط holding_admin می‌تواند پست را منتشر یا زمان‌بندی کند.</div>
                </div>
            @endif

            <div>
                <div class="fieldset-legend mb-1">محتوا</div>
                <div
                    wire:ignore
                    x-data
                    x-init="window.initBlogEditor('{{ $editorId }}', @js($initialBlocks), 'editor-content-input-{{ $editorId }}', '{{ route('blog.editor-image-upload') }}')"
                    class="rounded-box border border-base-300 bg-base-100 p-4"
                >
                    <div id="{{ $editorId }}"></div>
                </div>
                <input type="hidden" id="editor-content-input-{{ $editorId }}" wire:model="content" />
            </div>

            <x-input label="زمان مطالعه (دقیقه، اختیاری)" wire:model="reading_time_minutes" :icon="theme_icon('history')" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('blog.posts.index') }}" class="btn-ghost" />
                <x-button
                    label="{{ $record ? 'ذخیره تغییرات' : 'ساخت پست' }}"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
