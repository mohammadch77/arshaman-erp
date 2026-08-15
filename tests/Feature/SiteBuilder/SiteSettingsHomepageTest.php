<?php

use App\Livewire\SiteBuilder\LayoutDemoSelector;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\SiteSetting;
use Livewire\Livewire;

function hpMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function hpGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => hpMakeRole($roleName)->id,
    ]);
}

function hpActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    hpGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function hpDemo(): PageDemo
{
    $category = PageCategory::firstOrCreate(['category_key' => PageCategoryKey::About->value], ['name' => 'درباره ما']);

    return PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نمونه',
        'widget_tree' => [],
    ]);
}

function hpPage(Company $company, array $overrides = []): Page
{
    return Page::create(array_merge([
        'owner_company_id' => $company->id,
        'page_demo_id' => hpDemo()->id,
        'title' => 'صفحه تستی',
        'slug' => 'page-'.uniqid(),
        'widget_tree' => [
            [
                'id' => 'page-title',
                'widget_key' => WidgetKey::Title->value,
                'values' => ['text' => 'محتوای صفحه'],
                'children' => [],
            ],
        ],
        'content_html' => '<h1>محتوای صفحه</h1>',
        'page_status' => PageStatus::Draft->value,
    ], $overrides));
}

it('selects a published page as homepage and it renders at the public site url', function () {
    [$user, $company] = hpActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $page = hpPage($company, ['page_status' => PageStatus::Published->value, 'title' => 'صفحه اصلی جدید']);

    Livewire::test(LayoutDemoSelector::class)
        ->set('homepage_page_id', $page->id)
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::where('owner_company_id', $company->id)->firstOrFail();
    expect($setting->homepage_page_id)->toBe($page->id);

    $this->get(route('public-site.home', $company->slug))
        ->assertOk()
        ->assertSee('محتوای صفحه');
});

it('selects a published page as blog page', function () {
    [$user, $company] = hpActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $page = hpPage($company, ['page_status' => PageStatus::Published->value]);

    Livewire::test(LayoutDemoSelector::class)
        ->set('blog_page_id', $page->id)
        ->call('save')
        ->assertHasNoErrors();

    $setting = SiteSetting::where('owner_company_id', $company->id)->firstOrFail();
    expect($setting->blog_page_id)->toBe($page->id);
});

it('only lists published pages as selectable options for homepage/blog page', function () {
    [$user, $company] = hpActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $published = hpPage($company, ['page_status' => PageStatus::Published->value, 'title' => 'منتشرشده']);
    $draft = hpPage($company, ['page_status' => PageStatus::Draft->value, 'title' => 'پیش‌نویس']);

    $component = Livewire::test(LayoutDemoSelector::class);
    $options = $component->get('publishedPages');

    expect($options->pluck('id'))->toContain($published->id);
    expect($options->pluck('id'))->not->toContain($draft->id);
});

it('rejects selecting a draft page as homepage via validation', function () {
    [$user, $company] = hpActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $draft = hpPage($company, ['page_status' => PageStatus::Draft->value]);

    Livewire::test(LayoutDemoSelector::class)
        ->set('homepage_page_id', $draft->id)
        ->call('save')
        ->assertHasErrors(['homepage_page_id']);
});

it('nulls out homepage_page_id when the selected page is unpublished back to draft', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $page = hpPage($company, ['page_status' => PageStatus::Published->value]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $page->id,
        'blog_page_id' => $page->id,
    ]);

    $page->update(['page_status' => PageStatus::Draft->value]);

    $setting = SiteSetting::withoutGlobalScopes()->where('owner_company_id', $company->id)->firstOrFail();
    expect($setting->homepage_page_id)->toBeNull();
    expect($setting->blog_page_id)->toBeNull();
});

it('nulls out homepage_page_id when the selected page is deleted', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $page = hpPage($company, ['page_status' => PageStatus::Published->value]);

    SiteSetting::create([
        'owner_company_id' => $company->id,
        'homepage_page_id' => $page->id,
    ]);

    $page->delete();

    $setting = SiteSetting::withoutGlobalScopes()->where('owner_company_id', $company->id)->firstOrFail();
    expect($setting->homepage_page_id)->toBeNull();
});
