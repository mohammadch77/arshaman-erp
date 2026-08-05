<?php

use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;

function publicBlogCompany(string $suffix = ''): Company
{
    return Company::create([
        'name' => 'آرشامان'.$suffix,
        'slug' => 'arshaman-'.($suffix ?: uniqid()),
        'business_type' => 'project_services',
    ]);
}

function publicBlogPost(Company $company, array $overrides = []): BlogPost
{
    $author = User::factory()->create(['is_super_admin' => false]);

    return BlogPost::create(array_merge([
        'owner_company_id' => $company->id,
        'author_user_id' => $author->id,
        'title' => 'عنوان پست تستی',
        'slug' => 'test-post-'.uniqid(),
        'meta_title' => null,
        'meta_description' => null,
        'content_html' => '<p>متن نمونه پست</p>',
        'post_status' => 'draft',
        'published_at' => null,
    ], $overrides));
}

it('shows a published post whose published_at is in the past on the index and show pages', function () {
    $company = publicBlogCompany('one');
    $post = publicBlogPost($company, [
        'post_status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->get(route('public-blog.index', $company->slug))
        ->assertOk()
        ->assertSee($post->title);

    $this->get(route('public-blog.show', [$company->slug, $post->slug]))
        ->assertOk()
        ->assertSee($post->title);
});

it('returns 404 for a scheduled post whose published_at is in the future even via direct url', function () {
    $company = publicBlogCompany('two');
    $post = publicBlogPost($company, [
        'post_status' => 'scheduled',
        'published_at' => now()->addDay(),
    ]);

    $this->get(route('public-blog.show', [$company->slug, $post->slug]))->assertNotFound();

    $this->get(route('public-blog.index', $company->slug))
        ->assertOk()
        ->assertDontSee($post->title);
});

it('returns 404 for draft and archived posts', function () {
    $company = publicBlogCompany('three');

    $draft = publicBlogPost($company, ['post_status' => 'draft', 'published_at' => null]);
    $archived = publicBlogPost($company, ['post_status' => 'archived', 'published_at' => now()->subWeek()]);

    $this->get(route('public-blog.show', [$company->slug, $draft->slug]))->assertNotFound();
    $this->get(route('public-blog.show', [$company->slug, $archived->slug]))->assertNotFound();
});

it('isolates published posts by company on both the index and show pages', function () {
    $companyA = publicBlogCompany('a');
    $companyB = publicBlogCompany('b');

    $postOfA = publicBlogPost($companyA, [
        'post_status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    // تلاش برای دیدن پست شرکت الف با URL شرکت ب باید ۴۰۴ بدهد
    $this->get(route('public-blog.show', [$companyB->slug, $postOfA->slug]))->assertNotFound();

    $this->get(route('public-blog.index', $companyB->slug))
        ->assertOk()
        ->assertDontSee($postOfA->title);
});

it('renders real seo meta tags on the show page', function () {
    $company = publicBlogCompany('seo');
    $post = publicBlogPost($company, [
        'post_status' => 'published',
        'published_at' => now()->subDay(),
        'meta_title' => 'عنوان سئو',
        'meta_description' => 'توضیح سئو',
    ]);

    $response = $this->get(route('public-blog.show', [$company->slug, $post->slug]));

    $response->assertOk();
    $response->assertSee('<title>عنوان سئو', false);
    $response->assertSee('name="description" content="توضیح سئو"', false);
    $response->assertSee('property="og:title" content="عنوان سئو"', false);
});
