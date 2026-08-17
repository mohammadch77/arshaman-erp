<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessInstance;
use BackedEnum;
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
     * @return array<int, array{label: string, value: string}>
     */
    private static function forSubject(string $subjectType, ?Model $subject): array
    {
        if ($subject === null) {
            return [['label' => 'سوژه', 'value' => 'یافت نشد (احتمالاً حذف شده)']];
        }

        $fields = config("processes.subject_summary_fields.{$subjectType}", []);

        $summary = [];

        foreach ($fields as $path => $label) {
            $value = data_get($subject, $path);
            $summary[] = ['label' => $label, 'value' => self::formatValue($value)];
        }

        return $summary;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function forRequestData(ProcessInstance $instance): array
    {
        $fields = $instance->definition?->request_form_fields ?? [];
        $data = $instance->request_data ?? [];

        $summary = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;

            if ($key === null) {
                continue;
            }

            $summary[] = [
                'label' => $field['label'] ?? $key,
                'value' => self::formatValue($data[$key] ?? null),
            ];
        }

        return $summary;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : (string) $value->value;
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
