<?php

namespace App\Modules\HR\Actions;

use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Leave;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * حذف یک درخواست مرخصی توسط خودِ کارمند — فقط تا قبل از تصمیم مدیر.
 *
 * حذف **نرم** است (بند ۳ CLAUDE.md: «هرگز حذف فیزیکی»). یک درخواست پس‌گرفته‌شده
 * هم بخشی از تاریخچه است؛ ضمن اینکه اگر رکورد فیزیکی پاک می‌شد، هیچ ردی از
 * اینکه چنین درخواستی وجود داشته باقی نمی‌ماند.
 */
class DeleteLeaveRequest
{
    public function handle(Leave $leave, User $actor): void
    {
        Gate::forUser($actor)->authorize('deleteSelf', $leave);

        DB::transaction(function () use ($leave, $actor) {
            // ثبت آخرین دست‌زننده پیش از حذف، تا در بازیابی احتمالی معلوم باشد
            // چه کسی پس گرفته است.
            $leave->forceFill(['updated_by_user_id' => $actor->id])->saveQuietly();

            $leave->delete();
        });
    }
}
