<?php

use App\Livewire\SiteBuilder\PageContentEditor;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function publicSiteCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'آرشامان'.$suffix,
        'slug' => 'arshaman-site-'.($suffix ?: uniqid()),
        'business_type' => 'project_services',
    ]);
}

function publicSiteDemo(): PageDemo
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [],
    ]);
}

function publicSitePage(Company $company, array $overrides = []): Page
{
    return Page::create(array_merge([
        'owner_company_id' => $company->id,
        'page_demo_id' => publicSiteDemo()->id,
        'title' => 'درباره ما',
        'slug' => 'about-'.uniqid(),
        'meta_title' => null,
        'meta_description' => null,
        'widget_tree' => [
            [
                'id' => 'page-title',
                'widget_key' => WidgetKey::Title->value,
                'values' => ['text' => 'محتوای صفحه اصلی', 'level' => 1],
                'children' => [],
            ],
        ],
        'content_html' => '<h1>محتوای صفحه اصلی</h1>',
        'page_status' => PageStatus::Draft->value,
    ], $overrides));
}

function publicSiteLayoutDemo(LayoutType $type, string $markerText): LayoutDemo
{
    return LayoutDemo::create([
        'layout_type' => $type->value,
        'name' => $type->label(),
        'widget_tree' => [
            [
                'id' => 'marker',
                'widget_key' => WidgetKey::Title->value,
                'values' => ['text' => $markerText, 'level' => 3],
                'children' => [],
            ],
        ],
    ]);
}

it('shows the published homepage configured via site_settings, including the active header and footer', function () {
    $company = publicSiteCompany('home');
    $page = publicSitePage($company, ['page_status' => PageStatus::Published->value]);
    $header = publicSiteLayoutDemo(LayoutType::Header, 'متن نشانگر هدر');
    $footer = publicSiteLayoutDemo(LayoutType::Footer, 'متن نشانگر فوتر');

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $page->id,
        'active_header_demo_id' => $header->id,
        'active_footer_demo_id' => $footer->id,
    ]);

    $this->get(route('public-site.home', $company->slug))
        ->assertOk()
        ->assertSee('محتوای صفحه اصلی')
        ->assertSee('متن نشانگر هدر')
        ->assertSee('متن نشانگر فوتر');
});

it('shows a clear message instead of a raw error when no homepage is configured', function () {
    $company = publicSiteCompany('unconfigured');

    $response = $this->get(route('public-site.home', $company->slug));

    $response->assertOk();
    $response->assertSee('هنوز راه‌اندازی نشده');
});

it('shows a clear message when site_settings exists but has no homepage_page_id', function () {
    $company = publicSiteCompany('partial');

    SiteSetting::create(['owner_company_id' => $company->id]);

    $response = $this->get(route('public-site.home', $company->slug));

    $response->assertOk();
    $response->assertSee('هنوز راه‌اندازی نشده');
});

it('shows a published page by slug via the direct page url', function () {
    $company = publicSiteCompany('direct');
    $page = publicSitePage($company, [
        'slug' => 'services',
        'page_status' => PageStatus::Published->value,
        'content_html' => '<h1>صفحه خدمات</h1>',
    ]);

    $this->get(route('public-site.show', [$company->slug, $page->slug]))
        ->assertOk()
        ->assertSee('صفحه خدمات');
});

it('returns 404 for a draft page even via a direct url', function () {
    $company = publicSiteCompany('draft');
    $page = publicSitePage($company, ['page_status' => PageStatus::Draft->value]);

    $this->get(route('public-site.show', [$company->slug, $page->slug]))->assertNotFound();
});

it('returns 404 for the homepage when the configured page is not published', function () {
    $company = publicSiteCompany('draft-home');
    $page = publicSitePage($company, ['page_status' => PageStatus::Draft->value]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $page->id,
    ]);

    $this->get(route('public-site.home', $company->slug))->assertNotFound();
});

it('isolates published pages by company on the direct page url', function () {
    $companyA = publicSiteCompany('a');
    $companyB = publicSiteCompany('b');

    $pageOfA = publicSitePage($companyA, [
        'slug' => 'about-a',
        'page_status' => PageStatus::Published->value,
    ]);

    $this->get(route('public-site.show', [$companyB->slug, $pageOfA->slug]))->assertNotFound();
});

it('returns 404 for an unknown company slug', function () {
    $this->get(route('public-site.home', 'no-such-company-'.uniqid()))->assertNotFound();
});

it('renders real seo meta tags and a favicon link on the public page', function () {
    $company = publicSiteCompany('seo');
    $page = publicSitePage($company, [
        'page_status' => PageStatus::Published->value,
        'meta_title' => 'عنوان سئوی صفحه',
        'meta_description' => 'توضیح سئوی صفحه',
    ]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $page->id,
        'favicon_path' => 'sitebuilder/favicons/custom.svg',
    ]);

    $response = $this->get(route('public-site.home', $company->slug));

    $response->assertOk();
    $response->assertSee('<title>عنوان سئوی صفحه', false);
    $response->assertSee('name="description" content="توضیح سئوی صفحه"', false);
    $response->assertSee('property="og:title" content="عنوان سئوی صفحه"', false);
    $response->assertSee('/storage/sitebuilder/favicons/custom.svg', false);
});

it('escapes an XSS payload in a widget field on the public route exactly like the admin renderer does', function () {
    // content_html همیشه از WidgetContentRenderer ساخته می‌شود، هرگز دستی —
    // دقیقاً همان الگویی که CreatePageFromDemo/UpdatePageWidgetValues استفاده
    // می‌کنند، تا این تست رفتار واقعی مسیر عمومی را بسنجد نه یک HTML دستی.
    $company = publicSiteCompany('xss');
    $payload = '<script>alert(1)</script>';

    $widgetTree = [
        [
            'id' => 'page-title',
            'widget_key' => WidgetKey::Title->value,
            'values' => ['text' => $payload, 'level' => 1],
            'children' => [],
        ],
    ];

    $page = publicSitePage($company, [
        'page_status' => PageStatus::Published->value,
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('<script>alert(1)</script>', false);
    $response->assertSee(e($payload), false);
});

it('resolves header nav links to real published page urls and never leaves a dead link', function () {
    // رگرسیون باگ گزارش‌شده: nav_links قبلاً یک URL آزاد (مثل '/about') ذخیره
    // می‌کرد که هرگز با مسیر واقعی /site/{slug}/{pageSlug} مطابقت نداشت.
    $company = publicSiteCompany('nav');
    $aboutPage = publicSitePage($company, [
        'slug' => 'about-us',
        'page_status' => PageStatus::Published->value,
        'content_html' => '<h1>درباره ما</h1>',
    ]);

    $header = LayoutDemo::create([
        'layout_type' => LayoutType::Header->value,
        'name' => 'هدر تست منو',
        'widget_tree' => [
            [
                'id' => 'nav',
                'widget_key' => WidgetKey::HeaderNav->value,
                'values' => ['nav_links' => [
                    ['label' => 'خانه', 'category_key' => 'home'],
                    ['label' => 'درباره ما', 'category_key' => 'about'],
                    ['label' => 'خدمات (بدون صفحه)', 'category_key' => 'services'],
                ]],
                'children' => [],
            ],
        ],
    ]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $aboutPage->id,
        'active_header_demo_id' => $header->id,
    ]);

    $response = $this->get(route('public-site.home', $company->slug));
    $response->assertOk();

    $expectedAboutHref = route('public-site.show', [$company->slug, $aboutPage->slug]);
    $expectedHomeHref = route('public-site.home', $company->slug);

    $response->assertSee('href="'.$expectedAboutHref.'"', false);
    $response->assertSee('href="'.$expectedHomeHref.'"', false);
    // آیتم منویی که هیچ صفحه‌ی منتشرشده‌ای در آن دسته ندارد، باید ساکت حذف
    // شود، نه اینکه یک لینک مرده رندر کند.
    $response->assertDontSee('خدمات (بدون صفحه)');

    // خودِ رگرسیون: کلیک واقعی روی href تولیدشده هرگز ۴۰۴ نمی‌دهد.
    $this->get($expectedAboutHref)->assertOk();
});

it('rejects a disallowed map embed domain on the public route the same way the widget renderer whitelist does', function () {
    $company = publicSiteCompany('map-domain');

    $widgetTree = [
        [
            'id' => 'evil-map',
            'widget_key' => WidgetKey::Map->value,
            'values' => ['embed_url' => 'https://evil.example.com/maps/embed?x=1'],
            'children' => [],
        ],
    ];

    $page = publicSitePage($company, [
        'page_status' => PageStatus::Published->value,
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('evil.example.com', false);
});

it('shows a real image uploaded through the page editor on the actual public URL, with the file physically retrievable', function () {
    // رگرسیون گزارش‌شده کاربر: عکس واقعی آپلودشده از فرم ادیتور در URL عمومی
    // نهایی نمایش داده نمی‌شود. این تست دقیقاً همان مسیر واقعی را طی می‌کند —
    // آپلود واقعی از طریق PageContentEditor (نه ساخت دستی widget_tree با یک
    // مسیر عکس فرضی)، ذخیره، سپس باز کردن URL عمومی واقعی — و هم حضور
    // <img src> معتبر را چک می‌کند هم اینکه فایل واقعاً روی دیسک storage
    // موجود است (نه فقط اینکه HTML رشته‌ی درستی دارد).
    Storage::fake('public');

    $company = publicSiteCompany('img-regression');

    $user = User::factory()->create(['is_super_admin' => false]);
    $role = Role::firstOrCreate(['name' => 'holding_admin'], ['display_name' => 'holding_admin', 'is_system' => true]);
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => $role->id,
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Image->value,
        'name' => 'تصویر',
        'default_config' => ['editable_fields' => [
            ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
        ]],
    ]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [[
            'id' => 'page-image',
            'widget_key' => WidgetKey::Image->value,
            'instance_label' => 'تصویر صفحه',
            'values' => ['image_path' => null],
            'children' => [],
        ]],
    ]);

    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-img',
    ], $user);

    $file = UploadedFile::fake()->image('cover.jpg');

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('imageUploads.page-image.image_path', $file)
        ->set('page_status', PageStatus::Published->value)
        ->call('save')
        ->assertHasNoErrors();

    $page->refresh();
    $storedPath = $page->widget_tree[0]['values']['image_path'];

    expect($storedPath)->not->toBeNull();
    Storage::disk('public')->assertExists($storedPath);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertSee('src="/storage/'.$storedPath.'"', false);

    // خودِ رگرسیون: مسیر ذخیره‌شده در content_html باید یک فایل واقعاً
    // موجود روی دیسک باشد، نه فقط یک رشته HTML قابل‌قبول.
    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();
});
