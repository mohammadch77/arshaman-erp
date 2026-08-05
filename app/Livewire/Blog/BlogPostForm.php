<?php

namespace App\Livewire\Blog;

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

class BlogPostForm extends Component
{
    use Toast, WithFileUploads;

    public ?BlogPost $record = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $meta_title = '';

    public string $meta_description = '';

    public string $category_id = '';

    public array $tag_ids = [];

    public string $author_user_id = '';

    public string $content = '[]';

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

    public function mount(?string $post = null): void
    {
        if ($post) {
            $this->record = BlogPost::with('tags')->findOrFail($post);
            $this->authorize('update', $this->record);

            $this->title = $this->record->title;
            $this->slug = $this->record->slug;
            $this->slugManuallyEdited = true;
            $this->meta_title = (string) $this->record->meta_title;
            $this->meta_description = (string) $this->record->meta_description;
            $this->category_id = (string) $this->record->category_id;
            $this->tag_ids = $this->record->tags->pluck('id')->all();
            $this->author_user_id = $this->record->author_user_id;
            $this->content = json_encode($this->record->content_blocks ?: []);
            $this->reading_time_minutes = (string) ($this->record->reading_time_minutes ?? '');
            $this->post_status = $this->record->post_status->value;
            $this->existingFeaturedImagePath = $this->record->featured_image_path;

            if ($this->record->published_at) {
                $this->scheduled_date = Jalali::localDateString($this->record->published_at) ?? '';
                $this->jalaliParts['scheduled_date'] = Jalali::toJalaliParts($this->record->published_at);
                $this->scheduled_time = Jalali::toDisplayTime($this->record->published_at) ?? '';
            }

            return;
        }

        $this->authorize('create', BlogPost::class);
        $this->author_user_id = auth()->id();
    }

    public function updatedTitle(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = $this->generateSlug($this->title);
        }
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

    public function getTagOptionsProperty(): array
    {
        return BlogTag::query()->orderBy('name')->get()
            ->map(fn (BlogTag $tag) => ['id' => $tag->id, 'name' => $tag->name])
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
            'category_id' => ['nullable', 'uuid', 'exists:blog_categories,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['uuid', 'exists:blog_tags,id'],
            'content' => ['required', 'string'],
            'reading_time_minutes' => ['nullable', 'integer', 'min:0'],
            'post_status' => ['required', Rule::in(array_map(fn ($case) => $case->value, BlogPostStatus::cases()))],
            'featuredImage' => ['nullable', 'image', 'max:2048'],
            'scheduled_date' => [$this->canPublish && $this->post_status === BlogPostStatus::Scheduled->value ? 'required' : 'nullable', 'date'],
            'scheduled_time' => [$this->canPublish && $this->post_status === BlogPostStatus::Scheduled->value ? 'required' : 'nullable', 'date_format:H:i'],
        ];
    }

    public function save(CreateBlogPost $createAction, UpdateBlogPost $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        $data['meta_title'] = $data['meta_title'] !== '' ? $data['meta_title'] : null;
        $data['meta_description'] = $data['meta_description'] !== '' ? $data['meta_description'] : null;
        $data['category_id'] = $data['category_id'] ?: null;
        $data['reading_time_minutes'] = $data['reading_time_minutes'] !== '' && $data['reading_time_minutes'] !== null ? $data['reading_time_minutes'] : null;
        $decodedBlocks = json_decode($data['content'], true);
        $data['content_blocks'] = is_array($decodedBlocks) ? $decodedBlocks : [];
        unset($data['content']);

        $data['author_user_id'] = $this->author_user_id ?: auth()->id();

        if ($data['post_status'] === BlogPostStatus::Scheduled->value) {
            $data['published_at'] = Jalali::fromLocal("{$data['scheduled_date']} {$data['scheduled_time']}");
        } elseif ($data['post_status'] === BlogPostStatus::Published->value) {
            $data['published_at'] = now();
        } else {
            $data['published_at'] = null;
        }
        unset($data['scheduled_date'], $data['scheduled_time']);

        $featuredImage = $data['featuredImage'] ?? null;
        unset($data['featuredImage']);

        if ($featuredImage) {
            $data['featured_image_path'] = $featuredImage->store('blog/featured-images', 'public');
        } else {
            $data['featured_image_path'] = $this->existingFeaturedImagePath;
        }

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('پست به‌روزرسانی شد.', redirectTo: route('blog.posts.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('پست جدید ساخته شد.', redirectTo: route('blog.posts.index'));
    }

    public function render()
    {
        $decodedBlocks = json_decode($this->content, true);

        return view('livewire.blog.blog-post-form', [
            'existingFeaturedImageUrl' => $this->existingFeaturedImagePath ? Storage::url($this->existingFeaturedImagePath) : null,
            'initialBlocks' => is_array($decodedBlocks) ? $decodedBlocks : [],
            'editorId' => 'blog-post-editor-'.($this->record?->id ?? 'new'),
        ]);
    }
}
