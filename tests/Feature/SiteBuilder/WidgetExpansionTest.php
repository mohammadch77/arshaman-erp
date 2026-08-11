<?php

use App\Livewire\SiteBuilder\PageContentEditor;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Database\Seeders\SiteBuilderWidgetsExpansionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function weMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function weActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => weMakeRole($roleName)->id,
    ]);

    return [$user, $company];
}

it('seeds all thirteen widgets with editable_fields defined', function () {
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);

    // ویجت‌های پایه از SiteBuilderSeeder نیستند اینجا؛ فقط ده ویجت جدید این Seeder را چک می‌کنیم.
    $newKeys = [
        WidgetKey::Button, WidgetKey::Gallery, WidgetKey::Testimonial, WidgetKey::PricingTable,
        WidgetKey::FaqAccordion, WidgetKey::Map, WidgetKey::Video, WidgetKey::Spacer,
        WidgetKey::HeaderNav, WidgetKey::Footer,
    ];

    foreach ($newKeys as $key) {
        $widget = Widget::where('widget_key', $key->value)->first();
        expect($widget)->not->toBeNull();
        expect($widget->editableFields())->not->toBeEmpty();
    }
});

it('renders button, escapes label text, and skips outline style safely', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'btn1',
            'widget_key' => WidgetKey::Button->value,
            'values' => ['label' => '<script>alert(1)</script>', 'url' => 'https://example.com', 'style' => 'outline'],
            'children' => [],
        ],
    ]);

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
    expect($html)->toContain('sb-btn-outline');
});

it('renders gallery images with escaped captions and skips items without image_path', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'gal1',
            'widget_key' => WidgetKey::Gallery->value,
            'values' => ['images' => [
                ['image_path' => 'a.jpg', 'caption' => '"><img src=x onerror=alert(1)>'],
                ['image_path' => '', 'caption' => 'باید حذف شود'],
            ]],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('a.jpg');
    expect($html)->not->toContain('<img src=x');
    expect($html)->toContain('&lt;img src=x');
    expect($html)->not->toContain('باید حذف شود');
});

it('renders faq accordion items and skips incomplete pairs', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'faq1',
            'widget_key' => WidgetKey::FaqAccordion->value,
            'values' => ['items' => [
                ['question' => 'سوال یک', 'answer' => 'جواب یک'],
                ['question' => 'سوال ناقص', 'answer' => ''],
            ]],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('سوال یک');
    expect($html)->toContain('جواب یک');
    expect($html)->not->toContain('سوال ناقص');
});

it('renders pricing table features from a plain array', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'pt1',
            'widget_key' => WidgetKey::PricingTable->value,
            'values' => [
                'plan_name' => 'پلن طلایی',
                'price' => '۱۰۰,۰۰۰ تومان',
                'features' => ['ویژگی یک', 'ویژگی دو'],
                'cta_label' => 'خرید',
                'cta_url' => 'https://example.com/buy',
            ],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('پلن طلایی');
    expect($html)->toContain('ویژگی یک');
    expect($html)->toContain('ویژگی دو');
});

it('renders a map embed only from an allowed google.com/maps/embed url', function () {
    $renderer = app(WidgetContentRenderer::class);

    $allowedHtml = $renderer->render([
        [
            'id' => 'map1',
            'widget_key' => WidgetKey::Map->value,
            'values' => ['embed_url' => 'https://www.google.com/maps/embed?pb=abc123'],
            'children' => [],
        ],
    ]);

    expect($allowedHtml)->toContain('<iframe');
    expect($allowedHtml)->toContain('www.google.com/maps/embed');
});

it('rejects a map embed url from a disallowed domain', function () {
    $renderer = app(WidgetContentRenderer::class);

    $rejectedHtml = $renderer->render([
        [
            'id' => 'map2',
            'widget_key' => WidgetKey::Map->value,
            'values' => ['embed_url' => 'https://evil.com/maps/embed?pb=abc123'],
            'children' => [],
        ],
    ]);

    expect($rejectedHtml)->not->toContain('<iframe');
    expect($rejectedHtml)->not->toContain('evil.com');
});

it('renders a video embed from a valid youtube watch url', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'vid1',
            'widget_key' => WidgetKey::Video->value,
            'values' => ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('<iframe');
    expect($html)->toContain('youtube.com/embed/dQw4w9WgXcQ');
});

it('renders a video embed from a valid aparat url', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'vid2',
            'widget_key' => WidgetKey::Video->value,
            'values' => ['video_url' => 'https://www.aparat.com/v/abcde'],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('<iframe');
    expect($html)->toContain('aparat.com/video/video/embed/videohash/abcde');
});

it('rejects a video url from a disallowed domain', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        [
            'id' => 'vid3',
            'widget_key' => WidgetKey::Video->value,
            'values' => ['video_url' => 'https://evil.com/watch?v=xss'],
            'children' => [],
        ],
    ]);

    expect($html)->not->toContain('<iframe');
    expect($html)->not->toContain('evil.com');
});

it('renders header nav links and footer social links, escaping xss attempts', function () {
    $renderer = app(WidgetContentRenderer::class);

    $navHtml = $renderer->render([
        [
            'id' => 'nav1',
            'widget_key' => WidgetKey::HeaderNav->value,
            'values' => ['nav_links' => [
                ['label' => '"><script>alert(1)</script>', 'url' => 'https://example.com'],
            ]],
            'children' => [],
        ],
    ]);

    expect($navHtml)->not->toContain('<script>');

    $footerHtml = $renderer->render([
        [
            'id' => 'foot1',
            'widget_key' => WidgetKey::Footer->value,
            'values' => [
                'copyright_text' => 'تمامی حقوق محفوظ است',
                'social_links' => [['label' => 'اینستاگرام', 'url' => 'https://instagram.com/x']],
                'contact_text' => 'تهران',
            ],
            'children' => [],
        ],
    ]);

    expect($footerHtml)->toContain('تمامی حقوق محفوظ است');
    expect($footerHtml)->toContain('اینستاگرام');
});

it('clamps spacer height and renders nothing for zero height', function () {
    $renderer = app(WidgetContentRenderer::class);

    $html = $renderer->render([
        ['id' => 'sp1', 'widget_key' => WidgetKey::Spacer->value, 'values' => ['height_px' => '5000'], 'children' => []],
    ]);

    expect($html)->toContain('height:1000px');

    $empty = $renderer->render([
        ['id' => 'sp2', 'widget_key' => WidgetKey::Spacer->value, 'values' => ['height_px' => '0'], 'children' => []],
    ]);

    expect($empty)->toBe('<div class="sb-page-empty"></div>');
});

it('adds and removes repeater rows in the live editor and persists them to widget_tree', function () {
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);
    [$user, $company] = weActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::Services->value, 'name' => 'خدمات']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی سوالات متداول',
        'widget_tree' => [
            [
                'id' => 'faq-block',
                'widget_key' => WidgetKey::FaqAccordion->value,
                'instance_label' => 'سوالات متداول',
                'values' => ['items' => [['question' => 'سوال اولیه', 'answer' => 'جواب اولیه']]],
                'children' => [],
            ],
        ],
    ]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'خدمات ما',
        'slug' => 'services',
    ], $user);

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addRepeaterRow', 'faq-block', 'items', [
            ['key' => 'question', 'type' => 'text'],
            ['key' => 'answer', 'type' => 'textarea'],
        ])
        ->set('fieldValues.faq-block.items.1.question', 'سوال جدید')
        ->set('fieldValues.faq-block.items.1.answer', 'جواب جدید')
        ->call('removeRepeaterRow', 'faq-block', 'items', 0)
        ->call('save')
        ->assertHasNoErrors();

    $storedItems = $page->fresh()->widget_tree[0]['values']['items'];

    expect($storedItems)->toHaveCount(1);
    expect($storedItems[0]['question'])->toBe('سوال جدید');
    expect($storedItems[0]['answer'])->toBe('جواب جدید');
});

it('splits a lines-type field textarea into a trimmed array on save', function () {
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);
    [$user, $company] = weActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::Services->value, 'name' => 'خدمات']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی قیمت‌گذاری',
        'widget_tree' => [
            [
                'id' => 'pricing-block',
                'widget_key' => WidgetKey::PricingTable->value,
                'instance_label' => 'پلن پایه',
                'values' => ['plan_name' => 'پایه', 'price' => '۰', 'features' => [], 'cta_label' => '', 'cta_url' => ''],
                'children' => [],
            ],
        ],
    ]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'قیمت‌گذاری',
        'slug' => 'pricing',
    ], $user);

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->set('linesRaw.pricing-block.features', "ویژگی یک\n\nویژگی دو\n  ")
        ->call('save')
        ->assertHasNoErrors();

    $storedFeatures = $page->fresh()->widget_tree[0]['values']['features'];

    expect($storedFeatures)->toBe(['ویژگی یک', 'ویژگی دو']);
});

it('defaults fieldValues and imageUploads for a declared field the demo never set', function () {
    // Mary UI's <x-file> always @entangle's its wire:model path. If a demo
    // omits an optional field (e.g. testimonial.customer_photo), that path
    // never exists on fieldValues/imageUploads and Alpine's entangle throws
    // in the browser console — mount() must pre-fill every declared field.
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);
    [$user, $company] = weActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::Services->value, 'name' => 'خدمات']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی نظر مشتری بدون عکس',
        'widget_tree' => [
            [
                'id' => 'testimonial-block',
                'widget_key' => WidgetKey::Testimonial->value,
                'instance_label' => 'نظر مشتری',
                // عمداً customer_photo را ست نمی‌کنیم
                'values' => ['quote_text' => 'عالی بود', 'customer_name' => 'سارا'],
                'children' => [],
            ],
        ],
    ]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'نظرات',
        'slug' => 'testimonials',
    ], $user);

    $component = Livewire::test(PageContentEditor::class, ['page' => $page->id]);

    expect($component->get('fieldValues.testimonial-block.customer_photo'))->toBeNull();
    expect($component->get('imageUploads.testimonial-block.customer_photo'))->toBeNull();
});

it('renders the correct field labels for each new widget type over a real HTTP request', function () {
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);
    [$user, $company] = weActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::Services->value, 'name' => 'خدمات']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی همه ویجت‌ها',
        'widget_tree' => [
            ['id' => 'w-button', 'widget_key' => WidgetKey::Button->value, 'instance_label' => 'دکمه اصلی', 'values' => [], 'children' => []],
            ['id' => 'w-gallery', 'widget_key' => WidgetKey::Gallery->value, 'instance_label' => 'گالری تصاویر', 'values' => ['images' => []], 'children' => []],
            ['id' => 'w-testimonial', 'widget_key' => WidgetKey::Testimonial->value, 'instance_label' => 'نظر مشتری اول', 'values' => [], 'children' => []],
            ['id' => 'w-pricing', 'widget_key' => WidgetKey::PricingTable->value, 'instance_label' => 'پلن طلایی', 'values' => ['features' => []], 'children' => []],
            ['id' => 'w-faq', 'widget_key' => WidgetKey::FaqAccordion->value, 'instance_label' => 'سوالات متداول', 'values' => ['items' => []], 'children' => []],
            ['id' => 'w-map', 'widget_key' => WidgetKey::Map->value, 'instance_label' => 'نقشه دفتر', 'values' => [], 'children' => []],
            ['id' => 'w-video', 'widget_key' => WidgetKey::Video->value, 'instance_label' => 'ویدیوی معرفی', 'values' => [], 'children' => []],
            ['id' => 'w-spacer', 'widget_key' => WidgetKey::Spacer->value, 'instance_label' => 'فاصله', 'values' => [], 'children' => []],
            ['id' => 'w-nav', 'widget_key' => WidgetKey::HeaderNav->value, 'instance_label' => 'منوی هدر', 'values' => ['nav_links' => []], 'children' => []],
            ['id' => 'w-footer', 'widget_key' => WidgetKey::Footer->value, 'instance_label' => 'فوتر سایت', 'values' => ['social_links' => []], 'children' => []],
        ],
    ]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'همه ویجت‌ها',
        'slug' => 'all-widgets',
    ], $user);

    $this->get(route('sitebuilder.pages.edit', $page->id))
        ->assertOk()
        ->assertSee('متن دکمه')
        ->assertSee('تصاویر گالری')
        ->assertSee('متن نظر')
        ->assertSee('فهرست ویژگی‌ها (هر خط یک ویژگی)')
        ->assertSee('سوالات و جواب‌ها')
        ->assertSee('لینک embed نقشه گوگل')
        ->assertSee('لینک ویدیو (فقط یوتیوب یا آپارات)')
        ->assertSee('ارتفاع (پیکسل)')
        ->assertSee('آیتم‌های منو')
        ->assertSee('متن کپی‌رایت');
});

it('uploads a real image into a gallery repeater row and stores its path', function () {
    Storage::fake('public');
    $this->seed(SiteBuilderWidgetsExpansionSeeder::class);
    [$user, $company] = weActingAsWithRole('holding_admin');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $category = PageCategory::create(['category_key' => PageCategoryKey::Services->value, 'name' => 'خدمات']);
    $demo = PageDemo::create([
        'page_category_id' => $category->id,
        'name' => 'دموی گالری',
        'widget_tree' => [
            [
                'id' => 'gallery-block',
                'widget_key' => WidgetKey::Gallery->value,
                'instance_label' => 'گالری',
                'values' => ['images' => []],
                'children' => [],
            ],
        ],
    ]);

    $page = app(CreatePageFromDemo::class)->handle([
        'owner_company_id' => $company->id,
        'page_demo_id' => $demo->id,
        'title' => 'گالری تصاویر',
        'slug' => 'gallery',
    ], $user);

    $file = UploadedFile::fake()->image('gallery.jpg');

    Livewire::test(PageContentEditor::class, ['page' => $page->id])
        ->call('addRepeaterRow', 'gallery-block', 'images', [
            ['key' => 'image_path', 'type' => 'image'],
            ['key' => 'caption', 'type' => 'text'],
        ])
        ->set('imageUploads.gallery-block.images.0.image_path', $file)
        ->set('fieldValues.gallery-block.images.0.caption', 'عکس اول')
        ->call('save')
        ->assertHasNoErrors();

    $storedImages = $page->fresh()->widget_tree[0]['values']['images'];

    expect($storedImages)->toHaveCount(1);
    expect($storedImages[0]['caption'])->toBe('عکس اول');
    Storage::disk('public')->assertExists($storedImages[0]['image_path']);
});
