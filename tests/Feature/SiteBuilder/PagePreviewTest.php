<?php

use App\Livewire\SiteBuilder\PageContentEditor;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use Livewire\Livewire;

function pvMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pvActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => pvMakeRole($roleName)->id,
    ]);

    return [$user, $company];
}

function pvSeedWidgets(): void
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

function pvAboutPage(): Page
{
    $category = PageCategory::create(['category_key' => PageCategoryKey::About->value, 'name' => 'درباره ما']);

    $demo = PageDemo::create([
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
        ],
    ]);

    [$user, $company] = pvActingAsWithRole('holding_admin');
    test()->actingAs($user);
    session(['active_company_id' => $company->id]);

    return app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);
}

it('updates the live preview without touching the persisted page record', function () {
    pvSeedWidgets();
    $page = pvAboutPage();

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('fieldValues.hero-title.text', 'عنوان جدید در پیش‌نمایش')
        ->call('refreshPreview');

    expect($component->get('previewHtml'))->toContain('عنوان جدید در پیش‌نمایش');

    // رکورد واقعی در دیتابیس دست‌نخورده مانده — پیش‌نمایش معادل ذخیره نیست.
    $page->refresh();
    expect($page->widget_tree[0]['values']['text'])->toBe('عنوان اولیه');
    expect($page->content_html)->not->toContain('عنوان جدید در پیش‌نمایش');
});

it('escapes the same XSS payload identically in the live preview and the final persisted render', function () {
    pvSeedWidgets();
    $page = pvAboutPage();

    $payload = '<script>alert(1)</script>';

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('fieldValues.hero-title.text', $payload)
        ->call('refreshPreview');

    $previewHtml = $component->get('previewHtml');

    expect($previewHtml)->not->toContain('<script>alert(1)</script>');
    expect($previewHtml)->toContain(e($payload));

    // ذخیره واقعی همان مقدار را با WidgetContentRenderer رندر می‌کند —
    // پیش‌نمایش و رندر نهایی باید دقیقاً یک منبع escape داشته باشند.
    $component->call('save');

    expect($page->fresh()->content_html)->toContain(e($payload));
    expect($page->fresh()->content_html)->not->toContain('<script>alert(1)</script>');
});

it('keeps the preview consistent after rapid successive refreshPreview calls, matching only the last value', function () {
    pvSeedWidgets();
    $page = pvAboutPage();

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id]);

    foreach (['اول', 'دوم', 'سوم', 'چهارم', 'پنجم'] as $value) {
        $component->set('fieldValues.hero-title.text', $value)->call('refreshPreview');
    }

    expect($component->get('previewHtml'))->toContain('پنجم');
    expect($component->get('previewHtml'))->not->toContain('اول')
        ->and($component->get('previewHtml'))->not->toContain('دوم')
        ->and($component->get('previewHtml'))->not->toContain('سوم')
        ->and($component->get('previewHtml'))->not->toContain('چهارم');

    // هیچ‌کدام از این چرخه‌های سریع چیزی در دیتابیس ذخیره نکرده‌اند.
    expect($page->fresh()->widget_tree[0]['values']['text'])->toBe('عنوان اولیه');
});

it('populates an initial preview on mount before any field is touched', function () {
    pvSeedWidgets();
    $page = pvAboutPage();

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id]);

    expect($component->get('previewHtml'))->toContain('عنوان اولیه');
});
