<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessFormFieldResolver;
use App\Support\Jalali;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * تنها منبع خلاصه‌سازی سوژه/درخواست برای پنل‌های «کارهای من»/«درخواست‌های من»
 * (هم MyProcessTasks هم MyProcessRequests از همین استفاده می‌کنند تا دو بار
 * نوشته نشود). طبق بند ۴ CLAUDE.md ماژول Process هرگز مستقیم مدل یک ماژول
 * دیگر را import نمی‌کند — نگاشت فیلد از config('processes.subject_summary_fields')
 * (داده، نه کد) می‌آید؛ اینجا فقط data_get() عمومی روی سوژه‌ی polymorphic اجرا
 * می‌شود.
 */
class ProcessSubjectSummary
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function forInstance(ProcessInstance $instance): array
    {
        if ($instance->subject_type !== null) {
            return self::forSubject($instance->subject_type, self::resolveSubject($instance));
        }

        return self::forRequestData($instance);
    }

    /**
     * @return array<int, array{label: string, value: string, is_file: bool}>
     */
    private static function forSubject(string $subjectType, ?Model $subject): array
    {
        if ($subject === null) {
            return [['label' => 'سوژه', 'value' => 'یافت نشد (احتمالاً حذف شده)', 'is_file' => false]];
        }

        $fields = config("processes.subject_summary_fields.{$subjectType}", []);

        $summary = [];

        foreach ($fields as $path => $label) {
            $value = data_get($subject, $path);
            $summary[] = ['label' => $label, 'value' => self::formatValue($value), 'is_file' => false];
        }

        return $summary;
    }

    /**
     * @return array<int, array{label: string, value: string, is_file: bool}>
     */
    private static function forRequestData(ProcessInstance $instance): array
    {
        $fields = $instance->definition?->formFields ?? collect();
        $data = app(ProcessFormFieldResolver::class)->valuesForInstance($instance);

        $summary = [];

        foreach ($fields as $field) {
            $isFile = $field->field_type === 'file';
            $rawValue = $data[$field->field_key] ?? null;

            // فیلد select مقدار خام (value) را ذخیره می‌کند، نه برچسب نمایشی —
            // اینجا با options خودِ فیلد به برچسب واقعی نگاشت می‌شود.
            $displayValue = $rawValue;
            if ($field->field_type === 'select' && $rawValue !== null) {
                $option = collect($field->options ?? [])->firstWhere('value', $rawValue);
                $displayValue = $option['label'] ?? $rawValue;
            }

            $summary[] = [
                'label' => $field->label,
                // فیلد file مسیر خام ذخیره‌شده را حمل می‌کند (نه یک متن نمایشی)
                // تا blade بتواند لینک دانلود واقعی بسازد — نگاه کن is_file پایین.
                'value' => $isFile ? (string) ($rawValue ?? '') : self::formatValue($displayValue),
                'is_file' => $isFile,
            ];
        }

        return $summary;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : (string) $value->value;
        }

        // ستون‌های تاریخ/زمان (مثل Leave.start_date/end_date با cast 'date') قبل از این
        // چک به is_scalar می‌رسیدند که false است (Carbon یک آبجکت است)، پس به شاخه‌ی
        // json_encode می‌افتادند و میلادی خام با صفرهای اضافی (created_at/timezone) نمایش
        // داده می‌شد. اینجا با همان تابع تبدیل شمسی موجود پروژه (App\Support\Jalali) نمایش
        // می‌شود. اگر ساعت دقیقاً نیمه‌شب بود (یعنی یک ستون DATE خالص، نه یک لحظه)، فقط
        // تاریخ نشان داده می‌شود؛ وگرنه تاریخ و ساعت با «-» جدا می‌شوند.
        if ($value instanceof DateTimeInterface) {
            $isDateOnly = $value->format('H:i:s') === '00:00:00';

            return $isDateOnly
                ? (Jalali::toDisplay($value) ?? '—')
                : (Jalali::toDisplay($value).' - '.Jalali::toDisplayTime($value));
        }

        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * همان الگوی ProcessEngine::resolveSubject — global scope مدل سوژه (مثل
     * BelongsToCompany) نباید این کوئری را فیلتر کند، چون این خلاصه‌سازی
     * مستقل از شرکت فعال session است.
     */
    private static function resolveSubject(ProcessInstance $instance): ?Model
    {
        if ($instance->subject_type === null || $instance->subject_id === null) {
            return null;
        }

        $subjectClass = $instance->subject_type;

        return $subjectClass::withoutGlobalScopes()->find($instance->subject_id);
    }
}
