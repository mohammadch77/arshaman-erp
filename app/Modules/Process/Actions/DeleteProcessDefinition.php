<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * حذف واقعی یک تعریف فرایند — با محافظت داده‌ی تاریخی.
 *
 * تصمیم طراحی: process_instances/process_instance_logs هر دو FK با RESTRICT
 * (بدون CASCADE) به process_steps دارند (بند ۹ سند طراحی)، پس اگر این تعریف
 * حتی یک instance (حتی تاریخی/تمام‌شده) داشته باشد، حذف واقعی مراحلش در سطح
 * دیتابیس شکست می‌خورد — و نباید هم موفق شود، چون تاریخچه‌ی واقعی هرگز نباید از
 * دست برود (بند ۳ CLAUDE.md: هرگز حذف فیزیکی). در آن حالت فقط soft-delete
 * می‌شود. اگر تعریف کاملاً استفاده‌نشده باشد، حذف کامل (مراحل+گذارها+خودِ
 * تعریف) در یک تراکنش واحد امن است.
 */
class DeleteProcessDefinition
{
    public function handle(User $actor, ProcessDefinition $definition): bool
    {
        Gate::forUser($actor)->authorize('delete', $definition);

        $hasInstances = $definition->instances()->withoutGlobalScope('owner_company')->exists();

        if ($hasInstances) {
            $definition->delete();

            return false;
        }

        DB::transaction(function () use ($definition) {
            ProcessTransition::whereIn('from_step_id', $definition->steps()->pluck('id'))->delete();
            $definition->steps()->delete();
            $definition->forceDelete();
        });

        return true;
    }
}
