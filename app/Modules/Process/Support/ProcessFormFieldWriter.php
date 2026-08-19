<?php

namespace App\Modules\Process\Support;

use App\Modules\Process\Models\ProcessFormField;

/**
 * منبع واحد نوشتن یک آرایه‌ی خام فیلد فرم (خروجی ProcessDefinitionForm —
 * key/label/type[/options]) به ردیف‌های واقعی process_form_fields — هم
 * CreateProcessDefinition هم UpdateProcessDefinition هم
 * CreateProcessDefinitionVersion از همین استفاده می‌کنند تا این نگاشت سه‌بار
 * نوشته نشود.
 */
class ProcessFormFieldWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, string> کلید = field_key، مقدار = process_form_fields.id تازه‌ساخته‌شده
     */
    public static function write(string $formableType, string $formableId, array $fields): array
    {
        $idsByKey = [];

        foreach (array_values($fields) as $order => $field) {
            $created = ProcessFormField::create([
                'formable_type' => $formableType,
                'formable_id' => $formableId,
                'field_key' => $field['key'],
                'label' => $field['label'],
                'field_type' => $field['type'],
                'is_required' => $field['type'] !== 'boolean',
                'options' => $field['type'] === 'select' ? ($field['options'] ?? []) : null,
                'display_order' => $order,
            ]);

            $idsByKey[$field['key']] = $created->id;
        }

        return $idsByKey;
    }
}
