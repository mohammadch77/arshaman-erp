<?php

use App\Livewire\SiteBuilder\PageCreateFlow;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use Database\Seeders\SiteBuilderDemosExpansionSeeder;
use Database\Seeders\SiteBuilderSeeder;
use Database\Seeders\SiteBuilderWidgetsExpansionSeeder;
use Livewire\Livewire;

function deActingAsWithRole(string $roleName): array
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName, 'is_system' => true]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => $role->id,
    ]);

    return [$user, $company];
}

function deSeedAll(): void
{
    (new SiteBuilderSeeder)->run();
    (new SiteBuilderWidgetsExpansionSeeder)->run();
    (new SiteBuilderDemosExpansionSeeder)->run();
}

/**
 * هر گره widget_tree را (بازگشتی، شامل children) پیمایش می‌کند.
 */
function deFlattenNodes(array $tree): array
{
    $nodes = [];

    foreach ($tree as $node) {
        $nodes[] = $node;
        $nodes = array_merge($nodes, deFlattenNodes($node['children'] ?? []));
    }

    return $nodes;
}

it('seeds three real page demos for each of the six page categories with valid non-empty widget_tree', function () {
    deSeedAll();

    foreach (PageCategoryKey::cases() as $key) {
        $category = PageCategory::where('category_key', $key->value)->firstOrFail();
        $demos = PageDemo::where('page_category_id', $category->id)->get();

        expect($demos->count())->toBeGreaterThanOrEqual(3, "دسته {$key->value} باید حداقل سه دمو داشته باشد");

        foreach ($demos as $demo) {
            expect($demo->widget_tree)->toBeArray()->not->toBeEmpty();
        }
    }
});

it('seeds three header and three footer layout demos', function () {
    deSeedAll();

    expect(LayoutDemo::where('layout_type', LayoutType::Header->value)->count())->toBeGreaterThanOrEqual(3);
    expect(LayoutDemo::where('layout_type', LayoutType::Footer->value)->count())->toBeGreaterThanOrEqual(3);

    LayoutDemo::all()->each(function (LayoutDemo $demo) {
        expect($demo->widget_tree)->toBeArray()->not->toBeEmpty();
    });
});

it('never repeats an instance_label within the same demo widget_tree', function () {
    deSeedAll();

    $allDemos = PageDemo::all()->map(fn (PageDemo $d) => [$d->name, $d->widget_tree])
        ->concat(LayoutDemo::all()->map(fn (LayoutDemo $d) => [$d->name, $d->widget_tree]));

    foreach ($allDemos as [$name, $tree]) {
        $labels = collect(deFlattenNodes($tree))->pluck('instance_label');

        expect($labels->count())->toBe($labels->unique()->count(), "دمو «{$name}» برچسب‌های تکراری دارد");
    }
});

it('shows all three about demos in the page demo gallery, not just one', function () {
    deSeedAll();
    [$user, $company] = deActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(PageCreateFlow::class);
    $categories = $component->get('categories');

    $aboutCategory = $categories->firstWhere('category_key', PageCategoryKey::About);

    expect($aboutCategory)->not->toBeNull();
    expect($aboutCategory->demos->count())->toBeGreaterThanOrEqual(4); // 1 قدیمی + 3 جدید
});

it('creates genuinely different pages from each of the three about demos', function () {
    deSeedAll();
    [$user, $company] = deActingAsWithRole('holding_admin');

    $aboutCategory = PageCategory::where('category_key', PageCategoryKey::About->value)->firstOrFail();
    $demos = PageDemo::where('page_category_id', $aboutCategory->id)
        ->where('name', '!=', 'دموی نمونه درباره ما')
        ->get();

    expect($demos->count())->toBe(3);

    $pages = $demos->map(function (PageDemo $demo, int $i) use ($company, $user) {
        return app(CreatePageFromDemo::class)->handle([
            'owner_company_id' => $company->id,
            'page_demo_id' => $demo->id,
            'title' => 'صفحه درباره ما '.$i,
            'slug' => 'about-demo-test-'.$i,
        ], $user);
    });

    $trees = $pages->map(fn ($p) => json_encode($p->widget_tree));

    expect($trees->unique()->count())->toBe(3);
});

it('gives each of the three demos in a category a distinct --sb-primary-color theme', function () {
    deSeedAll();

    foreach (PageCategoryKey::cases() as $key) {
        $category = PageCategory::where('category_key', $key->value)->firstOrFail();

        $colors = PageDemo::where('page_category_id', $category->id)
            ->get()
            ->map(fn (PageDemo $demo) => $demo->widget_tree['theme']['primary_color'] ?? null)
            ->filter()
            ->values();

        expect($colors->count())->toBeGreaterThanOrEqual(3, "دسته {$key->value} باید حداقل سه دموی رنگ‌دار داشته باشد");
        expect($colors->unique()->count())->toBe($colors->count(), "دسته {$key->value} باید رنگ‌های متفاوت داشته باشد");
    }
});
