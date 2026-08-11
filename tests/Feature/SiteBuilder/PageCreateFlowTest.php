<?php

use App\Livewire\SiteBuilder\PageCreateFlow;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function pcfActingAsWithRole(string $roleName): array
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName, 'is_system' => true]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => $role->id,
    ]);

    test()->actingAs($user);
    session(['active_company_id' => $company->id]);

    return [$user, $company];
}

function pcfSeedWidgets(): void
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

function pcfMakeDemo(string $name = 'دموی تست اول'): PageDemo
{
    $category = PageCategory::firstOrCreate(
        ['category_key' => PageCategoryKey::About->value],
        ['name' => 'درباره ما']
    );

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => $name,
        'widget_tree' => [
            [
                'id' => 'hero-title',
                'widget_key' => WidgetKey::Title->value,
                'instance_label' => 'عنوان اصلی',
                'values' => ['text' => 'مقدار اولیه دمو', 'level' => 1],
                'children' => [],
            ],
            [
                'id' => 'hero-image',
                'widget_key' => WidgetKey::Image->value,
                'instance_label' => 'تصویر اصلی',
                'values' => ['image_path' => null, 'alt' => 'alt'],
                'children' => [],
            ],
        ],
    ]);
}

it('does not create any page record just by selecting a demo', function () {
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');
    $demo = pcfMakeDemo();

    Livewire::test(PageCreateFlow::class)->call('selectDemo', $demo->id);

    expect(Page::count())->toBe(0);
});

it('resets working state cleanly when switching between demos', function () {
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');
    $demoA = pcfMakeDemo('دموی الف');
    $demoB = pcfMakeDemo('دموی ب');

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demoA->id)
        ->set('fieldValues.hero-title.text', 'مقدار روی دموی الف')
        ->call('selectDemo', $demoB->id);

    expect($component->get('fieldValues')['hero-title']['text'])->toBe('مقدار اولیه دمو');
    expect($component->get('previewHtml'))->not->toContain('مقدار روی دموی الف');
    expect($component->get('selectedDemoId'))->toBe($demoB->id);
});

it('updates the live preview from in-memory field values without persisting anything', function () {
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');
    $demo = pcfMakeDemo();

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->set('fieldValues.hero-title.text', 'عنوان ویرایش‌شده در پیش‌نمایش')
        ->call('refreshPreview');

    expect($component->get('previewHtml'))->toContain('عنوان ویرایش‌شده در پیش‌نمایش');
    expect(Page::count())->toBe(0);
});

it('creates exactly one page record with the edited content on save', function () {
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');
    $demo = pcfMakeDemo();

    Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->set('title', 'صفحه نهایی')
        ->set('slug', 'final-page')
        ->set('fieldValues.hero-title.text', 'متن نهایی ذخیره‌شده')
        ->call('create')
        ->assertRedirect();

    expect(Page::count())->toBe(1);

    $page = Page::first();
    expect($page->title)->toBe('صفحه نهایی');
    expect($page->widget_tree[0]['values']['text'])->toBe('متن نهایی ذخیره‌شده');
    expect($page->content_html)->toContain('متن نهایی ذخیره‌شده');
});

it('renders a freshly selected but not-yet-saved image in the live preview via its temporary url', function () {
    Storage::fake('public');
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');
    $demo = pcfMakeDemo();

    $file = UploadedFile::fake()->image('cover.jpg');

    $component = Livewire::test(PageCreateFlow::class)
        ->call('selectDemo', $demo->id)
        ->set('imageUploads.hero-image.image_path', $file)
        ->call('refreshPreview');

    expect($component->get('previewHtml'))->toContain('<img');
    expect($component->get('previewHtml'))->not->toContain('sb-widget-image" src="">');

    // چیزی هنوز روی دیسک نهایی ذخیره نشده — فقط فایل موقت Livewire است.
    Storage::disk('public')->assertMissing('sitebuilder/images');
});

it('renders an already-saved image with a resolvable storage url in the preview', function () {
    Storage::fake('public');
    pcfSeedWidgets();
    pcfActingAsWithRole('holding_admin');

    $category = PageCategory::firstOrCreate(
        ['category_key' => PageCategoryKey::About->value],
        ['name' => 'درباره ما']
    );

    $storedPath = 'sitebuilder/images/already-saved.jpg';
    Storage::disk('public')->put($storedPath, 'fake-content');

    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی با تصویر ذخیره‌شده',
        'widget_tree' => [[
            'id' => 'hero-image',
            'widget_key' => WidgetKey::Image->value,
            'instance_label' => 'تصویر اصلی',
            'values' => ['image_path' => $storedPath, 'alt' => 'alt'],
            'children' => [],
        ]],
    ]);

    $component = Livewire::test(PageCreateFlow::class)->call('selectDemo', $demo->id);

    expect($component->get('previewHtml'))->toContain(Storage::disk('public')->url($storedPath));
});
