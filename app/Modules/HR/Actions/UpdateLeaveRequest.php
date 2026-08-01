<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveType;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Services\LeaveScheduler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * ویرایش یک درخواست مرخصی توسط خودِ کارمند — فقط تا قبل از تصمیم مدیر.
 *
 * authorize داخل خود Action (CLAUDE.md بند ۹) و از طریق `updateSelf` که هر دو
 * شرط را با هم بررسی می‌کند: مالکیت رکورد **و** وضعیت `pending`. اگر شرط وضعیت
 * فقط در UI بود، هر مسیر دیگری می‌توانست یک مرخصی تأییدشده را عوض کند و
 * جمع ماهانه و فیش حقوقی همان دوره را بی‌سروصدا از اعتبار بیندازد.
 */
class UpdateLeaveRequest
{
    /**
     * @param  array{leave_type: string, start_date: string, end_date: string, start_time?: ?string, end_time?: ?string, reason?: ?string}  $data
     */
    public function handle(Leave $leave, array $data, User $actor): Leave
    {
        Gate::forUser($actor)->authorize('updateSelf', $leave);

        $scheduler = app(LeaveScheduler::class);

        Validator::make($data, $scheduler->rules())->validate();
        $scheduler->assertHourlyShape($data);

        // خودِ این رکورد از بررسی تداخل کنار گذاشته می‌شود، وگرنه هر ویرایشی
        // (حتی عوض‌کردن فقط دلیل) با نسخه قبلی خودش تداخل می‌گرفت.
        $employee = $leave->employee()->withoutGlobalScopes()->firstOrFail();
        $scheduler->assertNoOverlap($employee, $data, $leave->id);

        $measured = $scheduler->measure($employee, $data);
        $isHourly = $data['leave_type'] === LeaveType::Hourly->value;

        return DB::transaction(function () use ($leave, $data, $measured, $isHourly, $actor) {
            $leave->update([
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                // اگر نوع از ساعتی به روزانه عوض شود، ساعت‌های قبلی باید پاک
                // شوند وگرنه یک رکورد روزانه با ساعت باقی‌مانده گیج‌کننده می‌ماند.
                'start_time' => $isHourly ? $data['start_time'] : null,
                'end_time' => $isHourly ? $data['end_time'] : null,
                'days_count' => $measured['days_count'],
                'hours_count' => $measured['hours_count'],
                'reason' => $data['reason'] ?? null,
                'updated_by_user_id' => $actor->id,
            ]);

            return $leave->fresh();
        });
    }
}
