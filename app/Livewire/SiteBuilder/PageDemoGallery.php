<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

class PageDemoGallery extends Component
{
    use Toast;

    public ?string $selectedDemoId = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public function mount(): void
    {
        $this->authorize('create', Page::class);
    }

    public function selectDemo(string $demoId): void
    {
        $this->selectedDemoId = $demoId;
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

    public function getCategoriesProperty()
    {
        return PageCategory::with(['demos' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn (PageCategory $category) => $category->demos->isNotEmpty());
    }

    protected function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'selectedDemoId' => ['required', 'uuid', 'exists:page_demos,id'],
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:150', 'alpha_dash',
                Rule::unique('pages', 'slug')->where('owner_company_id', $companyId),
            ],
        ];
    }

    public function create(CreatePageFromDemo $action, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        $page = $action->handle([
            'owner_company_id' => $companyContext->id(),
            'page_demo_id' => $data['selectedDemoId'],
            'title' => $data['title'],
            'slug' => $data['slug'],
        ], auth()->user());

        $this->success('صفحه از روی دمو ساخته شد.', redirectTo: route('sitebuilder.pages.edit', $page->id));
    }

    public function render()
    {
        return view('livewire.site-builder.page-demo-gallery');
    }
}
