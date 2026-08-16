<?php

use App\Livewire\SiteBuilder\LayoutDemoSelector;
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
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * دو باگ گزارش‌شده در ماژول SiteBuilder:
 * ۱. input آپلود لوگو/فاوآیکون در تنظیمات سایت اصلاً دیده نمی‌شد.
 * ۲. ویجت button در پیش‌نمایش/سایت عمومی رندر نمی‌شد (بازتولید نشد، ولی
 *    یک تست end-to-end واقعی برای این کلاس اضافه شد تا رگرسیون احتمالی
 *    آینده را بگیرد).
 */
function ssbwMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function ssbwActingAsHoldingAdmin(): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => ssbwMakeRole('holding_admin')->id,
    ]);

    return [$user, $company];
}

// ==========================================================
// باگ ۱ — آپلود لوگو/فاوآیکون
// ==========================================================

it('renders a real, clickable file input for the logo field — not hidden by a non-empty slot', function () {
    [$user, $company] = ssbwActingAsHoldingAdmin();
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    // فروشگاه هنوز هیچ لوگویی ندارد (تازه‌ساز)، پس شرط
    // $existingLogoUrl && ! $logo باید false باشد و <x-file> باید بدون
    // slot رندر شود — نه با یک @if خالی داخل slot که همیشه یک کامنت
    // Blade باقی می‌گذارد و ورودی واقعی را با کلاس hidden مخفی می‌کند.
    $html = (string) Livewire::test(LayoutDemoSelector::class)->html();

    expect($html)->toContain('type="file"');

    // استخراج تگ اولین input[type=file] (لوگو) و اطمینان از نبود کلاس hidden رویش.
    preg_match('/<input[^>]*type="file"[^>]*>/', $html, $matches);
    expect($matches)->not->toBeEmpty();
    expect($matches[0])->not->toContain('hidden');
});

it('uploads, saves, and persists a real logo file that is then loadable from storage', function () {
    Storage::fake('public');

    [$user, $company] = ssbwActingAsHoldingAdmin();
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $logo = UploadedFile::fake()->image('logo.png', 100, 100);

    Livewire::test(LayoutDemoSelector::class)
        ->set('logo', $logo)
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::withoutGlobalScopes()->where('owner_company_id', $company->id)->firstOrFail();

    expect($setting->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($setting->logo_path);
});

it('shows the existing logo preview (with the real file input still reachable via change) after a save', function () {
    Storage::fake('public');

    [$user, $company] = ssbwActingAsHoldingAdmin();
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'logo_path' => 'sitebuilder/branding/existing-logo.png',
    ]);
    Storage::disk('public')->put('sitebuilder/branding/existing-logo.png', 'fake-content');

    $html = (string) Livewire::test(LayoutDemoSelector::class)->html();

    // این‌بار slot واقعاً پر است (یک <img> واقعی)، پس مخفی‌شدن input درست است —
    // چون خودِ preview روی click کلیک به input واقعی را دوباره باز می‌کند.
    expect($html)->toContain('existing-logo.png');
});

// ==========================================================
// باگ ۲ — ویجت button
// ==========================================================

function ssbwSeedButtonWidget(): void
{
    Widget::firstOrCreate(['widget_key' => WidgetKey::Button->value], [
        'name' => 'دکمه',
        'icon' => 'o-cursor-arrow-rays',
        'default_config' => ['editable_fields' => [
            ['key' => 'label', 'type' => 'text', 'label' => 'متن دکمه'],
            ['key' => 'url', 'type' => 'text', 'label' => 'لینک'],
            ['key' => 'style', 'type' => 'select', 'label' => 'سبک', 'options' => [
                ['value' => 'primary', 'label' => 'اصلی'],
                ['value' => 'outline', 'label' => 'خط‌دور'],
            ]],
            ['key' => 'text_align', 'type' => 'select', 'label' => 'تراز', 'default' => 'right', 'options' => [
                ['value' => 'right', 'label' => 'راست‌چین'],
                ['value' => 'left', 'label' => 'چپ‌چین'],
                ['value' => 'center', 'label' => 'وسط‌چین'],
            ]],
        ]],
    ]);
}

function ssbwDemo(): PageDemo
{
    $category = PageCategory::firstOrCreate(['category_key' => PageCategoryKey::About->value], ['name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [],
    ]);
}

it('a button widget quick-added, filled via fieldValues, and refreshed shows up in the live preview HTML', function () {
    [$user, $company] = ssbwActingAsHoldingAdmin();
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    ssbwSeedButtonWidget();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => ssbwDemo()->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    $nodeId = $tree[0]['id'];

    $component
        ->set("fieldValues.$nodeId.label", 'تماس با ما')
        ->set("fieldValues.$nodeId.url", 'https://example.com/contact')
        ->set("fieldValues.$nodeId.style", 'outline')
        ->set("fieldValues.$nodeId.text_align", 'center')
        ->call('refreshPreview');

    $previewHtml = $component->get('previewHtml');

    expect($previewHtml)->toContain('<a class="sb-widget-button sb-btn-outline" href="https://example.com/contact">تماس با ما</a>');
    expect($previewHtml)->toContain('text-align:center;');
});

it('a filled button widget survives save into the real content_html and renders on the public site', function () {
    [$user, $company] = ssbwActingAsHoldingAdmin();
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);
    ssbwSeedButtonWidget();

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => ssbwDemo()->id,
        'title' => 'درباره ما',
        'slug' => 'about-us',
    ], $user);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addWidget', WidgetKey::Button->value);

    $tree = $component->get('widgetTree');
    $nodeId = $tree[0]['id'];

    $component
        ->set("fieldValues.$nodeId.label", 'تماس با ما')
        ->set("fieldValues.$nodeId.url", 'https://example.com/contact')
        ->set('page_status', 'published')
        ->call('save');

    $page->refresh();

    expect($page->content_html)->toContain('<a class="sb-widget-button');
    expect($page->content_html)->toContain('تماس با ما');

    $this->get(route('public-site.show', [$company->slug, $page->slug]))
        ->assertOk()
        ->assertSee('تماس با ما')
        ->assertSee('https://example.com/contact', false);
});
