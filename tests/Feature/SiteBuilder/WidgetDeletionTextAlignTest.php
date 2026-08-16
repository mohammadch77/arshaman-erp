<?php

use App\Livewire\SiteBuilder\PageContentEditor;
use App\Livewire\SiteBuilder\PageCreateFlow;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use App\Modules\SiteBuilder\Services\WidgetTreeValueMerger;
use Database\Seeders\SiteBuilderRemoveIconWidgetSeeder;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

function wdtaMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function wdtaGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => wdtaMakeRole($roleName)->id,
    ]);
}

function wdtaActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    wdtaGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function wdtaSeedWidgets(): void
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
            ['key' => 'text_align', 'type' => 'select', 'label' => 'تراز متن', 'default' => 'right', 'options' => [
                ['value' => 'right', 'label' => 'راست‌چین'],
                ['value' => 'left', 'label' => 'چپ‌چین'],
                ['value' => 'center', 'label' => 'وسط‌چین'],
            ]],
        ]],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::Button->value,
        'name' => 'دکمه',
        'default_config' => ['editable_fields' => [
            ['key' => 'label', 'type' => 'text', 'label' => 'متن دکمه'],
            ['key' => 'url', 'type' => 'text', 'label' => 'لینک'],
            ['key' => 'text_align', 'type' => 'select', 'label' => 'تراز', 'default' => 'right', 'options' => [
                ['value' => 'right', 'label' => 'راست‌چین'],
                ['value' => 'left', 'label' => 'چپ‌چین'],
                ['value' => 'center', 'label' => 'وسط‌چین'],
            ]],
        ]],
    ]);

    Widget::create([
        'widget_key' => WidgetKey::ContactForm->value,
        'name' => 'فرم تماس',
        'default_config' => ['editable_fields' => [
            ['key' => 'section_title', 'type' => 'text', 'label' => 'عنوان بالای فرم'],
        ]],
    ]);
}

function wdtaDemo(): PageDemo
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
                'values' => ['text' => 'عنوان اولیه'],
                'children' => [],
            ],
            [
                'id' => 'hero-container',
                'widget_key' => WidgetKey::Container->value,
                'instance_label' => 'محفظه اصلی',
                'values' => [],
                'children' => [
                    [
                        'id' => 'nested-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'عنوان تودرتو',
                        'values' => ['text' => 'عنوان تودرتو'],
                        'children' => [],
                    ],
                ],
            ],
        ],
    ]);
}

function wdtaCreatePage(User $user, Company $company, PageDemo $demo, string $slug = 'about-us'): Page
{
    return app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => $slug,
    ], $user);
}

// ==========================================================
// حذف تکی ویجت
// ==========================================================

it('deletes a leaf widget from an existing page via PageContentEditor', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('deleteWidget', 'hero-title');

    $tree = $component->get('widgetTree');
    expect(array_column($tree, 'id'))->toBe(['hero-container']);
});

it('deletes a container and all of its children together', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('deleteWidget', 'hero-container');

    $tree = $component->get('widgetTree');
    expect(array_column($tree, 'id'))->toBe(['hero-title']);
});

it('persists the deletion after save', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('deleteWidget', 'hero-title')
        ->call('save');

    $page->refresh();
    expect(array_column($page->widget_tree, 'id'))->toBe(['hero-container']);
});

it('rejects an operator deleting a widget from an already-published page', function () {
    [$user, $company] = wdtaActingAsWithRole('operator');
    wdtaSeedWidgets();
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    $admin = User::factory()->create(['is_super_admin' => false]);
    wdtaGiveRole($admin, $company, 'holding_admin');
    $page->update(['page_status' => PageStatus::Published, 'updated_by_user_id' => $admin->id]);

    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('deleteWidget', 'hero-title');

    $tree = $component->get('widgetTree');
    expect(array_column($tree, 'id'))->toBe(['hero-title', 'hero-container']);
});

it('deletes a widget in the create-flow working tree before any page record exists', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    $demo = wdtaDemo();

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->call('deleteWidget', 'hero-title');

    $tree = $component->get('workingWidgetTree');
    expect(array_column($tree, 'id'))->toBe(['hero-container']);
    expect(Page::count())->toBe(0);
});

// ==========================================================
// تراز متن (text_align)
// ==========================================================

it('applies text-align style to a title widget from the text_align field', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 't1', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'سلام', 'text_align' => 'center'], 'children' => []],
    ]);

    expect($html)->toContain('style="text-align:center;"');
});

it('ignores an invalid text_align value and renders without an inline style', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 't1', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'سلام', 'text_align' => 'javascript:alert(1)'], 'children' => []],
    ]);

    expect($html)->not->toContain('javascript:alert');
    expect($html)->not->toContain('style="text-align');
});

it('positions a button via a wrapping div text-align instead of the anchor itself', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'b1', 'widget_key' => WidgetKey::Button->value, 'values' => ['label' => 'کلیک', 'url' => 'https://example.com', 'text_align' => 'left'], 'children' => []],
    ]);

    expect($html)->toContain('<div class="sb-widget" style="text-align:left;">');
    expect($html)->toContain('<a class="sb-widget-button');
});

it('applies text-align to a text_editor widget', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'te1', 'widget_key' => WidgetKey::TextEditor->value, 'values' => ['html' => '<p>متن</p>', 'text_align' => 'left'], 'children' => []],
    ]);

    expect($html)->toContain('class="sb-widget sb-widget-text-editor" style="text-align:left;"');
});

it('passes the text_align field through WidgetTreeValueMerger like any other declared field', function () {
    wdtaSeedWidgets();

    $nodes = [
        ['id' => 't1', 'widget_key' => WidgetKey::Title->value, 'values' => ['text' => 'سلام', 'text_align' => 'right'], 'children' => []],
    ];

    $merged = app(WidgetTreeValueMerger::class)->apply($nodes, ['t1' => ['text' => 'سلام', 'text_align' => 'center']]);

    expect($merged[0]['values']['text_align'])->toBe('center');
});

// ==========================================================
// حذف کامل ویجت icon از کاتالوگ
// ==========================================================

it('no longer defines an Icon case in the WidgetKey enum', function () {
    expect(array_column(WidgetKey::cases(), 'value'))->not->toContain('icon');
});

it('no longer lists icon in the quick-add widgets config', function () {
    expect(Config::get('sitebuilder.quick_add_widgets'))->not->toContain('icon');
});

it('silently skips a legacy icon widget node instead of throwing, same as any other unknown widget_key', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'legacy-icon', 'widget_key' => 'icon', 'values' => ['icon_name' => 'o-star'], 'children' => []],
    ]);

    expect($html)->not->toContain('<svg');
});

it('removes any pre-existing icon widget catalog row via the cleanup seeder', function () {
    Widget::create([
        'widget_key' => 'icon',
        'name' => 'آیکون',
        'default_config' => ['editable_fields' => []],
    ]);

    $this->seed(SiteBuilderRemoveIconWidgetSeeder::class);

    expect(Widget::where('widget_key', 'icon')->exists())->toBeFalse();
});

// ==========================================================
// بخش ۱ گزارش — رگرسیون: این فیلدها/رندرها از قبل درست کار می‌کردند
// (طبق بازتولید دستی در مرورگر واقعی؛ اینجا قفل می‌شوند تا مسیر رگرسیون
// نکنند)
// ==========================================================

it('persists a typed alt text on a freshly added image widget through save', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    Widget::create([
        'widget_key' => WidgetKey::Image->value,
        'name' => 'تصویر',
        'default_config' => ['editable_fields' => [
            ['key' => 'image_path', 'type' => 'image', 'label' => 'تصویر'],
            ['key' => 'alt', 'type' => 'text', 'label' => 'متن جایگزین تصویر'],
        ]],
    ]);
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Image->value);

    $tree = $component->get('widgetTree');
    $newNodeId = array_values(array_diff(array_column($tree, 'id'), ['hero-title', 'hero-container']))[0];

    $component->set("fieldValues.{$newNodeId}.alt", 'متن جایگزین واقعی')->call('save');

    $page->refresh();
    $imageNode = collect($page->widget_tree)->firstWhere('id', $newNodeId);
    expect($imageNode['values']['alt'])->toBe('متن جایگزین واقعی');
});

it('persists a typed section_title on the contact_form widget and renders it in the marker config', function () {
    [$user, $company] = wdtaActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    wdtaSeedWidgets();
    $page = wdtaCreatePage($user, $company, wdtaDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::ContactForm->value);

    $tree = $component->get('widgetTree');
    $newNodeId = array_values(array_diff(array_column($tree, 'id'), ['hero-title', 'hero-container']))[0];

    $component->set("fieldValues.{$newNodeId}.section_title", 'تماس با ما')->call('save');

    $page->refresh();
    expect($page->content_html)->toContain('sb-widget-contact-form-title');
    expect($page->content_html)->toContain('تماس با ما');
});

it('renders a button with label and url in the final content_html', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'b1', 'widget_key' => WidgetKey::Button->value, 'values' => ['label' => 'ارسال', 'url' => 'https://example.com'], 'children' => []],
    ]);

    expect($html)->toContain('ارسال');
    expect($html)->toContain('href="https://example.com"');
});
