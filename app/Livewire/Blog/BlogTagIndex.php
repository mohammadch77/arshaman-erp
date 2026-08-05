<?php

namespace App\Livewire\Blog;

use App\Modules\Blog\Models\BlogTag;
use Livewire\Component;
use Livewire\WithPagination;

class BlogTagIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', BlogTag::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getTagsProperty()
    {
        return BlogTag::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.blog.blog-tag-index', [
            'tags' => $this->tags,
        ]);
    }
}
