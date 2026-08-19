<?php

namespace App\Modules\Process\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * جایگزین واقعی process_definitions.request_form_fields/process_steps.step_form_fields
 * (هر دو JSON قبلی). پلی‌مورفیک محض (formable_type/formable_id) بدون FK واقعی
 * روی formable_id — نمی‌توان یک ستون را هم‌زمان FK دو جدول متفاوت کرد؛ همان
 * الگوی subject_type/subject_id در ProcessInstance.
 */
class ProcessFormField extends Model
{
    use HasUuids;

    public const FORMABLE_DEFINITION = 'process_definition';

    public const FORMABLE_STEP = 'process_step';

    protected $fillable = [
        'formable_type',
        'formable_id',
        'field_key',
        'label',
        'field_type',
        'is_required',
        'options',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options' => 'array',
            'display_order' => 'integer',
        ];
    }

    /**
     * برخلاف morphTo استاندارد لاراول، این پلی‌مورفیک FK واقعی ندارد (نمی‌تواند
     * داشته باشد) — یک نگاشت دستی ساده به‌جای رابطه‌ی Eloquent واقعی، چون
     * formable_type اینجا نام دلخواه (process_definition/process_step) است،
     * نه FQCN کلاس مثل morph استاندارد.
     */
    public function resolveFormable(): ProcessDefinition|ProcessStep|null
    {
        return match ($this->formable_type) {
            self::FORMABLE_DEFINITION => ProcessDefinition::withoutGlobalScopes()->find($this->formable_id),
            self::FORMABLE_STEP => ProcessStep::find($this->formable_id),
            default => null,
        };
    }
}
