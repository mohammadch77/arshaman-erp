<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Actions\UpdateSiteSettings;
use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class LayoutDemoSelector extends Component
{
    use Toast, WithFileUploads;

    public SiteSetting $record;

    public string $site_title = '';

    public string $site_tagline = '';

    public ?string $active_header_demo_id = null;

    public ?string $active_footer_demo_id = null;

    public ?string $homepage_page_id = null;

    public ?string $blog_page_id = null;

    public $logo = null;

    public $favicon = null;

    public string $companyId = '';

    public function mount(CompanyContext $companyContext): void
    {
        $this->companyId = $companyContext->id();
        $this->record = SiteSetting::firstOrCreate(['owner_company_id' => $this->companyId]);
        $this->authorize('update', $this->record);

        $this->site_title = (string) ($this->record->site_title ?? '');
        $this->site_tagline = (string) ($this->record->site_tagline ?? '');
        $this->active_header_demo_id = $this->record->active_header_demo_id;
        $this->active_footer_demo_id = $this->record->active_footer_demo_id;
        $this->homepage_page_id = $this->record->homepage_page_id;
        $this->blog_page_id = $this->record->blog_page_id;
    }

    public function getHeaderDemosProperty()
    {
        return LayoutDemo::where('layout_type', LayoutType::Header->value)->where('is_active', true)->orderBy('name')->get();
    }

    public function getFooterDemosProperty()
    {
        return LayoutDemo::where('layout_type', LayoutType::Footer->value)->where('is_active', true)->orderBy('name')->get();
    }

    public function getPublishedPagesProperty()
    {
        return Page::where('owner_company_id', $this->companyId)->published()->orderBy('title')->get();
    }

    /**
     * پیش‌نمایش زنده دموی هدر/فوتر انتخاب‌شده — از همان WidgetContentRenderer
     * که رندر نهایی صفحه عمومی (PublicSiteController) و پیش‌نمایش
     * PageContentEditor استفاده می‌کنند؛ یک منبع واحد رندر. رادیوهای انتخاب در
     * Blade با wire:model.live بایند شده‌اند، پس هر کلیک خودش یک round-trip
     * می‌زند و این computed property را دوباره می‌سازد — نیازی به debounce
     * جداگانه نیست چون این یک انتخاب گسسته است، نه تایپ پیوسته.
     */
    public function getHeaderPreviewHtmlProperty(): string
    {
        return $this->renderLayoutDemoPreview($this->active_header_demo_id);
    }

    public function getFooterPreviewHtmlProperty(): string
    {
        return $this->renderLayoutDemoPreview($this->active_footer_demo_id);
    }

    private function renderLayoutDemoPreview(?string $demoId): string
    {
        if ($demoId === null) {
            return '';
        }

        $demo = LayoutDemo::find($demoId);

        if ($demo === null) {
            return '';
        }

        $company = Company::find($this->companyId);

        return app(WidgetContentRenderer::class)->render($demo->widget_tree, $company);
    }

    public function getHeaderPreviewDocumentProperty(): string
    {
        return $this->wrapPreviewDocument($this->headerPreviewHtml);
    }

    public function getFooterPreviewDocumentProperty(): string
    {
        return $this->wrapPreviewDocument($this->footerPreviewHtml);
    }

    private function wrapPreviewDocument(string $html): string
    {
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<style>body{margin:0;padding:1rem;font-family:inherit;}</style>'
            .'</head><body>'.$html.'</body></html>';
    }

    protected function rules(): array
    {
        $publishedPageExists = Rule::exists('pages', 'id')->where(function ($query) {
            $query->where('owner_company_id', $this->companyId)->where('page_status', 'published');
        });

        return [
            'site_title' => ['nullable', 'string', 'max:100'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'active_header_demo_id' => ['nullable', 'uuid', 'exists:layout_demos,id'],
            'active_footer_demo_id' => ['nullable', 'uuid', 'exists:layout_demos,id'],
            'homepage_page_id' => ['nullable', 'uuid', $publishedPageExists],
            'blog_page_id' => ['nullable', 'uuid', $publishedPageExists],
            'logo' => ['nullable', 'image', 'max:1024'],
            'favicon' => ['nullable', 'image', 'max:256'],
        ];
    }

    public function save(UpdateSiteSettings $action): void
    {
        $data = $this->validate();

        $logoPath = $this->logo ? $this->logo->store('sitebuilder/branding', 'public') : $this->record->logo_path;
        $faviconPath = $this->favicon ? $this->favicon->store('sitebuilder/branding', 'public') : $this->record->favicon_path;

        $action->handle($this->record, [
            'site_title' => $data['site_title'] !== '' ? $data['site_title'] : null,
            'site_tagline' => $data['site_tagline'] !== '' ? $data['site_tagline'] : null,
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
            'homepage_page_id' => $data['homepage_page_id'],
            'blog_page_id' => $data['blog_page_id'],
            'active_header_demo_id' => $data['active_header_demo_id'],
            'active_footer_demo_id' => $data['active_footer_demo_id'],
        ], auth()->user());

        $this->success('تنظیمات سایت ذخیره شد.');
    }

    public function render()
    {
        return view('livewire.site-builder.layout-demo-selector', [
            'existingLogoUrl' => $this->record->logo_path ? Storage::url($this->record->logo_path) : null,
            'existingFaviconUrl' => $this->record->favicon_path ? Storage::url($this->record->favicon_path) : null,
        ]);
    }
}
