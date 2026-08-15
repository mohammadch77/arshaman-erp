<?php

namespace App\Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Services\DynamicWidgetResolver;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\View\View;

/**
 * صفحات عمومی سایت‌ساز برای بازدیدکننده مهمان — بدون middleware auth. دقیقاً
 * همان الگوی ایزولاسیون PublicBlogController: هر کوئری
 * withoutGlobalScope('owner_company') می‌شود (نه withoutGlobalScopes، تا
 * SoftDeletes روی Page دست‌نخورده بماند) و owner_company_id از companySlug
 * مسیر گرفته می‌شود، نه از CompanyContext session (که برای مهمان بی‌معناست).
 */
class PublicSiteController extends Controller
{
    public function __construct(
        private WidgetContentRenderer $renderer,
        private DynamicWidgetResolver $dynamicWidgetResolver,
    ) {}

    public function home(string $companySlug): View
    {
        $company = Company::where('slug', $companySlug)->firstOrFail();
        $siteSetting = $this->findSiteSetting($company->id);

        if ($siteSetting === null || $siteSetting->homepage_page_id === null) {
            return view('public.sitebuilder.not-configured', ['company' => $company]);
        }

        $page = Page::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->where('id', $siteSetting->homepage_page_id)
            ->published()
            ->firstOrFail();

        return $this->renderPage($company, $siteSetting, $page);
    }

    public function show(string $companySlug, string $pageSlug): View
    {
        $company = Company::where('slug', $companySlug)->firstOrFail();

        $page = Page::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->where('slug', $pageSlug)
            ->published()
            ->firstOrFail();

        $siteSetting = $this->findSiteSetting($company->id);

        return $this->renderPage($company, $siteSetting, $page);
    }

    /**
     * پیش‌نمایش ادمین (auth، PagePolicy::view) — برخلاف show()، فیلتر
     * published() ندارد چون تنها راه دیدن یک صفحه‌ی draft قبل از انتشار همین
     * است. برخلاف مسیرهای مهمان بالا، route model binding معمولی (اسکوپ‌شده
     * به BelongsToCompany/CompanyContext ادمین) استفاده می‌شود، نه
     * withoutGlobalScope — پیش‌نمایش باید به شرکت فعال همان ادمین محدود بماند.
     */
    public function preview(Page $page): View
    {
        $this->authorizePreview($page);

        $company = Company::findOrFail($page->owner_company_id);
        $siteSetting = $this->findSiteSetting($company->id);

        return $this->renderPage($company, $siteSetting, $page, preview: true);
    }

    private function authorizePreview(Page $page): void
    {
        if (! auth()->user()?->can('view', $page)) {
            abort(403);
        }
    }

    private function findSiteSetting(string $companyId): ?SiteSetting
    {
        return SiteSetting::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $companyId)
            ->with(['activeHeaderDemo', 'activeFooterDemo'])
            ->first();
    }

    /**
     * هدر/فوتر عمومی سایت از همان WidgetContentRenderer صفحات ساخته می‌شوند —
     * نه یک منبع رندر جدا. content_html خودِ صفحه از قبل هنگام ذخیره ساخته و
     * ذخیره شده (نگاه کن UpdatePageWidgetValues/CreatePageFromDemo)، پس اینجا
     * دوباره رندر نمی‌شود؛ فقط widget_tree هدر/فوتر (که چنین ستونی ندارند) در
     * لحظه رندر می‌شود.
     */
    protected function renderPage(Company $company, ?SiteSetting $siteSetting, Page $page, bool $preview = false): View
    {
        $headerHtml = $siteSetting?->activeHeaderDemo !== null
            ? $this->renderer->render($siteSetting->activeHeaderDemo->widget_tree, $company)
            : '';

        $footerHtml = $siteSetting?->activeFooterDemo !== null
            ? $this->renderer->render($siteSetting->activeFooterDemo->widget_tree, $company)
            : '';

        // content_html خودِ صفحه یک snapshot است (نگاه کن RegenerateSiteBuilderContentHtml)،
        // ولی contact_form/blog_post_list باید همیشه زنده باشند — این resolve
        // فقط همان دو marker را در یک کپی از رشته جایگزین می‌کند، بدون هیچ
        // نوشتنی روی رکورد Page.
        $bodyHtml = $this->dynamicWidgetResolver->resolve($page->content_html ?? '', $company);

        return view('public.sitebuilder.show', [
            'company' => $company,
            'siteSetting' => $siteSetting,
            'page' => $page,
            'headerHtml' => $headerHtml,
            'bodyHtml' => $bodyHtml,
            'footerHtml' => $footerHtml,
            'preview' => $preview,
        ]);
    }
}
