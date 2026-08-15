<?php

use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;

function integrateFixCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'آرشامان'.$suffix,
        'slug' => 'arshaman-fix-'.($suffix ?: uniqid()),
        'business_type' => 'project_services',
    ]);
}

function integrateFixPage(Company $company, array $widgetTree, string $categoryKey = 'contact'): Page
{
    $category = PageCategory::firstOrCreate(['category_key' => $categoryKey], ['name' => $categoryKey]);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی قدیمی '.uniqid(),
        'widget_tree' => $widgetTree,
    ]);

    return Page::create([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه قدیمی',
        'slug' => 'old-page-'.uniqid(),
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
        'page_status' => PageStatus::Published->value,
    ]);
}

it('replaces the old contact-form placeholder container with the real contact_form widget on real pages', function () {
    $company = integrateFixCompany('contact');
    $page = integrateFixPage($company, [
        ['id' => 'contact-fullmap-map', 'widget_key' => WidgetKey::Map->value, 'values' => ['embed_url' => 'https://www.google.com/maps/embed?pb=1'], 'children' => []],
        [
            'id' => 'contact-fullmap-form-placeholder',
            'widget_key' => WidgetKey::Container->value,
            'values' => [],
            'children' => [
                ['id' => 'contact-fullmap-form-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'فرم تماس (به‌زودی)', 'level' => 2], 'children' => []],
            ],
        ],
    ]);

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();

    $page->refresh();

    $ids = array_column($page->widget_tree, 'id');
    expect($ids)->toContain('contact-fullmap-form');
    expect($ids)->not->toContain('contact-fullmap-form-placeholder');

    $replacedNode = collect($page->widget_tree)->firstWhere('id', 'contact-fullmap-form');
    expect($replacedNode['widget_key'])->toBe(WidgetKey::ContactForm->value);
    expect($page->content_html)->toContain('<!--sb:contact_form:');
    expect($page->content_html)->not->toContain('فرم تماس (به‌زودی)');
});

it('replaces the old blog-list placeholder containers with the real blog_post_list widget on real pages', function () {
    $company = integrateFixCompany('blog');
    $page = integrateFixPage($company, [
        ['id' => 'blog-simple-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'وبلاگ آرشامان', 'level' => 1], 'children' => []],
        [
            'id' => 'blog-simple-list-placeholder',
            'widget_key' => WidgetKey::Container->value,
            'values' => [],
            'children' => [
                ['id' => 'blog-simple-list-placeholder-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'فهرست پست‌ها به‌زودی اینجا نمایش داده می‌شود', 'level' => 5], 'children' => []],
            ],
        ],
    ]);

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();

    $page->refresh();

    $replacedNode = collect($page->widget_tree)->firstWhere('id', 'blog-simple-list');
    expect($replacedNode)->not->toBeNull();
    expect($replacedNode['widget_key'])->toBe(WidgetKey::BlogPostList->value);
    expect($page->content_html)->toContain('<!--sb:blog_post_list:');
});

it('leaves pages without the old placeholder pattern completely untouched', function () {
    // عمداً دسته "about" است، نه "contact" — تا مسیر append فرم تماس هم
    // برای این صفحه فعال نشود (آن مسیر جدا تست شده است).
    $company = integrateFixCompany('untouched');
    $widgetTree = [
        ['id' => 'page-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'صفحه عادی', 'level' => 1], 'children' => []],
    ];
    $page = integrateFixPage($company, $widgetTree, 'about');
    $originalHtml = $page->content_html;

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();

    $page->refresh();
    expect($page->content_html)->toBe($originalHtml);
});

it('appends a real contact_form to a published contact-category page that never had one at all', function () {
    // برخلاف بقیه تست‌ها، این‌جا هیچ placeholder ای وجود ندارد — دقیقاً حالت
    // واقعی کشف‌شده در دیتابیس تولید: صفحات ساخته‌شده از دموی «شعب متعدد»
    // که هرگز حتی یک container جای‌گیر فرم نداشتند.
    $company = integrateFixCompany('append');
    $category = PageCategory::firstOrCreate(['category_key' => 'contact'], ['name' => 'تماس']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی شعب بدون فرم '.uniqid(),
        'widget_tree' => [
            ['id' => 'branches-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'دفاتر ما', 'level' => 1], 'children' => []],
            ['id' => 'branches-map', 'widget_key' => WidgetKey::Map->value, 'values' => ['embed_url' => 'https://www.google.com/maps/embed?pb=1'], 'children' => []],
        ],
    ]);

    $widgetTree = $demo->widget_tree;
    $page = Page::create([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'تماس با ما',
        'slug' => 'no-form-contact-'.uniqid(),
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
        'page_status' => PageStatus::Published->value,
    ]);

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();

    $page->refresh();

    $formNode = collect($page->widget_tree)->first(fn ($node) => $node['widget_key'] === WidgetKey::ContactForm->value);
    expect($formNode)->not->toBeNull();
    expect($page->content_html)->toContain('<!--sb:contact_form:');
    // نودهای اصلی صفحه دست‌نخورده باقی مانده‌اند (فقط افزوده شده، چیزی حذف نشده).
    $ids = array_column($page->widget_tree, 'id');
    expect($ids)->toContain('branches-title');
    expect($ids)->toContain('branches-map');
});

it('does not append a contact_form to a draft contact-category page', function () {
    $company = integrateFixCompany('append-draft');
    $category = PageCategory::firstOrCreate(['category_key' => 'contact'], ['name' => 'تماس']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی شعب بدون فرم پیش‌نویس '.uniqid(),
        'widget_tree' => [
            ['id' => 'branches-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'دفاتر ما', 'level' => 1], 'children' => []],
        ],
    ]);

    $widgetTree = $demo->widget_tree;
    $page = Page::create([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'تماس با ما',
        'slug' => 'draft-no-form-contact-'.uniqid(),
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
        'page_status' => PageStatus::Draft->value,
    ]);

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();

    $page->refresh();
    $ids = array_column($page->widget_tree, 'widget_key');
    expect($ids)->not->toContain(WidgetKey::ContactForm->value);
});

it('is idempotent: running it twice does not error or change anything the second time', function () {
    $company = integrateFixCompany('idempotent');
    integrateFixPage($company, [
        [
            'id' => 'contact-fullmap-form-placeholder',
            'widget_key' => WidgetKey::Container->value,
            'values' => [],
            'children' => [
                ['id' => 'contact-fullmap-form-title', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'فرم تماس (به‌زودی)', 'level' => 2], 'children' => []],
            ],
        ],
    ]);

    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();
    $this->artisan('sitebuilder:integrate-contact-blog-widgets')->assertSuccessful();
});
