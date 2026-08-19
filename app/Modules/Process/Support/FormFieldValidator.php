<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessFormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * منبع واحد قاعده‌ی اعتبارسنجی «فیلد فرم» روی مدل‌های واقعی ProcessFormField
 * (نه دیگر آرایه‌ی JSON خام) — StepFormValidator (مرحله) و NewProcessRequest/
 * UpdateProcessInstanceRequest (فرم درخواست) همه از همین یک متد rules()
 * استفاده می‌کنند تا قاعده‌ی نوع فیلد فقط یک‌جا تعریف بماند.
 */
class FormFieldValidator
{
    /**
     * @param  Collection<int|string, ProcessFormField>  $fields
     * @return array<string, array<int, string>>
     */
    public static function rules(Collection $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $required = $field->is_required ? 'required' : 'nullable';

            $rules[$field->field_key] = match ($field->field_type) {
                'number' => [$required, 'numeric'],
                'boolean' => ['boolean'],
                'select' => [$required, 'string', 'in:'.implode(',', array_column($field->options ?? [], 'value'))],
                // فایل تا این لحظه از قبل آپلود و به مسیر ذخیره‌شده تبدیل شده
                // (نگاه کن ProcessFileUploader::store) — اینجا فقط حضور همان
                // رشته‌ی مسیر چک می‌شود.
                'file' => [$required, 'string'],
                default => [$required, 'string', 'max:2000'],
            };
        }

        return $rules;
    }

    /**
     * @param  Collection<int|string, ProcessFormField>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> فقط کلیدهای واقعاً تعریف‌شده در $fields
     */
    public static function validate(Collection $fields, array $data): array
    {
        if ($fields->isEmpty()) {
            return [];
        }

        $rules = self::rules($fields);

        Validator::make($data, $rules)->validate();

        return array_intersect_key($data, $rules);
    }
}
