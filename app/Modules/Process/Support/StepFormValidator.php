<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessStep;

/**
 * فقط منبع واحد اعتبارسنجی مقادیر فرم اضافه‌ی یک مرحله (step_form_fields،
 * بخش ۳ Session جاری) — هم ApproveProcessStep هم RejectProcessStep از همین
 * استفاده می‌کنند تا دو بار نوشته نشود. قاعده‌ی نوع فیلد از FormFieldValidator
 * (منبع مشترک با فرم درخواست آزاد NewProcessRequest/UpdateProcessInstanceRequest) می‌آید.
 */
class StepFormValidator
{
    /**
     * @param  array<string, mixed>  $stepData
     * @return array<string, mixed> فقط کلیدهای واقعاً تعریف‌شده در step_form_fields
     */
    public static function validate(ProcessStep $step, array $stepData): array
    {
        return FormFieldValidator::validate($step->formFields, $stepData);
    }
}
