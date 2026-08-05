<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Actions\CreateBlogTag;
use App\Modules\Blog\Actions\UpdateBlogTag;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

class BlogTagForm extends Component
{
    use Toast;

    public ?BlogTag $record = null;

    public string $name = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public function mount(?string $tag = null): void
    {
        if ($tag) {
            $this->record = BlogTag::findOrFail($tag);
            $this->authorize('update', $this->record);

            $this->name = $this->record->name;
            $this->slug = $this->record->slug;
            $this->slugManuallyEdited = true;

            return;
        }

        $this->authorize('create', BlogTag::class);
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
            'name' => ['required', 'string', 'max:40'],
            'slug' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('blog_tags', 'slug')
                    ->where('owner_company_id', $companyId)
                    ->ignore($this->record?->id),
            ],
        ];
    }

    public function save(CreateBlogTag $createAction, UpdateBlogTag $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('برچسب به‌روزرسانی شد.', redirectTo: route('blog.tags.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('برچسب جدید ساخته شد.', redirectTo: route('blog.tags.index'));
    }

    public function render()
    {
        return view('livewire.blog.blog-tag-form');
    }
}
