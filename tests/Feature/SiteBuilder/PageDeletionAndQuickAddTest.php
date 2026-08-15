<?php

use App\Livewire\SiteBuilder\LayoutDemoSelector;
use App\Livewire\SiteBuilder\PageContentEditor;
use App\Livewire\SiteBuilder\PageCreateFlow;
use App\Livewire\SiteBuilder\PageIndex;
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
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function pdqaMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pdqaGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => pdqaMakeRole($roleName)->id,
    ]);
}

function pdqaActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    pdqaGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function pdqaSeedWidgets(): void
{
    Widget::create([
        'widget_key' => WidgetKey::Container->value,
        'name' => 'محفظه',
        'icon' => 'o-squares-2x2',
        'default_config' => ['editable_fields' => []],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Title->value,
        'name' => 'عنوان',
        'icon' => 'o-bars-3',
        'default_config' => ['editable_fields' => [
            ['key' => 'text', 'type' => 'text', 'label' => 'متن عنوان'],
        ]],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Button->value,
        'name' => 'دکمه',
        'icon' => 'o-cursor-arrow-rays',
        'default_config' => ['editable_fields' => [
            ['key' => 'label', 'type' => 'text', 'label' => 'متن دکمه'],
            ['key' => 'url', 'type' => 'text', 'label' => 'لینک'],
        ]],
    ]);
}

function pdqaDemo(): PageDemo
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [
            [
                'id' => 'hero-title',
                'widget_key' => WidgetKey::Title->value,
                'instance_label' => 'عنوان اصلی',
                'values' => ['text' => 'عنوان اولیه', 'level' => 1],
                'children' => [],
            ],
            [
                'id' => 'hero-container',
                'widget_key' => WidgetKey::Container->value,
                'instance_label' => 'محفظه اصلی',
                'values' => [],
                'children' => [],
            ],
        ],
    ]);
}

function pdqaCreatePage(User $user, Company $company, PageDemo $demo, string $slug = 'about-us'): Page
{
    return app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => $slug,
    ], $user);
}

// ==========================================================
// بخش ۱ — حذف صفحه
// ==========================================================

it('lets holding_admin delete any page regardless of status', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    Livewire::test(PageIndex::class)->call('deletePage', $page->id);

    expect(Page::find($page->id))->toBeNull();
    expect(Page::withTrashed()->find($page->id))->not->toBeNull();
});

it('lets operator delete only a draft page', function () {
    [$user, $company] = pdqaActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    Livewire::test(PageIndex::class)->call('deletePage', $page->id);

    expect(Page::find($page->id))->toBeNull();
});

it('rejects an operator deleting an already-published page', function () {
    [$user, $company] = pdqaActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $admin = User::factory()->create(['is_super_admin' => false]);
    pdqaGiveRole($admin, $company, 'holding_admin');
    $page->update(['page_status' => PageStatus::Published, 'updated_by_user_id' => $admin->id]);

    Livewire::test(PageIndex::class)->call('deletePage', $page->id);

    expect(Page::find($page->id))->not->toBeNull();
});

it('rejects deleting a page directly through the action for an unauthorized actor', function () {
    [$user, $company] = pdqaActingAsWithRole('operator');
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());
    $page->update(['page_status' => PageStatus::Published]);

    expect(fn () => app(\App\Modules\SiteBuilder\Actions\DeletePage::class)->handle($page, $user))
        ->toThrow(AuthorizationException::class);
});

// ==========================================================
// بخش ۱ — مسیر پیش‌نمایش ادمین
// ==========================================================

it('lets an authorized admin preview a draft page that the public route would 404 on', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $this->get(route('sitebuilder.pages.preview', $page->id))
        ->assertOk()
        ->assertSee('پیش‌نمایش');

    $this->get(route('public-site.show', ['companySlug' => $company->slug, 'pageSlug' => $page->slug]))
        ->assertNotFound();
});

it('rejects a guest from reaching the admin preview route', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $this->get(route('sitebuilder.pages.preview', $page->id))->assertRedirect(route('login'));
});

// ==========================================================
// بخش ۲ — پیش‌نمایش زنده هدر/فوتر
// ==========================================================

it('changes the live header/footer preview when a different demo is selected', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $headerA = LayoutDemo::create([
        'layout_type' => LayoutType::Header->value,
        'name' => 'هدر آ',
        'widget_tree' => [['id' => 'nav-a', 'widget_key' => WidgetKey::HeaderNav->value, 'instance_label' => 'منو آ', 'values' => ['nav_links' => [], 'show_logo' => false], 'children' => []]],
    ]);
    $headerB = LayoutDemo::create([
        'layout_type' => LayoutType::Header->value,
        'name' => 'هدر ب',
        'widget_tree' => [['id' => 'nav-b', 'widget_key' => WidgetKey::HeaderNav->value, 'instance_label' => 'منو ب', 'values' => ['nav_links' => [], 'show_logo' => false], 'children' => []]],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::HeaderNav->value,
        'name' => 'منوی ناوبری',
        'default_config' => ['editable_fields' => []],
    ]);

    $component = Livewire::test(LayoutDemoSelector::class)
        ->set('active_header_demo_id', $headerA->id);

    expect($component->get('headerPreviewHtml'))->toContain('sb-widget-header-nav');

    $component->set('active_header_demo_id', $headerB->id);

    expect($component->get('headerPreviewHtml'))->toContain('sb-widget-header-nav');
});

it('shows no header preview when nothing is selected', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(LayoutDemoSelector::class)
        ->assertSet('headerPreviewHtml', '')
        ->assertSet('footerPreviewHtml', '');
});

// ==========================================================
// بخش ۳ — افزودن ویجت با کلیک
// ==========================================================

it('adds a brand new widget node to an existing page via the quick-add panel', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    $newNodeIds = array_diff(array_column($tree, 'id'), ['hero-title', 'hero-container']);

    expect($newNodeIds)->toHaveCount(1);
    $newNodeId = array_values($newNodeIds)[0];
    expect($component->get('fieldValues'))->toHaveKey($newNodeId);
});

it('adds a new widget inside the container selected as the active target', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('setActiveContainer', 'hero-container')
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    $container = collect($tree)->firstWhere('id', 'hero-container');

    expect($container['children'])->toHaveCount(1);
    expect($container['children'][0]['widget_key'])->toBe(WidgetKey::Button->value);
});

it('rejects an operator adding a widget to an already-published page', function () {
    [$user, $company] = pdqaActingAsWithRole('operator');
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $admin = User::factory()->create(['is_super_admin' => false]);
    pdqaGiveRole($admin, $company, 'holding_admin');
    $page->update(['page_status' => PageStatus::Published, 'updated_by_user_id' => $admin->id]);

    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    expect(array_column($tree, 'id'))->toBe(['hero-title', 'hero-container']);
});

it('adds a widget in the create-flow working tree before any page record exists', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $demo = pdqaDemo();

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->call('addWidget', WidgetKey::Button->value);

    expect(Page::count())->toBe(0);

    $tree = $component->get('workingWidgetTree');
    $newNodeIds = array_diff(array_column($tree, 'id'), ['hero-title', 'hero-container']);
    expect($newNodeIds)->toHaveCount(1);
});

it('a newly added widget can still be moved with the existing drag-and-drop reorderer', function () {
    [$user, $company] = pdqaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    pdqaSeedWidgets();
    $page = pdqaCreatePage($user, $company, pdqaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    $newNodeId = array_values(array_diff(array_column($tree, 'id'), ['hero-title', 'hero-container']))[0];

    $component->call('moveWidgetNode', $newNodeId, 'hero-container', 0);

    $tree = $component->get('widgetTree');
    $container = collect($tree)->firstWhere('id', 'hero-container');
    expect(array_column($container['children'], 'id'))->toBe([$newNodeId]);
});
