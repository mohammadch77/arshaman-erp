<?php

use App\Livewire\SiteBuilder\LayoutDemoSelector;
use App\Livewire\SiteBuilder\PageContentEditor;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Actions\UpdatePageWidgetValues;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function sbMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function sbGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => sbMakeRole($roleName)->id,
    ]);
}

function sbActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    sbGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function sbSeedWidgets(): void
{
    Widget::create([
        'widget_key' => WidgetKey::Container->value,
        'name' => 'محفظه',
        'default_config' => ['editable_fields' => []],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Title->value,
        'name' => 'عنوان',
        'default_config' => ['editable_fields' => [
            ['key' => 'text', 'type' => 'text', 'label' => 'متن عنوان'],
        ]],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Image->value,
        'name' => 'تصویر',
        'default_config' => ['editable_fields' => [
            ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
        ]],
    ]);
}

function sbAboutDemo(): PageDemo
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [
            [
                'id' => 'hero-title',
                'widget_key' => WidgetKey::Title->value,
                'instance_label' => 'عنوان اصلی صفحه',
                'values' => ['text' => 'عنوان اولیه', 'level' => 1],
                'children' => [],
            ],
            [
                'id' => 'content-container',
                'widget_key' => WidgetKey::Container->value,
                'instance_label' => 'محفظه بخش داستان',
                'values' => [],
                'children' => [
                    [
                        'id' => 'content-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'عنوان بخش داستان',
                        'values' => ['text' => 'داستان ما', 'level' => 2],
                        'children' => [],
                    ],
                    [
                        'id' => 'content-image',
                        'widget_key' => WidgetKey::Image->value,
                        'instance_label' => 'تصویر بخش داستان',
                        'values' => ['image_path' => null],
                        'children' => [],
                    ],
                ],
            ],
        ],
    ]);
}

it('copies the demo widget_tree exactly when creating a page from a demo', function () {
    [$user, $company] = sbActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    expect($page->widget_tree)->toBe($demo->widget_tree);
    expect($page->content_html)->not->toBe('');
    expect($page->content_html)->not->toBeNull();
});

it('updates only the targeted field value without changing widget_tree structure', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $updated = app(UpdatePageWidgetValues::class)->handle(
        $page,
        ['hero-title' => ['text' => 'عنوان جدید']],
        $user,
    );

    $originalNodeCount = fn (array $tree) => count($tree) + collect($tree)->sum(fn ($n) => count($n['children'] ?? []));

    expect($originalNodeCount($updated->widget_tree))->toBe($originalNodeCount($demo->widget_tree));
    expect($updated->widget_tree[0]['values']['text'])->toBe('عنوان جدید');
    expect($updated->content_html)->toContain('عنوان جدید');
});

it('ignores field keys not declared in editable_fields and never adds or removes nodes', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $updated = app(UpdatePageWidgetValues::class)->handle(
        $page,
        [
            'hero-title' => ['level' => 99],
            'nonexistent-node' => ['text' => 'باید نادیده گرفته شود'],
        ],
        $user,
    );

    expect($updated->widget_tree[0]['values']['level'])->toBe(1);
    expect(count($updated->widget_tree))->toBe(2);
});

it('never leaves content_html empty or null', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();

    $category = PageCategory::create(['category_key' => PageCategoryKey::Contact->value, 'name' => 'تماس']);
    $emptyDemo = PageDemo::create(['page_category_id' => $category->id, 'name' => 'دموی خالی', 'widget_tree' => []]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $emptyDemo->id,
        'title' => 'تماس با ما',
        'slug' => 'contact-us',
    ], $user);

    expect($page->content_html)->not->toBeNull();
    expect($page->content_html)->not->toBe('');
});

it('lets operator edit extra_css/extra_js on a published page but not publish it', function () {
    [$user, $company] = sbActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $admin = User::factory()->create(['is_super_admin' => false]);
    sbGiveRole($admin, $company, 'holding_admin');

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    app(UpdatePageWidgetValues::class)->handle($page, [], $admin, null, null, PageStatus::Published);
    $page->refresh();
    expect($page->page_status)->toBe(PageStatus::Published);

    // operator می‌تواند extra_css/extra_js را حتی روی صفحه‌ی published ویرایش کند
    $updated = app(UpdatePageWidgetValues::class)->handle($page, [], $user, 'body{color:red}', null);
    expect($updated->extra_css)->toBe('body{color:red}');

    // ولی نمی‌تواند مقادیر ویجت را عوض کند یا منتشر کند
    expect(fn () => app(UpdatePageWidgetValues::class)->handle($page, ['hero-title' => ['text' => 'x']], $user))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdatePageWidgetValues::class)->handle($page, [], $user, null, null, PageStatus::Draft))
        ->toThrow(AuthorizationException::class);
});

it('selects a header and footer layout demo in site settings', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $header = LayoutDemo::create(['layout_type' => LayoutType::Header->value, 'name' => 'هدر نمونه', 'widget_tree' => []]);
    $footer = LayoutDemo::create(['layout_type' => LayoutType::Footer->value, 'name' => 'فوتر نمونه', 'widget_tree' => []]);

    Livewire::test(LayoutDemoSelector::class)
        ->set('active_header_demo_id', $header->id)
        ->set('active_footer_demo_id', $footer->id)
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::where('owner_company_id', $company->id)->firstOrFail();
    expect($setting->active_header_demo_id)->toBe($header->id);
    expect($setting->active_footer_demo_id)->toBe($footer->id);
});

it('enforces company isolation on pages', function () {
    [$userA, $companyA] = sbActingAsWithRole('holding_admin');
    [, $companyB] = sbActingAsWithRole('holding_admin');
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $pageA = Page::withoutGlobalScopes()->create([
        'owner_company_id' => $companyA->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه شرکت آ',
        'slug' => 'company-a-page',
        'widget_tree' => $demo->widget_tree,
        'content_html' => '<div></div>',
    ]);

    $pageB = Page::withoutGlobalScopes()->create([
        'owner_company_id' => $companyB->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه شرکت ب',
        'slug' => 'company-b-page',
        'widget_tree' => $demo->widget_tree,
        'content_html' => '<div></div>',
    ]);

    $this->actingAs($userA);
    session(['active_company_id' => $companyA->id]);

    expect(Page::find($pageB->id))->toBeNull();
    expect(Page::find($pageA->id)?->id)->toBe($pageA->id);
});

it('logs and skips an unknown widget_key instead of rendering it', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        ['id' => 'x', 'widget_key' => 'not-a-real-widget', 'values' => ['text' => 'خطرناک'], 'children' => []],
    ]);

    expect($html)->not->toContain('خطرناک');
});

it('renders the page-content-editor form over a real HTTP request', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $this->get(route('sitebuilder.pages.edit', $page->id))->assertOk()->assertSee('درباره ما');
});

it('shows a distinct instance label for each occurrence of the same widget type', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    // این دمو دو نمونه از ویجت 'title' دارد (hero-title و content-title)؛
    // فرم باید instance_label اختصاصی هرکدام را نشان دهد، نه نام عمومی
    // تکراری نوع ویجت («عنوان» به‌تنهایی).
    $this->get(route('sitebuilder.pages.edit', $page->id))
        ->assertOk()
        ->assertSee('عنوان اصلی صفحه')
        ->assertSee('عنوان بخش داستان');
});

it('uploads a real image file and stores its path in the page widget_tree', function () {
    Storage::fake('public');

    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $file = UploadedFile::fake()->image('cover.jpg');

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('imageUploads.content-image.image_path', $file)
        ->call('save')
        ->assertHasNoErrors();

    $storedPath = $page->fresh()->widget_tree[1]['children'][1]['values']['image_path'];

    expect($storedPath)->not->toBeNull();
    Storage::disk('public')->assertExists($storedPath);
});

it('lets PageContentEditor update title/slug/meta of an existing page', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('title', 'درباره تیم ما')
        ->set('slug', 'about-our-team')
        ->set('meta_title', 'عنوان سئو جدید')
        ->set('meta_description', 'توضیح متای جدید')
        ->call('save')
        ->assertHasNoErrors();

    $page->refresh();

    expect($page->title)->toBe('درباره تیم ما')
        ->and($page->slug)->toBe('about-our-team')
        ->and($page->meta_title)->toBe('عنوان سئو جدید')
        ->and($page->meta_description)->toBe('توضیح متای جدید');
});

it('does not raise a false duplicate-slug error when saving without changing the slug', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('title', 'درباره ما (ویرایش‌شده)')
        ->call('save')
        ->assertHasNoErrors();

    expect($page->fresh()->slug)->toBe('about-us')
        ->and($page->fresh()->title)->toBe('درباره ما (ویرایش‌شده)');
});

it('rejects a slug that collides with another page in the same company', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه یک',
        'slug' => 'page-one',
    ], $user);

    $pageTwo = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه دو',
        'slug' => 'page-two',
    ], $user);

    Livewire::test(PageContentEditor::class, ['page' => $pageTwo->id])
        ->set('slug', 'page-one')
        ->call('save')
        ->assertHasErrors(['slug']);
});

it('reflects a published page slug change at the new public URL', function () {
    [$user, $company] = sbActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    sbSeedWidgets();
    $demo = sbAboutDemo();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $page->update(['page_status' => PageStatus::Published]);

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('slug', 'about-us-new')
        ->call('save')
        ->assertHasNoErrors();

    $this->get(route('public-site.show', [$company->slug, 'about-us-new']))->assertOk();
    $this->get(route('public-site.show', [$company->slug, 'about-us']))->assertNotFound();
});
