<?php

use App\Livewire\Blog\BlogPostForm;
use App\Modules\Blog\Actions\CreateBlogPost;
use App\Modules\Blog\Actions\UpdateBlogPost;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function blogMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function blogGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => blogMakeRole($roleName)->id,
    ]);
}

function blogActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman-'.uniqid(), 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    blogGiveRole($user, $company, $roleName);

    return [$user, $company];
}

function blogBasePostData(string $companyId): array
{
    return [
        'owner_company_id' => $companyId,
        'category_id' => null,
        'author_user_id' => null,
        'title' => 'عنوان پست تستی',
        'slug' => 'test-post-'.uniqid(),
        'meta_title' => null,
        'meta_description' => null,
        'content_blocks' => [['type' => 'paragraph', 'text' => 'متن نمونه']],
        'content_html' => null,
        'featured_image_path' => null,
        'reading_time_minutes' => null,
        'post_status' => 'draft',
        'published_at' => null,
        'tag_ids' => [],
    ];
}

it('forces author_user_id to the operator even when a different author is supplied', function () {
    [$operator, $company] = blogActingAsWithRole('operator');
    $otherUser = User::factory()->create(['is_super_admin' => false]);

    $data = blogBasePostData($company->id);
    $data['author_user_id'] = $otherUser->id;

    $post = app(CreateBlogPost::class)->handle($data, $operator);

    expect($post->author_user_id)->toBe($operator->id);
});

it('forces status to draft for an operator regardless of requested status', function () {
    [$operator, $company] = blogActingAsWithRole('operator');

    $post = app(CreateBlogPost::class)->handle(blogBasePostData($company->id), $operator);

    $data = blogBasePostData($company->id);
    $data['post_status'] = 'published';

    $updated = app(UpdateBlogPost::class)->handle($post, $data, $operator);

    expect($updated->post_status->value)->toBe('draft');
});

it('rejects scheduled status without published_at at the action level', function () {
    [$admin, $company] = blogActingAsWithRole('holding_admin');

    $data = blogBasePostData($company->id);
    $data['post_status'] = 'scheduled';
    $data['author_user_id'] = $admin->id;

    expect(fn () => app(CreateBlogPost::class)->handle($data, $admin))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects scheduled status without a scheduled date at the form validation level', function () {
    [$admin, $company] = blogActingAsWithRole('holding_admin');
    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست زمان‌بندی‌شده')
        ->set('content', 'متن نمونه')
        ->set('post_status', 'scheduled')
        ->call('save')
        ->assertHasErrors(['scheduled_date', 'scheduled_time']);
});

it('rejects a duplicate slug within the same company', function () {
    [$admin, $company] = blogActingAsWithRole('holding_admin');

    $data = blogBasePostData($company->id);
    $data['author_user_id'] = $admin->id;
    $data['slug'] = 'fixed-slug';
    app(CreateBlogPost::class)->handle($data, $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    Livewire::test(BlogPostForm::class)
        ->set('title', 'پست دوم')
        ->set('slug', 'fixed-slug')
        ->set('content', 'متن نمونه')
        ->call('save')
        ->assertHasErrors(['slug']);
});

it('isolates blog posts by company', function () {
    [$operatorOfA] = blogActingAsWithRole('operator');
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);

    $data = blogBasePostData($companyB->id);

    expect(fn () => app(CreateBlogPost::class)->handle($data, $operatorOfA))
        ->toThrow(AuthorizationException::class);

    expect(BlogPost::withoutGlobalScopes()->where('owner_company_id', $companyB->id)->exists())->toBeFalse();
});

it('lets a holding_admin choose any author and publish the post', function () {
    [$admin, $company] = blogActingAsWithRole('holding_admin');
    $author = User::factory()->create(['is_super_admin' => false]);
    blogGiveRole($author, $company, 'operator');

    $data = blogBasePostData($company->id);
    $data['author_user_id'] = $author->id;
    $data['post_status'] = 'published';

    $post = app(CreateBlogPost::class)->handle($data, $admin);

    expect($post->author_user_id)->toBe($author->id)
        ->and($post->post_status->value)->toBe('published');
});

it('allows viewer and accountant to view the post list but forbids create/update', function () {
    foreach (['viewer', 'accountant'] as $roleName) {
        [$user, $company] = blogActingAsWithRole($roleName);
        $this->actingAs($user);
        session(['active_company_id' => $company->id]);

        $this->get('/blog/posts')->assertOk();
        $this->get('/blog/posts/create')->assertForbidden();

        expect(fn () => app(CreateBlogPost::class)->handle(blogBasePostData($company->id), $user))
            ->toThrow(AuthorizationException::class);
    }
});

it('forbids an operator from editing another operators post', function () {
    [$company] = [Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services'])];
    $author = User::factory()->create(['is_super_admin' => false]);
    $otherOperator = User::factory()->create(['is_super_admin' => false]);
    blogGiveRole($author, $company, 'operator');
    blogGiveRole($otherOperator, $company, 'operator');

    $data = blogBasePostData($company->id);
    $post = app(CreateBlogPost::class)->handle($data, $author);

    $updateData = blogBasePostData($company->id);
    $updateData['title'] = 'دستکاری‌شده';

    expect(fn () => app(UpdateBlogPost::class)->handle($post, $updateData, $otherOperator))
        ->toThrow(AuthorizationException::class);
});

it('forbids an operator from editing a post the admin already published', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    $author = User::factory()->create(['is_super_admin' => false]);
    blogGiveRole($admin, $company, 'holding_admin');
    blogGiveRole($author, $company, 'operator');

    $data = blogBasePostData($company->id);
    $data['author_user_id'] = $author->id;
    $post = app(CreateBlogPost::class)->handle($data, $admin);

    $publishData = blogBasePostData($company->id);
    $publishData['author_user_id'] = $author->id;
    $publishData['post_status'] = 'published';
    $post = app(UpdateBlogPost::class)->handle($post, $publishData, $admin);

    $editData = blogBasePostData($company->id);
    $editData['title'] = 'تلاش برای ویرایش';

    expect(fn () => app(UpdateBlogPost::class)->handle($post, $editData, $author))
        ->toThrow(AuthorizationException::class);
});
