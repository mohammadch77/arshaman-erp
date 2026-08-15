<?php

use App\Livewire\CRM\Public\ContactForm as PublicContactForm;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSubmission;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Livewire\Livewire;

function integratedWidgetsCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'آرشامان'.$suffix,
        'slug' => 'arshaman-iw-'.($suffix ?: uniqid()),
        'business_type' => 'project_services',
    ]);
}

function integratedWidgetsBlogPost(Company $company, array $overrides = []): BlogPost
{
    $author = User::factory()->create(['is_super_admin' => false]);

    return BlogPost::create(array_merge([
        'owner_company_id' => $company->id,
        'author_user_id' => $author->id,
        'title' => 'عنوان پست تستی',
        'slug' => 'iw-post-'.uniqid(),
        'meta_title' => null,
        'meta_description' => null,
        'content_html' => '<p>متن نمونه پست برای آزمایش خلاصه‌سازی و طول آن که باید تا حدی بریده شود.</p>',
        'post_status' => 'draft',
        'published_at' => null,
    ], $overrides));
}

function integratedWidgetsPage(Company $company, array $widgetTree, array $overrides = []): Page
{
    $category = PageCategory::firstOrCreate(
        ['category_key' => 'contact'],
        ['name' => 'تماس']
    );

    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی تست یکپارچه‌سازی '.uniqid(),
        'widget_tree' => $widgetTree,
    ]);

    return Page::create(array_merge([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه تست',
        'slug' => 'iw-page-'.uniqid(),
        'widget_tree' => $widgetTree,
        'content_html' => app(WidgetContentRenderer::class)->render($widgetTree),
        'page_status' => PageStatus::Published->value,
    ], $overrides));
}

// ------------------------------------------------------------------
// contact_form
// ------------------------------------------------------------------

it('embeds the real hydrated contact form component on the public page, not the static marker', function () {
    $company = integratedWidgetsCompany('form');
    $page = integratedWidgetsPage($company, [
        ['id' => 'form', 'widget_key' => WidgetKey::ContactForm->value, 'values' => ['section_title' => 'تماس با ما'], 'children' => []],
    ]);

    // content_html ذخیره‌شده باید فقط marker باشد، هرگز کامپوننت واقعی —
    // نگاه کن DynamicWidgetResolver (فقط در لحظه‌ی درخواست عمومی resolve می‌شود).
    expect($page->content_html)->toContain('<!--sb:contact_form:');

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('<!--sb:contact_form-->', false);
    $response->assertSee('تماس با ما');
    // فیلدهای واقعی فرم Livewire (نام مشتری در view کامپوننت).
    $response->assertSee('wire:model', false);
    $response->assertSee('full_name', false);
});

it('creates a company-scoped ContactSubmission when the embedded form is actually submitted', function () {
    $company = integratedWidgetsCompany('submit');
    integratedWidgetsPage($company, [
        ['id' => 'form', 'widget_key' => WidgetKey::ContactForm->value, 'values' => [], 'children' => []],
    ]);

    // دقیقاً همان کامپوننتی که DynamicWidgetResolver embed می‌کند
    // (crm.public.contact-form با همان companySlug) — بدون کپی منطق ارسال.
    Livewire::test(PublicContactForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'کاربر تستی')
        ->set('phone', '09121234567')
        ->set('message', 'پیام تستی از طریق ویجت سایت‌ساز')
        ->call('submit')
        ->assertHasNoErrors();

    $submission = ContactSubmission::where('owner_company_id', $company->id)->first();

    expect($submission)->not->toBeNull();
    expect($submission->full_name)->toBe('کاربر تستی');
});

// ------------------------------------------------------------------
// blog_post_list
// ------------------------------------------------------------------

it('shows only the real published posts of the same company, respecting posts_count, with correct links', function () {
    $companyA = integratedWidgetsCompany('blog-a');
    $companyB = integratedWidgetsCompany('blog-b');

    $published1 = integratedWidgetsBlogPost($companyA, ['title' => 'پست منتشرشده یک', 'post_status' => 'published', 'published_at' => now()->subDays(2)]);
    $published2 = integratedWidgetsBlogPost($companyA, ['title' => 'پست منتشرشده دو', 'post_status' => 'published', 'published_at' => now()->subDay()]);
    integratedWidgetsBlogPost($companyA, ['title' => 'پست پیش‌نویس', 'post_status' => 'draft']);
    integratedWidgetsBlogPost($companyA, ['title' => 'پست زمان‌بندی‌شده آینده', 'post_status' => 'scheduled', 'published_at' => now()->addDay()]);
    integratedWidgetsBlogPost($companyB, ['title' => 'پست شرکت دیگر', 'post_status' => 'published', 'published_at' => now()->subHour()]);

    $page = integratedWidgetsPage($companyA, [
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '2', 'section_title' => 'آخرین مطالب'], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$companyA->slug, $page->slug]));

    $response->assertOk();
    $response->assertSee('آخرین مطالب');
    $response->assertSee('پست منتشرشده یک');
    $response->assertSee('پست منتشرشده دو');
    $response->assertDontSee('پست پیش‌نویس');
    $response->assertDontSee('پست زمان‌بندی‌شده آینده');
    $response->assertDontSee('پست شرکت دیگر');

    $response->assertSee('href="'.route('public-blog.show', [$companyA->slug, $published1->slug]).'"', false);
    $response->assertSee('href="'.route('public-blog.show', [$companyA->slug, $published2->slug]).'"', false);
});

it('respects a smaller posts_count and never renders more cards than configured', function () {
    $company = integratedWidgetsCompany('blog-count');

    foreach (range(1, 5) as $i) {
        integratedWidgetsBlogPost($company, [
            'title' => 'پست شماره '.$i,
            'post_status' => 'published',
            'published_at' => now()->subMinutes($i),
        ]);
    }

    $page = integratedWidgetsPage($company, [
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '1', 'section_title' => ''], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    expect(substr_count($response->getContent(), '<a class="sb-blog-post-card"'))->toBe(1);
});

it('shows a friendly empty state instead of a broken grid when the company has no published posts', function () {
    $company = integratedWidgetsCompany('blog-empty');

    $page = integratedWidgetsPage($company, [
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '3', 'section_title' => ''], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertSee('هنوز هیچ پستی منتشر نشده است');
});

// ------------------------------------------------------------------
// امنیت
// ------------------------------------------------------------------

it('escapes an XSS payload in the section_title field of both new widgets', function () {
    $company = integratedWidgetsCompany('xss-title');
    $payload = '<script>alert(1)</script>';

    integratedWidgetsBlogPost($company, ['title' => 'پست معمولی', 'post_status' => 'published', 'published_at' => now()->subDay()]);

    $page = integratedWidgetsPage($company, [
        ['id' => 'form', 'widget_key' => WidgetKey::ContactForm->value, 'values' => ['section_title' => $payload], 'children' => []],
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '3', 'section_title' => $payload], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('<script>alert(1)</script>', false);
});

it('cannot be broken out of its HTML comment marker by a section_title containing an HTML comment terminator', function () {
    // اگر پیکربندی blog_post_list خام (نه base64) داخل کامنت قرار می‌گرفت،
    // یک عنوان حاوی '-->' می‌توانست از کامنت خارج بزند و HTML دلخواه تزریق کند.
    $company = integratedWidgetsCompany('xss-comment-break');
    $payload = '--><img src=x onerror=alert(1)>';

    integratedWidgetsBlogPost($company, ['title' => 'پست معمولی', 'post_status' => 'published', 'published_at' => now()->subDay()]);

    $page = integratedWidgetsPage($company, [
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '3', 'section_title' => $payload], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('<img src=x onerror=alert(1)>', false);
});

it('escapes an XSS payload in a real blog post title/excerpt rendered by the live widget', function () {
    $company = integratedWidgetsCompany('xss-post');
    $payload = '<script>alert(2)</script>';

    integratedWidgetsBlogPost($company, [
        'title' => $payload,
        'content_html' => '<p>'.$payload.'</p>',
        'post_status' => 'published',
        'published_at' => now()->subHour(),
    ]);

    $page = integratedWidgetsPage($company, [
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '3', 'section_title' => ''], 'children' => []],
    ]);

    $response = $this->get(route('public-site.show', [$company->slug, $page->slug]));

    $response->assertOk();
    $response->assertDontSee('<script>alert(2)</script>', false);
});

// ------------------------------------------------------------------
// پیش‌نمایش ادمین / content_html ذخیره‌شده هرگز داده‌ی زنده ندارد
// ------------------------------------------------------------------

it('never bakes real blog posts or a hydrated form into the stored content_html snapshot', function () {
    $company = integratedWidgetsCompany('snapshot');
    integratedWidgetsBlogPost($company, ['title' => 'پستی که نباید در اسنپ‌شات باشد', 'post_status' => 'published', 'published_at' => now()->subDay()]);

    $page = integratedWidgetsPage($company, [
        ['id' => 'form', 'widget_key' => WidgetKey::ContactForm->value, 'values' => [], 'children' => []],
        ['id' => 'list', 'widget_key' => WidgetKey::BlogPostList->value, 'values' => ['posts_count' => '3', 'section_title' => ''], 'children' => []],
    ]);

    expect($page->content_html)->toContain('<!--sb:contact_form:');
    expect($page->content_html)->toContain('<!--sb:blog_post_list:');
    expect($page->content_html)->not->toContain('پستی که نباید در اسنپ‌شات باشد');
    expect($page->content_html)->not->toContain('wire:id');
});
