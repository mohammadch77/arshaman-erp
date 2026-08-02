<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Leave;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveLeave
{
    public function handle(Leave $leave, User $actor): Leave
    {
        Gate::forUser($actor)->authorize('review', [Leave::class, $leave->owner_company_id]);

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
