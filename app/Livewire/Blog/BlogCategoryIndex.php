<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Models\BlogCategory;
use Livewire\Component;
use Livewire\WithPagination;

class BlogCategoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', BlogCategory::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getCategoriesProperty()
    {
        return BlogCategory::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.blog.blog-category-index', [
            'categories' => $this->categories,
        ]);
    }
}
