<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use RuntimeException;

/**
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است: مستقل از این‌که
 * از کجا صدا زده شده، actor واقعاً باید مجاز مرحله‌ی approval فعلی باشد.
 */
class RejectProcessStep
{
    public function __construct(private readonly ProcessEngine $engine) {}

    public function handle(ProcessInstance $instance, User $actor, ?string $comment = null): void
    {
        $step = $instance->currentStep;

        if ($step === null || $step->step_type !== StepType::Approval) {
            throw new RuntimeException('مرحله‌ی فعلی این فرایند یک مرحله‌ی تأیید نیست.');
        }

        $this->engine->assertActorAuthorizedForStep($instance, $step, $actor);

        $this->engine->advance($instance, TransitionResult::Rejected->value, $actor, $comment);
    }
}
