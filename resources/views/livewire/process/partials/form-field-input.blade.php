{{--
    ورودی مشترک یک فیلد فرم فرایند (ProcessFormField واقعی، نه دیگر آرایه‌ی
    JSON) — با هر سه پنل (NewProcessRequest، MyProcessTasks، MyProcessRequests)
    استفاده می‌شود تا این تطبیق نوع فیلد چندبار نوشته نشود.

    پارامترها:
    - $field: App\Modules\Process\Models\ProcessFormField
    - $valuePrefix: پیشوند wire:model برای مقدار (مثلاً 'formValues')
    - $filePrefix: پیشوند wire:model برای فایل آپلودی (مثلاً 'fileUploads')
--}}
@if($field->field_type === 'textarea')
    <x-textarea
        label="{{ $field->label }}"
        wire:model="{{ $valuePrefix }}.{{ $field->field_key }}"
        rows="3"
    />
@elseif($field->field_type === 'number')
    <x-input
        type="number"
        label="{{ $field->label }}"
        wire:model="{{ $valuePrefix }}.{{ $field->field_key }}"
    />
@elseif($field->field_type === 'boolean')
    <x-checkbox
        label="{{ $field->label }}"
        wire:model="{{ $valuePrefix }}.{{ $field->field_key }}"
    />
@elseif($field->field_type === 'select')
    <x-select
        label="{{ $field->label }}"
        wire:model="{{ $valuePrefix }}.{{ $field->field_key }}"
        :options="$field->options ?? []"
        option-value="value"
        option-label="label"
        placeholder="انتخاب کنید"
    />
@elseif($field->field_type === 'file')
    <x-file
        label="{{ $field->label }}"
        wire:model="{{ $filePrefix }}.{{ $field->field_key }}"
        :icon="theme_icon('file')"
        hint="فرمت‌های مجاز: {{ implode('، ', config('processes.file_upload.allowed_extensions')) }} — حداکثر {{ round(config('processes.file_upload.max_kilobytes') / 1024, 1) }} مگابایت"
    />
@else
    <x-input
        label="{{ $field->label }}"
        wire:model="{{ $valuePrefix }}.{{ $field->field_key }}"
    />
@endif
