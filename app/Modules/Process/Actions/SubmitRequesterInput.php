<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Support\StepFormValidator;
use RuntimeException;

/**
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است: مستقل از این‌که از
 * کجا صدا زده شده، actor واقعاً باید همان کسی باشد که این instance را شروع
 * کرده (ProcessEngine::assertActorIsRequester) — نه یک نقش/شخص واگذارشده
 * مثل مرحله‌ی approval.
 */
class SubmitRequesterInput
{
    public function __construct(private readonly ProcessEngine $engine) {}

    /**
     * @param  array<string, mixed>  $data  مقادیر فرم همین مرحله (step_form_fields الزامی است)
     */
    public function handle(ProcessInstance $instance, User $actor, array $data): void
    {
        $step = $instance->currentStep;

        if ($step === null || $step->step_type !== StepType::RequesterInput) {
            throw new RuntimeException('مرحله‌ی فعلی این فرایند یک مرحله‌ی تکمیل اطلاعات نیست.');
        }

        $this->engine->assertActorIsRequester($instance, $actor);

        $stepData = StepFormValidator::validate($step, $data);

        $this->engine->submitRequesterInput($instance, $actor, $stepData);
    }
}
