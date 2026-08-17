<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Support\StepFormValidator;
use RuntimeException;

/**
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است: مستقل از این‌که
 * از کجا صدا زده شده، actor واقعاً باید مجاز مرحله‌ی approval فعلی باشد.
 */
class ApproveProcessStep
{
    public function __construct(private readonly ProcessEngine $engine) {}

    /**
     * @param  array<string, mixed>  $stepData  مقادیر فرم اضافه‌ی خودِ این مرحله (بخش ۳
     *                                            Session جاری، اگر step_form_fields داشته باشد)
     */
    public function handle(ProcessInstance $instance, User $actor, ?string $comment = null, array $stepData = []): void
    {
        $step = $instance->currentStep;

        if ($step === null || $step->step_type !== StepType::Approval) {
            throw new RuntimeException('مرحله‌ی فعلی این فرایند یک مرحله‌ی تأیید نیست.');
        }

        $this->engine->assertActorAuthorizedForStep($instance, $step, $actor);

        $stepData = StepFormValidator::validate($step, $stepData);

        $this->engine->advance($instance, TransitionResult::Approved->value, $actor, $comment, $stepData);
    }
}
