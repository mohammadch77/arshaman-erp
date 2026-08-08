<?php

use App\Livewire\Blog\BlogPostForm;
use App\Modules\Blog\Actions\DeleteBlogPost;
use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

function mgmtMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function mgmtGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => mgmtMakeRole($roleName)->id,
    ]);
}

function mgmtActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-mgmt-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    mgmtGiveRole($user, $company, $roleName);

    return [$user, $company];
}

// ————— بخش ۱: Autosave —————

it('autosaves a new draft post as soon as the title is set, without an explicit save click', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست خودکار ذخیره‌شده')
        ->call('runAutosave');

    $post = BlogPost::where('title', 'پست خودکار ذخیره‌شده')->first();

    expect($post)->not->toBeNull()
        ->and($post->post_status)->toBe(BlogPostStatus::Draft)
        ->and($post->author_user_id)->toBe($admin->id);
});

it('reuses the same autosaved record for subsequent content autosaves instead of creating a new one', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست تکرارنشونده')
        ->call('runAutosave')
        ->set('content', '<p>محتوای اول</p>')
        ->call('runAutosave')
        ->set('content', '<p>محتوای دوم</p>')
        ->call('runAutosave');

    expect(BlogPost::where('title', 'پست تکرارنشونده')->count())->toBe(1);

    $post = BlogPost::where('title', 'پست تکرارنشونده')->first();
    expect($post->content_html)->toContain('محتوای دوم');
});

it('does not autosave changes to an already scheduled or published post', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست منتشرشده',
        'slug' => 'published-post-'.uniqid(),
        'content_html' => '<p>اصل</p>',
        'post_status' => BlogPostStatus::Published->value,
        'published_at' => now(),
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    Livewire::test(BlogPostForm::class, ['post' => $post->id])
        ->set('title', 'تلاش برای ویرایش بی‌صدا')
        ->call('runAutosave');

    expect($post->fresh()->title)->toBe('پست منتشرشده');
});

// ————— بخش ۱.۵: ذخیره نهایی نباید به autosave وابسته باشد (باگ واقعی) —————
//
// در استفاده واقعی، کاربر می‌تواند بلافاصله بعد از تایپ عنوان روی «ثبت» کلیک
// کند — سریع‌تر از چیزی که autosave سمت مرورگر (debounce دوثانیه‌ای) فرصت
// اجرا پیدا کند. save() باید مستقل از اینکه runAutosave قبلش اجرا شده یا نه،
// همیشه با محتوای تازه‌ترین کاربر کار کند و اسلاگ را خودش تولید کند — نه اینکه
// به یک اسلاگ خالی (چون فقط autosave آن را پر می‌کرد) با خطای اعتبارسنجی
// خاموش شکست بخورد.
it('creates a post via a real submit even when autosave never ran first', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست بدون autosave قبلی')
        ->set('content', '<p>این محتوا باید دقیقاً ذخیره شود.</p>')
        ->call('save')
        ->assertHasNoErrors();

    $post = BlogPost::where('title', 'پست بدون autosave قبلی')->first();

    expect($post)->not->toBeNull()
        ->and($post->slug)->not->toBe('')
        ->and($post->content_html)->toContain('این محتوا باید دقیقاً ذخیره شود.');
});

// ————— بخش ۱.۶: ویرایش نباید محتوای موجود را پاک کند (باگ واقعی) —————
//
// باگ واقعی: چون Blade، هم div ادیتور Quill را با wire:ignore منجمد می‌کرد و
// هم id ورودی مخفی content را از رکورد ($this->record?->id ?? 'new') مشتق
// می‌کرد، هر رندر مجددی که این id را عوض می‌کرد باعث می‌شد Quill به یک id
// دیگر بنویسد و محتوا هرگز واقعاً ذخیره نشود. این تست با یک درخواست HTTP
// واقعی (نه فقط Livewire::test) صفحه ویرایش را باز می‌کند تا مطمئن شود محتوای
// از‌پیش‌موجود در HTML رندرشده واقعاً حاضر است — دقیقاً همان درسی که از باگ
// @scope گرفتیم: باگ‌های رندر فقط با یک درخواست HTTP کامل دیده می‌شوند.
it('renders a real edit page over HTTP with the existing content intact, and a real save preserves it', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست ویرایش با محتوای واقعی',
        'slug' => 'real-edit-content-'.uniqid(),
        'content_html' => '<p>این محتوای اولیه است که نباید در ویرایش گم شود.</p>',
        'post_status' => BlogPostStatus::Draft->value,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    // محتوای اولیه از طریق x-init="window.initBlogEditor(..., @js($initialContent), ...)"
    // به Quill پاس داده می‌شود — @js() آن را JSON-escape می‌کند (از جمله یونیکد
    // فارسی و علائم <>)، پس متن فارسی خام در HTML رندرشده به‌صورت لفظی دیده
    // نمی‌شود. برای بررسی واقعی باید همان encoding را با Js::from() بازتولید
    // کرد، نه substring خام فارسی را جست‌وجو کرد.
    $this->get(route('blog.posts.edit', $post->id))
        ->assertOk()
        ->assertSee(\Illuminate\Support\Js::from($post->content_html)->toHtml(), false);

    Livewire::test(BlogPostForm::class, ['post' => $post->id])
        ->set('content', '<p>این محتوای اولیه است که نباید در ویرایش گم شود. -- افزوده‌شده در ویرایش.</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($post->fresh()->content_html)
        ->toContain('این محتوای اولیه است که نباید در ویرایش گم شود.')
        ->toContain('افزوده‌شده در ویرایش.');
});

// ————— بخش ۲: حذف کامل بایگانی —————

it('no longer exposes an archived case on the status enum', function () {
    expect(BlogPostStatus::tryFrom('archived'))->toBeNull()
        ->and(array_map(fn ($case) => $case->value, BlogPostStatus::cases()))->toBe(['draft', 'scheduled', 'published']);
});

it('rejects the archived status at the database CHECK constraint layer', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL واقعی اعمال می‌شود.');
    }

    [$admin, $company] = mgmtActingAsWithRole('holding_admin');

    expect(fn () => BlogPost::withoutGlobalScopes()->getConnection()->table('blog_posts')->insert([
        'id' => (string) Str::uuid(),
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'تلاش برای بایگانی',
        'slug' => 'archive-attempt-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => 'archived',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

// ————— بخش ۳: حذف پست —————

it('forbids an operator from deleting a post that is not their own draft', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-del-'.uniqid(), 'business_type' => 'project_services']);
    $author = User::factory()->create(['is_super_admin' => false]);
    $otherOperator = User::factory()->create(['is_super_admin' => false]);
    mgmtGiveRole($author, $company, 'operator');
    mgmtGiveRole($otherOperator, $company, 'operator');

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $author->id,
        'title' => 'پست دیگری',
        'slug' => 'other-post-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Draft->value,
        'created_by_user_id' => $author->id,
        'updated_by_user_id' => $author->id,
    ]);

    expect(fn () => app(DeleteBlogPost::class)->handle($post, $otherOperator))
        ->toThrow(AuthorizationException::class);

    expect(BlogPost::withoutGlobalScopes()->find($post->id))->not->toBeNull();
});

it('lets a holding_admin delete any post via a real soft delete', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست حذف‌شدنی',
        'slug' => 'deletable-post-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Published->value,
        'published_at' => now(),
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    app(DeleteBlogPost::class)->handle($post, $admin);

    // withoutGlobalScope('owner_company') فقط ایزولاسیون شرکت را کنار می‌زند،
    // نه SoftDeletingScope را — تا رکورد soft-delete-شده واقعاً از find() عادی
    // غایب باشد و فقط با withTrashed() برگردد.
    expect(BlogPost::withoutGlobalScope('owner_company')->find($post->id))->toBeNull()
        ->and(BlogPost::withoutGlobalScope('owner_company')->withTrashed()->find($post->id))->not->toBeNull();
});

// ————— بخش ۴: برچسب آزاد —————

it('auto-creates a new free-form tag on save and links it to the post', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست با برچسب آزاد')
        ->set('content', 'متن نمونه')
        ->set('tagNames', ['برچسب نو'])
        ->call('save');

    $tag = BlogTag::where('owner_company_id', $company->id)->where('name', 'برچسب نو')->first();

    expect($tag)->not->toBeNull();

    $post = BlogPost::where('title', 'پست با برچسب آزاد')->first();
    expect($post->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('reuses an existing tag with the same name instead of creating a duplicate', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست اول با برچسب مشترک')
        ->set('content', 'متن نمونه')
        ->set('tagNames', ['برچسب مشترک'])
        ->call('save');

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست دوم با برچسب مشترک')
        ->set('content', 'متن نمونه')
        ->set('tagNames', ['برچسب مشترک'])
        ->call('save');

    expect(BlogTag::where('owner_company_id', $company->id)->where('name', 'برچسب مشترک')->count())->toBe(1);

    $firstTagId = BlogPost::where('title', 'پست اول با برچسب مشترک')->first()->tags->pluck('id')->first();
    $secondTagId = BlogPost::where('title', 'پست دوم با برچسب مشترک')->first()->tags->pluck('id')->first();

    expect($firstTagId)->toBe($secondTagId);
});

it('renders the real /blog/posts index page over HTTP without errors for draft, scheduled, and published rows', function () {
    // این تست عمداً یک درخواست HTTP واقعی می‌زند (نه فقط Livewire::test) — باگ
    // واقعی‌ای که این تست جایگزینش شد این بود: @scope('actions', $post) در
    // mary/Table.php فقط متغیرهایی را capture می‌کند که صریحاً به‌عنوان آرگومان
    // اضافه به خودِ @scope داده شده باشند، نه کل scope بیرونی view را؛
    // $activeCompanySlug که در BlogPostIndex::render() به view پاس داده می‌شد
    // هرگز به این شکل به @scope اضافه نشده بود، پس فقط وقتی حداقل یک ردیف
    // واقعی در جدول رندر می‌شد (نه در Livewire::test بدون رکورد) خطای
    // «Undefined variable» رخ می‌داد. بدون یک درخواست HTTP کامل با رکورد
    // واقعی در هر سه وضعیت، این کلاس باگ (خطای رندر داخل @scope) هرگز در
    // تست خودکار دیده نمی‌شد.
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');

    BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست پیش‌نویس فهرست',
        'slug' => 'index-draft-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Draft->value,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست زمان‌بندی‌شده فهرست',
        'slug' => 'index-scheduled-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Scheduled->value,
        'published_at' => now()->addWeek(),
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست منتشرشده فهرست',
        'slug' => 'index-published-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Published->value,
        'published_at' => now(),
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $this->get(route('blog.posts.index'))
        ->assertOk()
        ->assertSee('پست پیش‌نویس فهرست')
        ->assertSee('پست زمان‌بندی‌شده فهرست')
        ->assertSee('پست منتشرشده فهرست');
});

// ————— بخش ۵: پیش‌نمایش —————

it('lets an authorized user preview a draft or scheduled post that the public URL still 404s on', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست پیش‌نمایش',
        'slug' => 'preview-post-'.uniqid(),
        'content_html' => '<p>متن پیش‌نمایش</p>',
        'post_status' => BlogPostStatus::Draft->value,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $this->get(route('blog.posts.preview', $post->id))
        ->assertOk()
        ->assertSee('پست پیش‌نمایش');

    $this->get(route('public-blog.show', [$company->slug, $post->slug]))
        ->assertNotFound();
});

it('forbids a user with no role in the company from previewing its posts', function () {
    [$admin, $company] = mgmtActingAsWithRole('holding_admin');
    $outsider = User::factory()->create(['is_super_admin' => false]);

    $post = BlogPost::create([
        'owner_company_id' => $company->id,
        'author_user_id' => $admin->id,
        'title' => 'پست محرمانه',
        'slug' => 'restricted-post-'.uniqid(),
        'content_html' => '<p>متن</p>',
        'post_status' => BlogPostStatus::Draft->value,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    $this->actingAs($outsider);
    session(['active_company_id' => $company->id]);

    $this->get(route('blog.posts.preview', $post->id))->assertForbidden();
});
