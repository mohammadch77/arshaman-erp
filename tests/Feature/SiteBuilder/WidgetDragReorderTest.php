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
use Livewire\Livewire;

function wdrMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function wdrActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => wdrMakeRole($roleName)->id,
    ]);

    return [$user, $company];
}

function wdrSeedWidgets(): void
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
}

function wdrDemo(): PageDemo
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی درگ‌اند‌دراپ',
        'widget_tree' => [
            [
                'id' => 'root-title',
                'widget_key' => WidgetKey::Title->value,
                'instance_label' => 'عنوان سطح بالا',
                'values' => ['text' => 'ROOT-TITLE-TEXT'],
                'children' => [],
            ],
            [
                'id' => 'container-a',
                'widget_key' => WidgetKey::Container->value,
                'instance_label' => 'محفظه آ',
                'values' => [],
                'children' => [
                    [
                        'id' => 'a1-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'عنوان آ۱',
                        'values' => ['text' => 'A1-TEXT'],
                        'children' => [],
                    ],
                ],
            ],
            [
                'id' => 'container-b',
                'widget_key' => WidgetKey::Container->value,
                'instance_label' => 'محفظه ب',
                'values' => [],
                'children' => [
                    [
                        'id' => 'b1-title',
                        'widget_key' => WidgetKey::Title->value,
                        'instance_label' => 'عنوان ب۱',
                        'values' => ['text' => 'B1-TEXT'],
                        'children' => [],
                    ],
                ],
            ],
        ],
    ]);
}

function wdrCreatePage(User $user, Company $company, PageDemo $demo): Page
{
    return app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'صفحه تست درگ',
        'slug' => 'drag-test',
    ], $user);
}

// --- PageContentEditor -------------------------------------------------

it('moves a top-level widget into a container and reflects the new order in widget_tree after save', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $page = wdrCreatePage($user, $company, wdrDemo());

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('moveWidgetNode', 'root-title', 'container-a', 0)
        ->call('save');

    $tree = $page->fresh()->widget_tree;

    expect(array_column($tree, 'id'))->toBe(['container-a', 'container-b']);
    expect(array_column($tree[0]['children'], 'id'))->toBe(['root-title', 'a1-title']);
});

it('moves a widget from one container to a different container', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $page = wdrCreatePage($user, $company, wdrDemo());

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('moveWidgetNode', 'a1-title', 'container-b', 1)
        ->call('save');

    $tree = $page->fresh()->widget_tree;
    $containerA = collect($tree)->firstWhere('id', 'container-a');
    $containerB = collect($tree)->firstWhere('id', 'container-b');

    expect($containerA['children'])->toBe([]);
    expect(array_column($containerB['children'], 'id'))->toBe(['b1-title', 'a1-title']);
});

it('rejects dropping a container inside itself, both in the live tree and if attempted directly', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $page = wdrCreatePage($user, $company, wdrDemo());

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('moveWidgetNode', 'container-a', 'container-a', 0);

    // ساختار درخت در حافظه دست‌نخورده مانده — حرکت رد شد
    expect(array_column($component->get('widgetTree'), 'id'))->toBe(['root-title', 'container-a', 'container-b']);

    $component->call('save');

    expect(array_column($page->fresh()->widget_tree, 'id'))->toBe(['root-title', 'container-a', 'container-b']);
});

it('never writes to the database before an explicit save, even after several drags', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $page = wdrCreatePage($user, $company, wdrDemo());
    $originalTree = $page->widget_tree;

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('moveWidgetNode', 'root-title', 'container-a', 0)
        ->call('moveWidgetNode', 'a1-title', 'container-b', 0)
        ->call('moveWidgetNode', 'container-a', null, 2);

    expect($page->fresh()->widget_tree)->toBe($originalTree);
});

it('updates the live preview immediately after a drop, before saving', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $page = wdrCreatePage($user, $company, wdrDemo());

    $before = Livewire::test(PageContentEditor::class, ['page' => $page->id]);
    $beforeHtml = $before->get('previewHtml');

    // در پیش‌نمایش اولیه، ROOT-TITLE-TEXT قبل از A1-TEXT می‌آید (سطح بالا قبل از محفظه آ)
    expect(strpos($beforeHtml, 'ROOT-TITLE-TEXT'))->toBeLessThan(strpos($beforeHtml, 'A1-TEXT'));

    $after = $before->call('moveWidgetNode', 'root-title', 'container-a', 0);
    $afterHtml = $after->get('previewHtml');

    // بعد از جابه‌جایی، ROOT-TITLE-TEXT داخل محفظه آ قبل از A1-TEXT قرار می‌گیرد —
    // ترتیب نسبی همچنان همان است، ولی محتوا واقعاً از سرور دوباره رندر شده (نه کش قدیمی).
    expect($afterHtml)->not->toBe($beforeHtml);
    expect($afterHtml)->toContain('ROOT-TITLE-TEXT');
    expect($afterHtml)->toContain('A1-TEXT');

    // رکورد دیتابیس دست‌نخورده مانده — پیش‌نمایش معادل ذخیره نیست.
    expect($page->fresh()->content_html)->not->toBe($afterHtml);
});

it('blocks an operator from reordering widgets on an already-published page', function () {
    wdrSeedWidgets();
    [$admin, $company] = wdrActingAsWithRole('holding_admin');
    $operator = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create([
        'user_id' => $operator->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => wdrMakeRole('operator')->id,
    ]);

    $page = wdrCreatePage($admin, $company, wdrDemo());
    $page->update(['page_status' => PageStatus::Published]);

    test()->actingAs($operator);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('moveWidgetNode', 'root-title', 'container-a', 0);

    expect(array_column($component->get('widgetTree'), 'id'))->toBe(['root-title', 'container-a', 'container-b']);
    expect($page->fresh()->widget_tree)->toBe($page->widget_tree);
});

// --- PageCreateFlow ------------------------------------------------------

it('reorders widgets in-memory during the create flow and persists the new order only on create', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $demo = wdrDemo();

    Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->call('moveWidgetNode', 'root-title', 'container-a', 0)
        ->assertSet('workingWidgetTree', function ($tree) {
            return array_column($tree, 'id') === ['container-a', 'container-b'];
        })
        ->set('title', 'صفحه ساخته‌شده با ترتیب جدید')
        ->set('slug', 'reordered-page')
        ->call('create');

    expect(Page::count())->toBe(1);

    $tree = Page::first()->widget_tree;
    expect(array_column($tree, 'id'))->toBe(['container-a', 'container-b']);
    expect(array_column($tree[0]['children'], 'id'))->toBe(['root-title', 'a1-title']);
});

it('rejects a container-into-itself move during the create flow too', function () {
    wdrSeedWidgets();
    [$user, $company] = wdrActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);
    $demo = wdrDemo();

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->call('moveWidgetNode', 'container-a', 'container-a', 0);

    expect(array_column($component->get('workingWidgetTree'), 'id'))->toBe(['root-title', 'container-a', 'container-b']);
});
