<?php

namespace App\Modules\Process\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Auth\Access\AuthorizationException;

class ProcessInstancePolicy
{
    /**
     * درخواست‌دهنده، هر کسی که در زنجیره‌ی تصمیم واقعاً اقدامی ثبت کرده
     * (اعم از تأیید/رد یک مرحله‌ی قبلی)، مسئول فعلی مرحله‌ی در جریان (تا بتواند
     * تاریخچه را قبل از تصمیم‌گیری هم ببیند)، یا holding_admin همان شرکت.
     */
    public function view(User $user, ProcessInstance $instance): bool
    {
        if ($instance->started_by_user_id === $user->id) {
            return true;
        }

        if ($user->hasRoleInCompany($instance->owner_company_id, 'holding_admin')) {
            return true;
        }

        if ($instance->logs()->where('actor_user_id', $user->id)->exists()) {
            return true;
        }

        return $this->canActOnCurrentStep($user, $instance);
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
     * فهرست نظارتی کل شرکت (ProcessOversight) — فقط holding_admin، مستقل از
     * این‌که خودش در زنجیره‌ی تصمیم شرکت داشته یا نه.
     */
    public function oversight(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId, 'holding_admin');
    }

    /**
     * یادآوری فقط روی یک instance واقعاً «در جریان» و فقط توسط holding_admin
     * همان شرکت — دقیقاً همان نقشی که به ProcessOversight دسترسی دارد.
     */
    public function remind(User $user, ProcessInstance $instance): bool
    {
        return $instance->status === ProcessStatus::InProgress
            && $user->hasRoleInCompany($instance->owner_company_id, 'holding_admin');
    }

    /**
     * تنها منبع واحد حقیقت برای «آیا این actor مجاز بازگردانی آخرین تصمیم
     * خودش است؟» همان ProcessEngine::canReverseLastDecision است.
     */
    public function reverseLastDecision(User $user, ProcessInstance $instance): bool
    {
        return app(ProcessEngine::class)->canReverseLastDecision($instance, $user);
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
