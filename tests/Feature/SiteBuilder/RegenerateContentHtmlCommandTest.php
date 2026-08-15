<?php

use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;

function regenCompany(): Company
{
    return Company::create([
        'name' => 'آرشامان',
        'slug' => 'arshaman-regen-'.uniqid(),
        'business_type' => 'project_services',
    ]);
}

function regenDemo(): PageDemo
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [],
    ]);
}

function regenPage(Company $company, array $overrides = []): Page
{
    return Page::create(array_merge([
        'owner_company_id' => $company->id,
        'page_demo_id' => regenDemo()->id,
        'title' => 'درباره ما',
        'slug' => 'about-'.uniqid(),
        'meta_title' => null,
        'meta_description' => null,
        'widget_tree' => [
            [
                'id' => 'page-image',
                'widget_key' => WidgetKey::Image->value,
                'values' => ['image_path' => 'sitebuilder/images/test.png', 'alt' => 'تصویر تست'],
                'children' => [],
            ],
        ],
        // شبیه‌سازی خروجی رندرر قدیمی، پیش از resolveImageUrl (بدون Storage::url و بدون wrapper sb-page).
        'content_html' => '<img class="sb-widget sb-widget-image" src="sitebuilder/images/test.png" alt="تصویر تست">',
        'page_status' => PageStatus::Draft->value,
        'updated_by_user_id' => null,
    ], $overrides));
}

it('rebuilds content_html from the current renderer for every existing page', function () {
    $company = regenCompany();
    $page = regenPage($company);

    $this->artisan('sitebuilder:regenerate-content-html')->assertSuccessful();

    $fresh = $page->fresh();

    expect($fresh->content_html)
        ->toContain('sb-page')
        ->and($fresh->content_html)
        ->not->toContain('src="sitebuilder/images/test.png"')
        ->and($fresh->content_html)
        ->toContain('src="/storage/sitebuilder/images/test.png"');
});

it('does not touch widget_tree or updated_by_user_id when regenerating', function () {
    $company = regenCompany();
    $page = regenPage($company);
    $originalWidgetTree = $page->widget_tree;

    $this->artisan('sitebuilder:regenerate-content-html')->assertSuccessful();

    $fresh = $page->fresh();

    expect($fresh->widget_tree)->toBe($originalWidgetTree)
        ->and($fresh->updated_by_user_id)->toBeNull();
});

it('leaves an already up-to-date page unreported as updated', function () {
    $company = regenCompany();
    $page = regenPage($company);

    // اول یک‌بار اجرا می‌کنیم تا content_html با رندرر فعلی هماهنگ شود.
    $this->artisan('sitebuilder:regenerate-content-html');

    $this->artisan('sitebuilder:regenerate-content-html')
        ->expectsOutputToContain('0 صفحه به‌روز شد')
        ->assertSuccessful();
});
