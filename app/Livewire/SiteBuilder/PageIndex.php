<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Actions\DeletePage;
use App\Modules\SiteBuilder\Models\Page;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class PageIndex extends Component
{
    use Toast, WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Page::class);
    }

    public function deletePage(string $pageId, DeletePage $action): void
    {
        $page = Page::findOrFail($pageId);

        try {
            $action->handle($page, auth()->user());
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->error('اجازه حذف این صفحه را ندارید.');

            return;
        }

        $this->success('صفحه حذف شد.');
    }

    public function render()
    {
        $pages = Page::with('demo.category')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.site-builder.page-index', [
            'pages' => $pages,
            'activeCompanySlug' => app(CompanyContext::class)->activeCompany()?->slug,
        ]);
    }
}
