<?php

namespace App\Modules\Process\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Auth\Access\AuthorizationException;

class ProcessInstancePolicy
{
    /**
     * درخواست‌دهنده، هر کسی که در زنجیره‌ی تصمیم واقعاً اقدامی ثبت کرده
     * (اعم از تأیید/رد یک مرحله‌ی قبلی)، یا holding_admin همان شرکت.
     */
    public function view(User $user, ProcessInstance $instance): bool
    {
        if ($instance->started_by_user_id === $user->id) {
            return true;
        }

        if ($user->hasRoleInCompany($instance->owner_company_id, 'holding_admin')) {
            return true;
        }

        return $instance->logs()->where('actor_user_id', $user->id)->exists();
    }

    public function approve(User $user, ProcessInstance $instance): bool
    {
        return $this->canActOnCurrentStep($user, $instance);
    }

    public function reject(User $user, ProcessInstance $instance): bool
    {
        return $this->canActOnCurrentStep($user, $instance);
    }

    /**
     * تنها منبع واحد حقیقت برای «آیا این کاربر مجاز تصمیم‌گیری روی مرحله‌ی
     * فعلی است؟» همان ProcessEngine::assertActorAuthorizedForStep است — این
     * Policy فقط آن را به یک بولین بی‌طرف‌شده تبدیل می‌کند، منطق تخصیص را
     * دوباره پیاده نمی‌کند.
     */
    private function canActOnCurrentStep(User $user, ProcessInstance $instance): bool
    {
        $step = $instance->currentStep;

        if ($step === null || $step->step_type !== StepType::Approval) {
            return false;
        }

        try {
            app(ProcessEngine::class)->assertActorAuthorizedForStep($instance, $step, $user);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
