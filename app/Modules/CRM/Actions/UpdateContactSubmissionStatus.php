<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\CRM\Models\ContactSubmission;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Facades\CauserResolver;

class UpdateContactSubmissionStatus
{
    public function handle(ContactSubmission $submission, ContactSubmissionStatus $status, User $actor): ContactSubmission
    {
        Gate::forUser($actor)->authorize('update', $submission);

        $attributes = ['status' => $status->value];

        if ($status === ContactSubmissionStatus::Read && $submission->read_at === null) {
            $attributes['read_at'] = now();
        }

        if ($status === ContactSubmissionStatus::Replied && $submission->replied_at === null) {
            $attributes['replied_at'] = now();
            $attributes['replied_by_user_id'] = $actor->id;
        }

        // causer رکورد خودکار activity_log (از LogsActivity روی ContactSubmission)
        // صریح همین $actor است، نه Auth::user() پیش‌فرض پکیج — اگر بعداً این Action
        // از یک context بدون session (job/queue) صدا زده شود، causer گم نمی‌شود.
        // ریست در finally چون CauserResolver singleton سراسری است و نباید روی
        // لاگ‌های دیگر بعد از این درخواست نشت کند.
        CauserResolver::setCauser($actor);

        try {
            $submission->update($attributes);
        } finally {
            CauserResolver::setCauser(null);
        }

        return $submission;
    }
}
