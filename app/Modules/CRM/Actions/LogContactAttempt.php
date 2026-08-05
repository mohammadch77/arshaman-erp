<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\ContactAttemptOutcome;
use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\CRM\Models\ContactSubmission;
use App\Modules\CRM\Models\ContactSubmissionAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Facades\CauserResolver;

class LogContactAttempt
{
    /**
     * ثبت یک تلاش تماس + به‌روزرسانی احتمالی status، هر دو در یک تراکنش.
     *
     * قانون تغییر status بر اساس outcome:
     * - answered_resolved → replied (+ replied_at/replied_by_user_id مثل UpdateContactSubmissionStatus)
     * - answered_followup_needed / will_call_back → in_progress
     * - no_answer / busy / wrong_number → فقط اگر status فعلی new بود، به in_progress
     *   تغییر می‌کند؛ در غیر این صورت دست‌نخورده می‌ماند (مثلاً یک تماس بی‌پاسخ روی
     *   پیامی که از قبل in_progress یا replied است، نباید عقب‌گرد بدهد).
     */
    public function handle(ContactSubmission $submission, ContactAttemptOutcome $outcome, ?string $note, User $actor): ContactSubmissionAttempt
    {
        Gate::forUser($actor)->authorize('update', $submission);

        return DB::transaction(function () use ($submission, $outcome, $note, $actor) {
            $attempt = ContactSubmissionAttempt::create([
                'owner_company_id' => $submission->owner_company_id,
                'contact_submission_id' => $submission->id,
                'attempted_by_user_id' => $actor->id,
                'outcome' => $outcome->value,
                'note' => $note,
                'attempted_at' => now(),
            ]);

            $attributes = $this->resolveStatusUpdate($submission, $outcome, $actor);

            if ($attributes !== null) {
                // همان دلیل CauserResolver در UpdateContactSubmissionStatus: causer
                // رکورد خودکار activity_log باید صریح $actor باشد، نه Auth::user() پیش‌فرض.
                CauserResolver::setCauser($actor);

                try {
                    $submission->update($attributes);
                } finally {
                    CauserResolver::setCauser(null);
                }
            }

            return $attempt;
        });
    }

    /**
     * @return array<string, mixed>|null  null یعنی status نباید تغییر کند.
     */
    protected function resolveStatusUpdate(ContactSubmission $submission, ContactAttemptOutcome $outcome, User $actor): ?array
    {
        if ($outcome === ContactAttemptOutcome::AnsweredResolved) {
            $attributes = ['status' => ContactSubmissionStatus::Replied->value];

            if ($submission->replied_at === null) {
                $attributes['replied_at'] = now();
                $attributes['replied_by_user_id'] = $actor->id;
            }

            return $attributes;
        }

        if (in_array($outcome, [ContactAttemptOutcome::AnsweredFollowupNeeded, ContactAttemptOutcome::WillCallBack], true)) {
            return ['status' => ContactSubmissionStatus::InProgress->value];
        }

        // no_answer / busy / wrong_number
        if ($submission->status === ContactSubmissionStatus::New) {
            return ['status' => ContactSubmissionStatus::InProgress->value];
        }

        return null;
    }
}
