<?php

namespace App\Modules\Process\Support;

use Illuminate\Support\Facades\Validator;

/**
 * منبع واحد قاعده‌ی اعتبارسنجی «فیلد فرم» — همان ساختار key/label/type که هم
 * process_definitions.request_form_fields هم process_steps.step_form_fields
 * از آن استفاده می‌کنند. StepFormValidator (مرحله) و UpdateProcessInstanceRequest/
 * NewProcessRequest (فرم درخواست) همه از همین یک متد rules() استفاده می‌کنند
 * تا قاعده‌ی نوع فیلد فقط یک‌جا تعریف بماند.
 */
class FormFieldValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, string>>
     */
    public static function rules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules[$field['key']] = match ($field['type'] ?? 'text') {
                'number' => ['required', 'numeric'],
                'boolean' => ['boolean'],
                // فایل تا این لحظه از قبل آپلود و به مسیر ذخیره‌شده تبدیل شده
                // (نگاه کن ProcessFileUploader::store) — اینجا فقط حضور همان
                // رشته‌ی مسیر چک می‌شود.
                'file' => ['required', 'string'],
                default => ['required', 'string', 'max:2000'],
            };
        }

        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> فقط کلیدهای واقعاً تعریف‌شده در $fields
     */
    public static function validate(array $fields, array $data): array
    {
        if ($fields === []) {
            return [];
        }

        $rules = self::rules($fields);

        Validator::make($data, $rules)->validate();

        return array_intersect_key($data, $rules);
    }
}
