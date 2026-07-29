<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Models\Leave;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectLeave
{
    public function handle(Leave $leave, User $actor): Leave
    {
        Gate::forUser($actor)->authorize('review', Leave::class);

        if ($leave->leave_status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages([
                'leave_status' => 'فقط درخواست‌های در انتظار قابل رد هستند.',
            ]);
        }

        return DB::transaction(function () use ($leave, $actor) {
            $leave->update([
                'leave_status' => LeaveStatus::Rejected,
                'approved_by_user_id' => $actor->id,
            ]);

            return $leave;
        });
    }
}
