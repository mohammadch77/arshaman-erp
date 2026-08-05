<?php

use App\Livewire\CRM\ContactSubmissionIndex;
use App\Livewire\CRM\Public\ContactForm as PublicContactForm;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\SubmitContactForm;
use App\Modules\CRM\Actions\UpdateContactSubmissionStatus;
use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\CRM\Models\ContactSubmission;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function contactSubmissionMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function contactSubmissionGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => contactSubmissionMakeRole($roleName)->id,
    ]);
}

it('lets a guest submit the contact form without logging in', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    Livewire::test(PublicContactForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'مشتری مهمان')
        ->set('phone', '09121234567')
        ->set('email', 'guest@example.com')
        ->set('subject', 'سوال درباره خدمات')
        ->set('message', 'سلام، می‌خواستم درباره خدمات شما بپرسم.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $submission = ContactSubmission::first();

    expect($submission)->not->toBeNull();
    expect($submission->owner_company_id)->toBe($company->id);
    expect($submission->full_name)->toBe('مشتری مهمان');
    expect($submission->status)->toBe(ContactSubmissionStatus::New);
    expect(auth()->check())->toBeFalse();
});

it('rejects an invalid phone number at the validation layer', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    Livewire::test(PublicContactForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'مشتری مهمان')
        ->set('phone', '12345')
        ->set('message', 'پیام تست')
        ->call('submit')
        ->assertHasErrors(['phone']);

    expect(ContactSubmission::count())->toBe(0);
});

it('rejects an invalid phone number at the database CHECK constraint layer', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL واقعی اعمال می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    expect(fn () => ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '12345678901',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects submissions from the same IP after 3 attempts within the throttle window', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $action = app(SubmitContactForm::class);

    $data = [
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
        'honeypot' => '',
    ];

    $action->handle($company, $data, '1.2.3.4');
    $action->handle($company, $data, '1.2.3.4');
    $action->handle($company, $data, '1.2.3.4');

    expect(fn () => $action->handle($company, $data, '1.2.3.4'))->toThrow(ThrottleRequestsException::class);

    expect(ContactSubmission::count())->toBe(3);
});

it('silently drops a submission when the honeypot field is filled, without raising an error', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $result = app(SubmitContactForm::class)->handle($company, [
        'full_name' => 'بات مخرب',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام بات',
        'honeypot' => 'http://spam.example',
    ], '9.9.9.9');

    expect($result)->toBeNull();
    expect(ContactSubmission::count())->toBe(0);
});

it('returns 403 in the admin panel for a role not authorized to view contact submissions', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    contactSubmissionGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    $this->get('/contact-submissions')->assertForbidden();
});

it('isolates contact submissions between companies in the admin panel', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);

    ContactSubmission::create([
        'owner_company_id' => $companyA->id,
        'full_name' => 'مشتری آرشامان',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام برای آرشامان',
    ]);

    ContactSubmission::create([
        'owner_company_id' => $companyB->id,
        'full_name' => 'مشتری Tkart',
        'phone' => '09121234568',
        'email' => null,
        'subject' => null,
        'message' => 'پیام برای Tkart',
    ]);

    $operatorOfA = User::factory()->create(['is_super_admin' => false]);
    contactSubmissionGiveRole($operatorOfA, $companyA, 'operator');

    $this->actingAs($operatorOfA);
    session(['active_company_id' => $companyA->id]);

    Livewire::test(ContactSubmissionIndex::class)
        ->assertSee('مشتری آرشامان')
        ->assertDontSee('مشتری Tkart');
});

it('logs a separate activity record with the correct causer for each of two status changes by two different users', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operatorA = User::factory()->create(['is_super_admin' => false, 'full_name' => 'اپراتور اول']);
    $operatorB = User::factory()->create(['is_super_admin' => false, 'full_name' => 'اپراتور دوم']);
    contactSubmissionGiveRole($operatorA, $company, 'operator');
    contactSubmissionGiveRole($operatorB, $company, 'operator');

    $submission = ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
    ]);

    // فرشِ صریح قبل از هر دو فراخوانی — دقیقاً همان الگوی واقعی
    // ContactSubmissionIndex::markStatus()/openHistory() که همیشه رکورد را
    // تازه از دیتابیس می‌خوانند، نه یک نمونه در-حافظه‌ی تازه‌ساخته‌شده که
    // مقدار پیش‌فرض status را از DB نخوانده.
    app(UpdateContactSubmissionStatus::class)->handle($submission->fresh(), ContactSubmissionStatus::Read, $operatorA);
    app(UpdateContactSubmissionStatus::class)->handle($submission->fresh(), ContactSubmissionStatus::Replied, $operatorB);

    $activities = Activity::where('log_name', 'contact_submission')
        ->where('subject_id', $submission->id)
        ->orderBy('id')
        ->get();

    expect($activities)->toHaveCount(2);

    expect($activities[0]->causer_id)->toBe($operatorA->id);
    expect($activities[0]->properties['old']['status'])->toBe('new');
    expect($activities[0]->properties['attributes']['status'])->toBe('read');

    expect($activities[1]->causer_id)->toBe($operatorB->id);
    expect($activities[1]->properties['old']['status'])->toBe('read');
    expect($activities[1]->properties['attributes']['status'])->toBe('replied');
});

it('logs an activity record when a submission is archived, not only when replied', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    contactSubmissionGiveRole($operator, $company, 'operator');

    $submission = ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
    ]);

    app(UpdateContactSubmissionStatus::class)->handle($submission, ContactSubmissionStatus::Archived, $operator);

    $activity = Activity::where('log_name', 'contact_submission')->where('subject_id', $submission->id)->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($operator->id);
    expect($activity->properties['attributes']['status'])->toBe('archived');
});

it('forbids a user without access to a submission from viewing its history', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    contactSubmissionGiveRole($viewer, $company, 'viewer');

    $submission = ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
    ]);

    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    // خودِ ContactSubmissionIndex::mount() با viewAny رد می‌شود چون viewer
    // اصلاً اجازه دیدن پنل را ندارد — یعنی حتی به مرحله باز کردن تاریخچه هم
    // نمی‌رسد. این خودش تضمین می‌کند viewer هرگز تاریخچه را نمی‌بیند.
    $this->get('/contact-submissions')->assertForbidden();
});
