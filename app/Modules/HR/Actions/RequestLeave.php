<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Enums\RecordedBy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Services\LeaveScheduler;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RequestLeave
{
    /**
     * @param  array{leave_type: string, start_date: string, end_date: string, start_time?: ?string, end_time?: ?string, reason?: ?string}  $data
     *
     * $requestedBy مشخص می‌کند درخواست از پنل خودِ کارمند آمده یا پنل ادمین —
     * authorize متفاوت بر اساس آن (همان الگوی RecordAttendance).
     */
    public function handle(Employee $employee, array $data, User $actor, RecordedBy $requestedBy): Leave
    {
        if ($requestedBy === RecordedBy::SelfService) {
            Gate::forUser($actor)->authorize('requestSelf', [Leave::class, $employee]);
        } else {
            Gate::forUser($actor)->authorize('requestAny', [Leave::class, $employee->owner_company_id]);
        }

        $scheduler = app(LeaveScheduler::class);

        Validator::make($data, $scheduler->rules())->validate();
        $scheduler->assertHourlyShape($data);
        $scheduler->assertNoOverlap($employee, $data);

        $measured = $scheduler->measure($employee, $data);
        $isHourly = $data['leave_type'] === LeaveType::Hourly->value;

        return DB::transaction(function () use ($employee, $data, $actor, $isHourly, $measured) {
            $leave = Leave::create([
                'employee_id' => $employee->id,
                'owner_company_id' => $employee->owner_company_id,
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'start_time' => $isHourly ? $data['start_time'] : null,
                'end_time' => $isHourly ? $data['end_time'] : null,
                'days_count' => $measured['days_count'],
                'hours_count' => $measured['hours_count'],
                'leave_status' => LeaveStatus::Pending,
                'reason' => $data['reason'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            // اگر شرکت هدف یک فرایند تأیید مرخصی فعال داشته باشد، خودکار وارد
            // آن می‌شود؛ در غیر این صورت null برمی‌گردد و leave_status همان
            // pending عادی می‌ماند — رفتار قبلی (بدون فرایند) دست‌نخورده است.
            app(ProcessEngine::class)->startForSubjectIfActive($leave, $actor);

            return $leave;
        });
    }
}
