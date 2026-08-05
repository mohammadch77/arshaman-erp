<?php

use App\Livewire\CRM\ContactSubmissionIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\LogContactAttempt;
use App\Modules\CRM\Enums\ContactAttemptOutcome;
use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\CRM\Models\ContactSubmission;
use App\Modules\CRM\Models\ContactSubmissionAttempt;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function csaMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function csaGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => csaMakeRole($roleName)->id,
    ]);
}

function csaMakeSubmission(Company $company, string $phone = '09121234567', string $status = 'new'): ContactSubmission
{
    return ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => $phone,
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
        'status' => $status,
    ]);
}

it('sets status to replied when the outcome is answered_resolved', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    csaGiveRole($operator, $company, 'operator');
    $submission = csaMakeSubmission($company);

    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::AnsweredResolved, null, $operator);

    $submission->refresh();
    expect($submission->status)->toBe(ContactSubmissionStatus::Replied);
    expect($submission->replied_at)->not->toBeNull();
    expect($submission->replied_by_user_id)->toBe($operator->id);
});

it('sets status to in_progress when the outcome is answered_followup_needed or will_call_back', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    csaGiveRole($operator, $company, 'operator');

    $submissionA = csaMakeSubmission($company, '09121234567');
    app(LogContactAttempt::class)->handle($submissionA->fresh(), ContactAttemptOutcome::AnsweredFollowupNeeded, null, $operator);
    expect($submissionA->fresh()->status)->toBe(ContactSubmissionStatus::InProgress);

    $submissionB = csaMakeSubmission($company, '09121234568');
    app(LogContactAttempt::class)->handle($submissionB->fresh(), ContactAttemptOutcome::WillCallBack, null, $operator);
    expect($submissionB->fresh()->status)->toBe(ContactSubmissionStatus::InProgress);
});

it('moves a new submission to in_progress on no_answer/busy/wrong_number but leaves a non-new submission untouched', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    csaGiveRole($operator, $company, 'operator');

    foreach ([ContactAttemptOutcome::NoAnswer, ContactAttemptOutcome::Busy, ContactAttemptOutcome::WrongNumber] as $index => $outcome) {
        $submission = csaMakeSubmission($company, '0912123456'.$index, 'new');
        app(LogContactAttempt::class)->handle($submission->fresh(), $outcome, null, $operator);
        expect($submission->fresh()->status)->toBe(ContactSubmissionStatus::InProgress);
    }

    // روی پیامی که از قبل replied است، یک تماس بی‌پاسخ نباید عقب‌گرد بدهد.
    $repliedSubmission = csaMakeSubmission($company, '09121234599', 'replied');
    app(LogContactAttempt::class)->handle($repliedSubmission->fresh(), ContactAttemptOutcome::NoAnswer, null, $operator);
    expect($repliedSubmission->fresh()->status)->toBe(ContactSubmissionStatus::Replied);
});

it('persists every attempt separately when multiple users call the same submission in sequence', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operatorA = User::factory()->create(['is_super_admin' => false, 'full_name' => 'اپراتور اول']);
    $operatorB = User::factory()->create(['is_super_admin' => false, 'full_name' => 'اپراتور دوم']);
    csaGiveRole($operatorA, $company, 'operator');
    csaGiveRole($operatorB, $company, 'operator');

    $submission = csaMakeSubmission($company);

    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::NoAnswer, 'جواب نداد', $operatorA);
    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::Busy, 'اشغال بود', $operatorB);
    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::AnsweredResolved, 'حل شد', $operatorA);

    $attempts = ContactSubmissionAttempt::where('contact_submission_id', $submission->id)->orderBy('attempted_at')->get();

    expect($attempts)->toHaveCount(3);
    expect($attempts[0]->attempted_by_user_id)->toBe($operatorA->id);
    expect($attempts[0]->outcome)->toBe(ContactAttemptOutcome::NoAnswer);
    expect($attempts[1]->attempted_by_user_id)->toBe($operatorB->id);
    expect($attempts[1]->outcome)->toBe(ContactAttemptOutcome::Busy);
    expect($attempts[2]->attempted_by_user_id)->toBe($operatorA->id);
    expect($attempts[2]->outcome)->toBe(ContactAttemptOutcome::AnsweredResolved);
});

it('rejects an invalid outcome at the database CHECK constraint layer', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL واقعی اعمال می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    $submission = csaMakeSubmission($company);

    expect(fn () => ContactSubmissionAttempt::create([
        'owner_company_id' => $company->id,
        'contact_submission_id' => $submission->id,
        'attempted_by_user_id' => $operator->id,
        'outcome' => 'not_a_real_outcome',
        'note' => null,
        'attempted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects logging a contact attempt by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    csaGiveRole($intruder, $company, 'viewer');
    $submission = csaMakeSubmission($company);

    expect(fn () => app(LogContactAttempt::class)->handle($submission, ContactAttemptOutcome::NoAnswer, null, $intruder))
        ->toThrow(AuthorizationException::class);

    expect(ContactSubmissionAttempt::count())->toBe(0);
    expect($submission->fresh()->status)->toBe(ContactSubmissionStatus::New);
});

it('returns 403 for a role without access when trying to open the attempt-logging panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $viewer = User::factory()->create(['is_super_admin' => false]);
    csaGiveRole($viewer, $company, 'viewer');

    $this->actingAs($viewer);
    session(['active_company_id' => $company->id]);

    $this->get('/contact-submissions')->assertForbidden();
});

it('shows the combined status-change and call-attempt history in correct chronological order', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false, 'full_name' => 'اپراتور تست']);
    csaGiveRole($operator, $company, 'operator');

    $submission = csaMakeSubmission($company);

    // ستون‌های attempted_at/created_at دقت ثانیه‌ای دارند و در تست دو فراخوانی
    // پشت‌سرهم می‌توانند در همان ثانیه بیفتند — برای اینکه تست واقعاً «ترتیب
    // زمانی درست» را بسنجد نه شانس اجرا، زمان را صریح چند دقیقه جلو می‌بریم.
    Carbon::setTestNow(Carbon::parse('2026-08-09 10:00:00'));
    // تلاش اول: بی‌پاسخ، status از new به in_progress می‌رود (یک رویداد attempt + یک status_change).
    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::NoAnswer, 'یادداشت اول', $operator);

    Carbon::setTestNow(Carbon::parse('2026-08-09 10:05:00'));
    // تلاش دوم: حل شد، status به replied می‌رود.
    app(LogContactAttempt::class)->handle($submission->fresh(), ContactAttemptOutcome::AnsweredResolved, 'یادداشت دوم', $operator);

    Carbon::setTestNow();

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    $component = Livewire::test(ContactSubmissionIndex::class)
        ->call('openHistory', $submission->id);

    $history = $component->get('history');

    expect($history)->toHaveCount(4);

    // جدیدترین رویداد اول (sortByDesc('at')) — آخرین status_change (به replied).
    expect($history[0]['type'])->toBe('status_change');
    expect($history[0]['to'])->toBe('replied');

    // بعدش تلاش دوم (answered_resolved).
    expect($history[1]['type'])->toBe('attempt');
    expect($history[1]['outcome'])->toBe(ContactAttemptOutcome::AnsweredResolved);

    // بعدش status_change اول (به in_progress).
    expect($history[2]['type'])->toBe('status_change');
    expect($history[2]['to'])->toBe('in_progress');

    // قدیمی‌ترین رویداد آخر — تلاش اول (no_answer).
    expect($history[3]['type'])->toBe('attempt');
    expect($history[3]['outcome'])->toBe(ContactAttemptOutcome::NoAnswer);
});
