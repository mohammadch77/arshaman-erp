<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessStep;
use Illuminate\Support\Facades\Validator;

/**
 * فقط منبع واحد اعتبارسنجی مقادیر فرم اضافه‌ی یک مرحله (step_form_fields،
 * بخش ۳ Session جاری) — هم ApproveProcessStep هم RejectProcessStep از همین
 * استفاده می‌کنند تا دو بار نوشته نشود. همان الگوی مفهومی اعتبارسنجی فرم
 * درخواست آزاد در NewProcessRequest.
 */
class StepFormValidator
{
    /**
     * @param  array<string, mixed>  $stepData
     * @return array<string, mixed> فقط کلیدهای واقعاً تعریف‌شده در step_form_fields
     */
    public static function validate(ProcessStep $step, array $stepData): array
    {
        $fields = $step->step_form_fields ?? [];

        if ($fields === []) {
            return [];
        }

        $rules = [];
        foreach ($fields as $field) {
            $rules[$field['key']] = match ($field['type'] ?? 'text') {
                'number' => ['required', 'numeric'],
                'boolean' => ['boolean'],
                default => ['required', 'string', 'max:2000'],
            };
        }

        Validator::make($stepData, $rules)->validate();

        return array_intersect_key($stepData, $rules);
    }
}
