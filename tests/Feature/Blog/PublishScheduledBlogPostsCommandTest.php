<?php

use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Spatie\Activitylog\Models\Activity;

function schedulerCompany(): Company
{
    return Company::create([
        'name' => 'آرشامان',
        'slug' => 'arshaman-'.uniqid(),
        'business_type' => 'project_services',
    ]);
}

function schedulerPost(Company $company, array $overrides = []): BlogPost
{
    $author = User::factory()->create(['is_super_admin' => false]);

    return BlogPost::create(array_merge([
        'owner_company_id' => $company->id,
        'author_user_id' => $author->id,
        'title' => 'پست زمان‌بندی‌شده',
        'slug' => 'scheduled-post-'.uniqid(),
        'content_html' => '<p>متن نمونه</p>',
        'post_status' => 'scheduled',
        'published_at' => now()->subMinute(),
    ], $overrides));
}

it('publishes a scheduled post whose published_at is in the past', function () {
    $company = schedulerCompany();
    $post = schedulerPost($company, ['published_at' => now()->subMinute()]);

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->post_status->value)->toBe('published');
});

it('leaves a scheduled post whose published_at is in the future untouched', function () {
    $company = schedulerCompany();
    $post = schedulerPost($company, ['published_at' => now()->addDay()]);

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->post_status->value)->toBe('scheduled');
});

it('logs the auto-publish activity with no human causer', function () {
    $company = schedulerCompany();
    $post = schedulerPost($company, ['published_at' => now()->subMinute()]);

    $this->artisan('blog:publish-scheduled')->assertSuccessful();

    $activity = Activity::where('subject_id', $post->id)->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer)->toBeNull();
});
