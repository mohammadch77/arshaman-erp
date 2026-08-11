<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Actions\UpdateSiteSettings;
use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
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

    public $logo = null;

    public $favicon = null;

    public function mount(CompanyContext $companyContext): void
    {
        $this->record = SiteSetting::firstOrCreate(['owner_company_id' => $companyContext->id()]);
        $this->authorize('update', $this->record);

        $this->site_title = (string) ($this->record->site_title ?? '');
        $this->site_tagline = (string) ($this->record->site_tagline ?? '');
        $this->active_header_demo_id = $this->record->active_header_demo_id;
        $this->active_footer_demo_id = $this->record->active_footer_demo_id;
    }

    public function getHeaderDemosProperty()
    {
        return LayoutDemo::where('layout_type', LayoutType::Header->value)->where('is_active', true)->orderBy('name')->get();
    }

    public function getFooterDemosProperty()
    {
        return LayoutDemo::where('layout_type', LayoutType::Footer->value)->where('is_active', true)->orderBy('name')->get();
    }

    protected function rules(): array
    {
        return [
            'site_title' => ['nullable', 'string', 'max:100'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'active_header_demo_id' => ['nullable', 'uuid', 'exists:layout_demos,id'],
            'active_footer_demo_id' => ['nullable', 'uuid', 'exists:layout_demos,id'],
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
            'homepage_page_id' => $this->record->homepage_page_id,
            'blog_page_id' => $this->record->blog_page_id,
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
