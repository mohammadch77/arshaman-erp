<?php

namespace App\Modules\Process\Services;

use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceFieldValue;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Models\ProcessInstanceLogFieldValue;
use Illuminate\Support\Collection;

/**
 * منبع واحد نگاشت field_key ↔ process_form_field_id برای یک formable مشخص، و
 * ذخیره‌ی دسته‌ای مقادیر روی process_instance_field_values/
 * process_instance_log_field_values — تا این نگاشت در Engine/Action های
 * مختلف (NewProcessRequest، ApproveProcessStep/RejectProcessStep،
 * UpdateProcessInstanceRequest) دوباره نوشته نشود.
 */
class ProcessFormFieldResolver
{
    /**
     * @return Collection<string, ProcessFormField> کلید = field_key
     */
    public function fieldsFor(string $formableType, string $formableId): Collection
    {
        return ProcessFormField::query()
            ->where('formable_type', $formableType)
            ->where('formable_id', $formableId)
            ->orderBy('display_order')
            ->get()
            ->keyBy('field_key');
    }

    /**
     * @param  array<string, mixed>  $values  کلید = field_key، از قبل با FormFieldValidator اعتبارسنجی‌شده
     */
    public function storeForInstance(ProcessInstance $instance, string $formableType, string $formableId, array $values): void
    {
        $fields = $this->fieldsFor($formableType, $formableId);

        foreach ($values as $key => $value) {
            $field = $fields->get($key);

            if ($field === null) {
                continue;
            }

            ProcessInstanceFieldValue::updateOrCreate(
                ['process_instance_id' => $instance->id, 'process_form_field_id' => $field->id],
                ['value' => $this->stringify($value)],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $values  کلید = field_key
     */
    public function storeForLog(ProcessInstanceLog $log, string $formableId, array $values): void
    {
        $fields = $this->fieldsFor(ProcessFormField::FORMABLE_STEP, $formableId);

        foreach ($values as $key => $value) {
            $field = $fields->get($key);

            if ($field === null) {
                continue;
            }

            ProcessInstanceLogFieldValue::create([
                'process_instance_log_id' => $log->id,
                'process_form_field_id' => $field->id,
                'value' => $this->stringify($value),
            ]);
        }
    }

    /**
     * @return array<string, mixed> کلید = field_key
     */
    public function valuesForInstance(ProcessInstance $instance): array
    {
        return $instance->fieldValues()
            ->with('formField')
            ->get()
            ->filter(fn ($row) => $row->formField !== null)
            ->mapWithKeys(fn ($row) => [$row->formField->field_key => $row->value])
            ->all();
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
