<div>
    <x-header
        title="{{ $record ? 'ویرایش پست' : 'پست جدید' }}"
        subtitle="اطلاعات پست وبلاگ"
        separator
    />

    <x-card shadow class="max-w-3xl">
        <x-form
            x-data="{
                autosaveTimer: null,
                autosaveInFlight: null,
                isSubmitting: false,
                scheduleAutosave() {
                    // window.saveBlogEditor() (فراخوانی‌شده از submitForm) هم برای
                    // sync کردن محتوای نهایی، یک 'input' مصنوعی روی همین فیلد
                    // dispatch می‌کند — بدون این گارد، همان sync نهایی دوباره یک
                    // تایمر autosave تازه دوثانیه‌ای می‌ساخت که ممکن بود بعد از
                    // ریدایرکت موفق save() (کامپوننت از بین رفته) شلیک شود.
                    if (this.isSubmitting) {
                        return;
                    }

                    clearTimeout(this.autosaveTimer);
                    this.autosaveTimer = setTimeout(() => {
                        this.autosaveInFlight = $wire.runAutosave().finally(() => { this.autosaveInFlight = null; });
                    }, 2000);
                },
                async submitForm() {
                    this.isSubmitting = true;
                    clearTimeout(this.autosaveTimer);

                    if (this.autosaveInFlight) {
                        await this.autosaveInFlight;
                    }

                    await window.saveBlogEditor('{{ $editorId }}');

                    try {
                        await $wire.save();
                    } finally {
                        // اگر save() به خطای اعتبارسنجی برخورد (بدون ریدایرکت)، کاربر
                        // همچنان روی همین فرم می‌ماند و باید بتواند دوباره تایپ کند؛
                        // autosave نباید برای بقیه عمر صفحه غیرفعال بماند.
                        this.isSubmitting = false;
                    }
                },
            }"
            @submit.prevent="submitForm()"
            class="gap-5"
        >
            <x-input label="عنوان" wire:model="title" x-on:input="scheduleAutosave()" :icon="theme_icon('blog')" required />

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

            <div
                x-data="{
                    tagInput: '',
                    tags: $wire.entangle('tagNames'),
                    addTag() {
                        const value = this.tagInput.trim();

                        if (value !== '' && ! this.tags.includes(value)) {
                            this.tags.push(value);
                        }

                        this.tagInput = '';
                    },
                }"
            >
                <div class="fieldset-legend mb-1">
                    <x-icon :name="theme_icon('blog-tag')" class="h-4 w-4 inline-block align-text-top" />
                    برچسب‌ها
                </div>

                <div class="flex flex-wrap gap-2 mb-2" x-show="tags.length > 0">
                    <template x-for="(tag, index) in tags" :key="index">
                        <span class="badge badge-outline gap-1 py-3">
                            <span x-text="tag"></span>
                            <button type="button" @click="tags.splice(index, 1)" class="cursor-pointer">
                                <x-icon :name="theme_icon('clear')" class="h-3 w-3" />
                            </button>
                        </span>
                    </template>
                </div>

                <input
                    type="text"
                    x-model="tagInput"
                    @keydown.enter.prevent="addTag()"
                    @keydown.comma.prevent="addTag()"
                    class="input w-full"
                    placeholder="تایپ کنید و Enter یا کاما بزنید"
                />
            </div>

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
                    x-init="window.initBlogEditor('{{ $editorId }}', @js($initialContent), 'editor-content-input-{{ $editorId }}', '{{ route('blog.editor-image-upload') }}')"
                    class="rounded-box border border-base-300 bg-base-100 p-4"
                >
                    <div id="{{ $editorId }}"></div>
                </div>
                <input type="hidden" id="editor-content-input-{{ $editorId }}" wire:model="content" x-on:input="scheduleAutosave()" />
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
