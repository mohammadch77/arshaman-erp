<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectLeave
{
    /**
     * $rejectionReason اختیاری است — رد بدون توضیح هم مجاز است. رشته خالی یا
     * فقط فاصله به null تبدیل می‌شود تا در UI بین «دلیلی نوشته نشده» و «یک
     * رشته خالی ذخیره شده» تفاوتی نماند و شرط نمایش یک چیز ساده بماند.
     *
     * این ستون جدا از leaves.reason است: آن دلیل خودِ درخواست کارمند است،
     * این دلیل تصمیم مدیر — دو نقش متفاوت، پس دو ستون.
     */
    public function handle(Leave $leave, User $actor, ?string $rejectionReason = null): Leave
    {
        Gate::forUser($actor)->authorize('review', [Leave::class, $leave->owner_company_id]);

        // چون ProcessEngine::completeInstance() هم خودش این Action را (به‌عنوان
        // تنها مسیر مجاز تغییر leave_status) صدا می‌زند، این چک را نمی‌بندد —
        // آن لحظه instance از قبل به rejected تغییر کرده. فقط مسیر مستقیم/موازی
        // روی یک instance واقعاً «در جریان» مسدود می‌شود.
        if (app(ProcessEngine::class)->hasActiveInstance($leave)) {
            throw ValidationException::withMessages([
                'leave_status' => 'این درخواست از طریق فرایند سازمانی در حال بررسی است.',
            ]);
        }

        if ($leave->leave_status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'leave_status' => 'فقط درخواست‌های در انتظار قابل رد هستند.',
            ]);
        }

        $rejectionReason = trim((string) $rejectionReason);

        return DB::transaction(function () use ($leave, $actor, $rejectionReason) {
            $leave->update([
                'leave_status' => LeaveStatus::Rejected,
                'approved_by_user_id' => $actor->id,
                'rejection_reason' => $rejectionReason !== '' ? $rejectionReason : null,
            ]);

            return $leave;
        });
    }
}
