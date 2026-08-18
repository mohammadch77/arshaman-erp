<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceLog;
use Illuminate\Support\Facades\Gate;

/**
 * فرستنده‌ی اصلی، قبل از این‌که مرحله‌ی فعلی هیچ اقدامی داشته باشد، می‌تواند
 * کل instance را لغو کند (بخش ۳ Session جاری) — چه فرایند آزاد چه وصل‌به‌ماژول.
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است — تنها منبع حقیقت
 * ProcessInstancePolicy::cancel.
 */
class CancelProcessInstance
{
    public function handle(User $actor, ProcessInstance $instance): void
    {
        Gate::forUser($actor)->authorize('cancel', $instance);

        $instance->status = ProcessStatus::Cancelled;
        $instance->completed_at = now();
        $instance->save();

        ProcessInstanceLog::create([
            'owner_company_id' => $instance->owner_company_id,
            'process_instance_id' => $instance->id,
            'step_id' => $instance->current_step_id,
            'actor_user_id' => $actor->id,
            'action' => LogAction::Cancelled,
            'comment' => null,
        ]);
    }
}
