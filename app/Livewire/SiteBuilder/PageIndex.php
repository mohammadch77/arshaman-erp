<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\SiteBuilder\Models\Page;
use Livewire\Component;
use Livewire\WithPagination;

class PageIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Page::class);
    }

    public function render()
    {
        $pages = Page::with('demo.category')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.site-builder.page-index', ['pages' => $pages]);
    }
}
