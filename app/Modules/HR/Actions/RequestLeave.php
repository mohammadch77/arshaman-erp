<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Services\WorkCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestLeave
{
    /**
     * @param  array{leave_type: string, start_date: string, end_date: string, reason?: ?string}  $data
     *
     * $requestedBy مشخص می‌کند درخواست از پنل خودِ کارمند آمده یا پنل ادمین —
     * authorize متفاوت بر اساس آن (همان الگوی RecordAttendance).
     */
    public function handle(Employee $employee, array $data, User $actor, RecordedBy $requestedBy): Leave
    {
        if ($requestedBy === RecordedBy::SelfService) {
            Gate::forUser($actor)->authorize('requestSelf', [Leave::class, $employee]);
        } else {
            Gate::forUser($actor)->authorize('requestAny', Leave::class);
        }

        Validator::make($data, [
            'leave_type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ])->validate();

        $workCalendar = app(WorkCalendar::class);
        $daysCount = 0;

        for (
            $date = Carbon::parse($data['start_date']);
            $date->lte(Carbon::parse($data['end_date']));
            $date->addDay()
        ) {
            if ($workCalendar->isWorkday($date, $employee->owner_company_id)) {
                $daysCount++;
            }
        }

        return DB::transaction(fn () => Leave::create([
            'employee_id' => $employee->id,
            'owner_company_id' => $employee->owner_company_id,
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_count' => $daysCount,
            'leave_status' => LeaveStatus::Pending,
            'reason' => $data['reason'] ?? null,
            'created_by_user_id' => $actor->id,
        ]));
    }
}
