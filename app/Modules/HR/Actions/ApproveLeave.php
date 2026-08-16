<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveLeave
{
    /**
     * این Action هم مستقیم از پنل ادمین صدا زده می‌شود، هم خودِ
     * ProcessEngine::completeInstance() آن را (به‌عنوان تنها مسیر مجاز تغییر
     * leave_status) وقتی یک فرایند به end_approved می‌رسد صدا می‌زند. در حالت
     * دوم، instance از قبل به approved تغییر کرده — پس چک hasActiveInstance()
     * زیر آن فراخوانی را مسدود نمی‌کند، فقط مسیر مستقیم/موازی روی یک instance
     * واقعاً «در جریان» را می‌بندد.
     */
    public function handle(Leave $leave, User $actor): Leave
    {
        Gate::forUser($actor)->authorize('review', [Leave::class, $leave->owner_company_id]);

        if (app(ProcessEngine::class)->hasActiveInstance($leave)) {
            throw ValidationException::withMessages([
                'leave_status' => 'این درخواست از طریق فرایند سازمانی در حال بررسی است.',
            ]);
        }

        if ($leave->leave_status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'leave_status' => 'فقط درخواست‌های در انتظار قابل تأیید هستند.',
            ]);
        }

        return DB::transaction(function () use ($leave, $actor) {
            $leave->update([
                'leave_status' => LeaveStatus::Approved,
                'approved_by_user_id' => $actor->id,
            ]);

            return $leave;
        });
    }
}
