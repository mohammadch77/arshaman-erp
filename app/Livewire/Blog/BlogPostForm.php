<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Actions\AutosaveBlogPostDraft;
use App\Modules\Blog\Actions\CreateBlogPost;
use App\Modules\Blog\Actions\UpdateBlogPost;
use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Blog\Policies\BlogPostPolicy;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Support\Jalali;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Throwable;

class BlogPostForm extends Component
{
    use Toast, WithFileUploads;

    public ?BlogPost $record = null;

    /**
     * فقط وقتی صفحه از مسیر ویرایش باز شده true می‌شود.
     * با $record فرق دارد: بعد از autosave در حالت ساخت، $record پر می‌شود
     * ولی $isEditing همچنان false می‌ماند تا متن دکمه عوض نشود.
     */
    public bool $isEditing = false;


    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $meta_title = '';

    public string $meta_description = '';

    public string $category_id = '';

    /**
     * برچسب آزاد: نام‌های تایپ‌شده توسط کاربر (نه id). موقع ذخیره با
     * BlogTag::firstOrCreate به رکورد واقعی تبدیل می‌شوند.
     */
    public array $tagNames = [];

    public string $author_user_id = '';

    public string $content = '';

    public string $reading_time_minutes = '';

    public string $post_status = 'draft';

    public $featuredImage = null;

    public ?string $existingFeaturedImagePath = null;

    public string $scheduled_time = '';

    /**
     * @var array<string, array{year: ?int, month: ?int, day: ?int}>
     */
    public array $jalaliParts = [
        'scheduled_date' => ['year' => null, 'month' => null, 'day' => null],
    ];

    public string $scheduled_date = '';

    /**
     * شناسه ثابت ادیتور Quill برای کل عمر این کامپوننت (یک‌بار در mount ساخته
     * می‌شود). عمداً از id رکورد مشتق نمی‌شود: در حالت ساخت، اولین autosave
     * یک رکورد جدید می‌سازد و $this->record->id از null به یک UUID واقعی
     * تغییر می‌کند؛ چون خودِ div ادیتور wire:ignore دارد، x-init آن فقط یک‌بار
     * با اولین مقدار اجرا می‌شود و دیگر عوض نمی‌شود، ولی input مخفی content
     * (که wire:ignore ندارد) با هر render دوباره با id جدید ساخته می‌شد —
     * یعنی بعد از اولین autosave، Quill داشت به یک id غیرموجود می‌نوشت و
     * محتوا هرگز به سرور نمی‌رسید. یک id ثابت مستقل از رکورد این عدم‌تطابق
     * را کاملاً حذف می‌کند.
     */
    public string $editorInstanceId = '';

    public function mount(?string $post = null): void
    {
        $this->editorInstanceId = (string) \Illuminate\Support\Str::uuid();

        if ($post) {
            $this->isEditing = true;
            $this->record = BlogPost::with('tags')->findOrFail($post);
            $this->authorize('update', $this->record);

            $this->title = $this->record->title;
            $this->slug = $this->record->slug;
            $this->slugManuallyEdited = true;
            $this->meta_title = (string) $this->record->meta_title;
            $this->meta_description = (string) $this->record->meta_description;
            $this->category_id = (string) $this->record->category_id;
            $this->tagNames = $this->record->tags->pluck('name')->all();
            $this->author_user_id = $this->record->author_user_id;
            $this->content = (string) ($this->record->content_html ?? '');
            $this->reading_time_minutes = (string) ($this->record->reading_time_minutes ?? '');
            $this->post_status = $this->record->post_status->value;
            $this->existingFeaturedImagePath = $this->record->featured_image_path;

            if ($this->record->published_at) {
                $this->scheduled_date = Jalali::localDateString($this->record->published_at) ?? '';
                $this->jalaliParts['scheduled_date'] = Jalali::toJalaliParts($this->record->published_at);
                $this->scheduled_time = $this->record->published_at?->timezone(config('app.display_timezone', config('app.timezone')))->format('H:i') ?? '';
            }

            return;
        }

        $this->authorize('create', BlogPost::class);
        $this->author_user_id = auth()->id();
    }

    /**
     * تنها نقطه ورود autosave — از سمت JS با یک debounce واحد و قابل‌لغو در
     * Alpine صدا زده می‌شود (نه wire:model.live جدا روی title و content).
     * دلیل: دو trigger مستقل (title.live.debounce + content.live.debounce)
     * دو درخواست Livewire کاملاً جدا و بی‌هماهنگی می‌ساختند که می‌توانستند با
     * $wire.save() نهایی race کنند — پاسخ یک درخواست قدیمی‌تر که دیرتر
     * می‌رسید، state تازه‌تر را با مقدار قدیمی (حتی title خالی) بازنویسی
     * می‌کرد. حالا submitForm در Alpine قبل از save() صریحاً منتظر تکمیل
     * همین یک مسیر autosave می‌ماند، پس هرگز با آن race نمی‌کند.
     */
    public function runAutosave(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = $this->generateSlug($this->title);
        }

        $this->autosaveDraft();
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    protected function generateSlug(string $source): string
    {
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::slug(Str::random(8));
    }

    /**
     * حالت ساخت: به‌محض عنوان غیرخالی یک draft ساخته می‌شود و id آن در
     * $this->record می‌ماند تا فراخوانی‌های بعدی همان رکورد را update کنند.
     * حالت ویرایش: فقط تا وقتی پست خودش draft است فعال می‌ماند — روی
     * scheduled/published هیچ نوشتنی رخ نمی‌دهد، فقط دکمه «ذخیره تغییرات»
     * صریح کار می‌کند. خطاها بی‌صدا نادیده گرفته می‌شوند (بند ۱، مشخصات
     * کارفرما) چون کاربر هنوز در حال تایپ است؛ ذخیره نهایی همچنان
     * اعتبارسنجی کامل خودش را دارد.
     */
    public function autosaveDraft(): void
    {
        if (trim($this->title) === '') {
            return;
        }

        if ($this->record && $this->record->post_status !== BlogPostStatus::Draft) {
            return;
        }

        $companyContext = app(CompanyContext::class);
        $companyId = $this->record?->owner_company_id ?? $companyContext->id();

        if ($companyId === null) {
            return;
        }

        $slug = $this->slug !== '' ? $this->slug : $this->generateSlug($this->title);

        try {
            $this->record = app(AutosaveBlogPostDraft::class)->handle($this->record, [
                'owner_company_id' => $companyId,
                'title' => $this->title,
                'slug' => $slug,
                'content_html' => $this->content,
            ], auth()->user());

            // اگر کاربر خودش دستی اسلاگ را ویرایش کرده، مقدار تایپ‌شده‌اش دست‌نخورده
            // می‌ماند — حتی اگر autosave به‌خاطر تصادم مجبور شده باشد یک نسخه یکتای
            // دیگر را در دیتابیس ذخیره کند. وگرنه اعتبارسنجی نهایی save() هرگز تصادم
            // واقعی را به کاربر نشان نمی‌دهد (autosave بی‌صدا آن را دور می‌زند).
            if (! $this->slugManuallyEdited) {
                $this->slug = $this->record->slug;
            }
        } catch (Throwable) {
            // ذخیره خودکار تلاش بی‌صداست؛ شکست آن نباید تایپ کاربر را مزاحم شود.
        }
    }

    public function updatedJalaliParts($value, $key): void
    {
        [$field] = explode('.', $key);

        if (! property_exists($this, $field)) {
            return;
        }

        $year = $this->jalaliParts[$field]['year'] ?? null;
        $month = $this->jalaliParts[$field]['month'] ?? null;
        $day = $this->jalaliParts[$field]['day'] ?? null;

        if ($day && $month) {
            $maxDay = Jalali::maxDayForMonth($year, $month);

            if ((int) $day > $maxDay) {
                $day = $maxDay;
                $this->jalaliParts[$field]['day'] = $maxDay;
            }
        }

        $this->{$field} = Jalali::toGregorian($year, $month, $day) ?? '';
    }

    public function getCanPublishProperty(): bool
    {
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return app(BlogPostPolicy::class)->canPublish(auth()->user(), $companyId);
    }

    public function getPostStatusOptionsProperty(): array
    {
        if (! $this->canPublish) {
            return [['id' => BlogPostStatus::Draft->value, 'name' => BlogPostStatus::Draft->label()]];
        }

        return array_map(fn (BlogPostStatus $case) => ['id' => $case->value, 'name' => $case->label()], BlogPostStatus::cases());
    }

    public function getCategoryOptionsProperty(): array
    {
        return BlogCategory::query()->orderBy('name')->get()
            ->map(fn (BlogCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    public function getAuthorOptionsProperty(): array
    {
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null) {
            return [];
        }

        return User::orderBy('full_name')
            ->get(['id', 'full_name', 'is_super_admin'])
            ->filter(fn (User $user) => $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->full_name])
            ->values()
            ->all();
    }

    public function getMetaTitleRemainingProperty(): int
    {
        return 70 - mb_strlen($this->meta_title);
    }

    public function getMetaDescriptionRemainingProperty(): int
    {
        return 160 - mb_strlen($this->meta_description);
    }

    protected function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required', 'string', 'max:220', 'alpha_dash',
                Rule::unique('blog_posts', 'slug')
                    ->where('owner_company_id', $companyId)
                    ->ignore($this->record?->id),
            ],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'category_id' => [
                'nullable',
                Rule::when(filled($this->category_id), ['uuid', 'exists:blog_categories,id']),
            ],
            'tagNames' => ['array'],
            'tagNames.*' => ['string', 'max:60'],
            'content' => ['required', 'string'],
            'reading_time_minutes' => [
                'nullable',
                Rule::when(filled($this->reading_time_minutes), ['integer', 'min:0']),
            ],
            'post_status' => ['required', Rule::in(array_map(fn ($case) => $case->value, BlogPostStatus::cases()))],
            'featuredImage' => ['nullable', 'image', 'max:2048'],
            'scheduled_date' => [
                Rule::requiredIf($this->canPublish && $this->post_status === BlogPostStatus::Scheduled->value),
                Rule::when(filled($this->scheduled_date), ['date']),
            ],
            'scheduled_time' => [
                Rule::requiredIf($this->canPublish && $this->post_status === BlogPostStatus::Scheduled->value),
                Rule::when(filled($this->scheduled_time), ['date_format:H:i']),
            ],
        ];
    }

    /**
     * برچسب آزاد: هر نام تایپ‌شده یا به رکورد موجود همان شرکت وصل می‌شود
     * (تطبیق بر اسلاگ) یا خودکار ساخته می‌شود — محدود به برچسب‌های
     * از‌پیش‌ساخته در ماژول تگ‌ها نیست.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    protected function resolveTagIds(?string $companyId, array $names): array
    {
        if ($companyId === null) {
            return [];
        }

        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => $this->tagSlug($name))
            ->map(function (string $name) use ($companyId) {
                $tag = BlogTag::withoutGlobalScopes()->firstOrCreate(
                    ['owner_company_id' => $companyId, 'slug' => $this->tagSlug($name)],
                    ['name' => $name, 'created_by_user_id' => auth()->id(), 'updated_by_user_id' => auth()->id()],
                );

                return $tag->id;
            })
            ->values()
            ->all();
    }

    /**
     * Str::slug روی نام کاملاً فارسی رشته خالی برمی‌گرداند (همان مشکل مستند‌شده
     * در generateSlug پست/BlogTagForm). آنجا چون کاربر بعداً دستی ویرایش می‌کند
     * یک fallback تصادفی کافی بود؛ اینجا چون هیچ فرم دستی‌ای در کار نیست و باید
     * تایپ دوباره‌ی همان نام دقیقاً به همان تگ برسد (نه یک تگ تکراری جدید)،
     * fallback باید decisive و برای یک نام ثابت همیشه یکسان باشد.
     */
    protected function tagSlug(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'tag-'.substr(sha1($name), 0, 8);
    }

    /**
     * به‌محض انتخاب عکس، فوراً روی دیسک public ذخیره می‌شود.
     * اگر تا save صبر کنیم، autosaveهای میانی TemporaryUploadedFile را از بین
     * می‌برند و مسیر در دیتابیس خالی می‌ماند.
     */
    public function updatedFeaturedImage(): void
    {
        if (! $this->featuredImage) {
            return;
        }

        $this->validateOnly('featuredImage');

        $path = $this->featuredImage->store('blog/featured-images', 'public');

        if ($this->existingFeaturedImagePath && $this->existingFeaturedImagePath !== $path) {
            Storage::disk('public')->delete($this->existingFeaturedImagePath);
        }

        $this->existingFeaturedImagePath = $path;
        $this->featuredImage = null;
    }


    public function save(CreateBlogPost $createAction, UpdateBlogPost $updateAction, CompanyContext $companyContext): void
    {
        // اسلاگ نباید منتظر رسیدن autosave (debounce دوثانیه‌ای سمت JS) بماند —
        // اگر کاربر بلافاصله بعد از تایپ عنوان روی «ثبت» کلیک کند (سریع‌تر از
        // دوثانیه)، هنوز هیچ runAutosave ای اجرا نشده و $this->slug می‌تواند
        // خالی بماند. همان تولید خودکار اینجا هم تکرار می‌شود تا ذخیره نهایی
        // هرگز به چرخه autosave وابسته نباشد.
        if (! $this->slugManuallyEdited) {
            $this->slug = $this->generateSlug($this->title);
        }

        try {
            $data = $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->error('ذخیره انجام نشد. خطاهای فرم را بررسی کنید.');
            throw $e;
        }

        $data['meta_title'] = $data['meta_title'] !== '' ? $data['meta_title'] : null;
        $data['meta_description'] = $data['meta_description'] !== '' ? $data['meta_description'] : null;
        $data['category_id'] = $data['category_id'] ?: null;
        $data['reading_time_minutes'] = $data['reading_time_minutes'] !== '' && $data['reading_time_minutes'] !== null ? $data['reading_time_minutes'] : null;
        $data['content_html'] = $data['content'];
        unset($data['content']);

        $data['author_user_id'] = $this->author_user_id ?: auth()->id();

        $ownerCompanyId = $this->record?->owner_company_id ?? $companyContext->id();
        $data['tag_ids'] = $this->resolveTagIds($ownerCompanyId, $data['tagNames']);
        unset($data['tagNames']);

        if ($data['post_status'] === BlogPostStatus::Scheduled->value) {
            $data['published_at'] = Jalali::fromLocal("{$data['scheduled_date']} {$data['scheduled_time']}");
        } elseif ($data['post_status'] === BlogPostStatus::Published->value) {
            $data['published_at'] = now();
        } else {
            $data['published_at'] = null;
        }
        unset($data['scheduled_date'], $data['scheduled_time']);

        // عکس شاخص در updatedFeaturedImage() هنگام انتخاب فایل ذخیره شده؛
        // اینجا فقط مسیر نهایی را می‌نویسیم. اگر به‌هر دلیل هنوز TemporaryUpload
        // روی property مانده باشد (مثلاً autosave خاموش بوده)، همین‌جا ذخیره می‌کنیم.
        unset($data['featuredImage']);

        if ($this->featuredImage) {
            $path = $this->featuredImage->store('blog/featured-images', 'public');
            if ($this->existingFeaturedImagePath && $this->existingFeaturedImagePath !== $path) {
                Storage::disk('public')->delete($this->existingFeaturedImagePath);
            }
            $this->existingFeaturedImagePath = $path;
            $this->featuredImage = null;
        }

        $data['featured_image_path'] = $this->existingFeaturedImagePath;

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $message = $this->isEditing
                ? 'تغییرات با موفقیت ذخیره شد.'
                : 'پست با موفقیت ایجاد شد.';

            $this->success($message, redirectTo: route('blog.posts.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('پست با موفقیت ایجاد شد.', redirectTo: route('blog.posts.index'));
    }

    public function render()
    {
        return view('livewire.blog.blog-post-form', [
            'existingFeaturedImageUrl' => $this->existingFeaturedImagePath ? Storage::url($this->existingFeaturedImagePath) : null,
            'initialContent' => $this->content,
            'editorId' => 'blog-post-editor-'.$this->editorInstanceId,
        ]);
    }
}
