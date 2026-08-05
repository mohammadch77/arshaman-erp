<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Actions\CreateBlogCategory;
use App\Modules\Blog\Actions\UpdateBlogCategory;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

class BlogCategoryForm extends Component
{
    use Toast;

    public ?BlogCategory $record = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $slugManuallyEdited = false;

    public function mount(?string $category = null): void
    {
        if ($category) {
            $this->record = BlogCategory::findOrFail($category);
            $this->authorize('update', $this->record);

            $this->name = $this->record->name;
            $this->slug = $this->record->slug;
            $this->description = (string) $this->record->description;
            $this->slugManuallyEdited = true;

            return;
        }

        $this->authorize('create', BlogCategory::class);
    }

    public function updatedName(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = $this->generateSlug($this->name);
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

    protected function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('blog_categories', 'slug')
                    ->where('owner_company_id', $companyId)
                    ->ignore($this->record?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(CreateBlogCategory $createAction, UpdateBlogCategory $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();
        $data['description'] = $data['description'] !== '' ? $data['description'] : null;

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('دسته‌بندی به‌روزرسانی شد.', redirectTo: route('blog.categories.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('دسته‌بندی جدید ساخته شد.', redirectTo: route('blog.categories.index'));
    }

    public function render()
    {
        return view('livewire.blog.blog-category-form');
    }
}
