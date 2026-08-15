<?php

namespace App\Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\SiteSetting;
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
    public function __construct(private WidgetContentRenderer $renderer) {}

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
    private function renderPage(Company $company, ?SiteSetting $siteSetting, Page $page): View
    {
        $headerHtml = $siteSetting?->activeHeaderDemo !== null
            ? $this->renderer->render($siteSetting->activeHeaderDemo->widget_tree, $company)
            : '';

        $footerHtml = $siteSetting?->activeFooterDemo !== null
            ? $this->renderer->render($siteSetting->activeFooterDemo->widget_tree, $company)
            : '';

        return view('public.sitebuilder.show', [
            'company' => $company,
            'siteSetting' => $siteSetting,
            'page' => $page,
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
        ]);
    }
}
