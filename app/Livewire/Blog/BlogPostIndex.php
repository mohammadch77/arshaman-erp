<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use Livewire\Component;
use Livewire\WithPagination;

class BlogPostIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $postStatus = '';

    public string $categoryId = '';

    public string $authorUserId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', BlogPost::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPostStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedAuthorUserId(): void
    {
        $this->resetPage();
    }

    public function getPostStatusOptionsProperty(): array
    {
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

    public function getPostsProperty()
    {
        return BlogPost::with(['category', 'author'])
            ->when($this->search, fn ($query) => $query->where('title', 'like', "%{$this->search}%"))
            ->when($this->postStatus, fn ($query) => $query->where('post_status', $this->postStatus))
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->authorUserId, fn ($query) => $query->where('author_user_id', $this->authorUserId))
            ->latest()
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.blog.blog-post-index', [
            'posts' => $this->posts,
        ]);
    }
}
