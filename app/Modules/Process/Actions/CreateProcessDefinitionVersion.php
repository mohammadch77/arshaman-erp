<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessGraphValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * نسخه‌ی جدید یک تعریف فرایند (بخش ۴.۲ Session جاری) — وقتی تعریف فقط
 * instance تمام‌شده دارد (بدون هیچ در‌جریان)، ویرایش ساختاری دیگر رکورد
 * موجود را رونویسی نمی‌کند: یک process_definitions تازه با همان process_key
 * و version+1 ساخته می‌شود (کلون کامل مراحل/گذارها با مقادیر تازه‌ی فرم)،
 * نسخه‌ی قدیمی is_current_version=false می‌شود. تاریخچه‌ی نسخه‌ی قدیمی
 * (instance/log هایش) کاملاً دست‌نخورده می‌ماند — همچنان به همان process_steps
 * قدیمی اشاره دارد. از دید UI فقط «یک فرایند با نام ثابت» دیده می‌شود چون
 * فهرست/لوکاپ‌ها همیشه is_current_version=true را می‌خوانند.
 */
class CreateProcessDefinitionVersion
{
    public function __construct(private readonly ProcessGraphValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data  خروجی ProcessDefinitionForm::extractPayload() —
     *                                       process_key در $data نادیده گرفته می‌شود، همیشه
     *                                       از روی نسخه‌ی قدیمی به ارث می‌رسد.
     */
    public function handle(User $actor, ProcessDefinition $previousVersion, array $data): ProcessDefinition
    {
        Gate::forUser($actor)->authorize('update', $previousVersion);

        $this->validator->validate($data['subject_type'], $data['steps'], $data['transitions'], $data['request_form_fields'] ?? []);

        return DB::transaction(function () use ($actor, $previousVersion, $data) {
            $previousVersion->update(['is_current_version' => false]);

            $newVersion = ProcessDefinition::create([
                'owner_company_id' => $previousVersion->owner_company_id,
                'name' => $data['name'],
                'process_key' => $previousVersion->process_key,
                'version' => $previousVersion->version + 1,
                'is_current_version' => true,
                'subject_type' => $data['subject_type'],
                'request_form_fields' => $data['subject_type'] === null ? $data['request_form_fields'] : null,
                'is_active' => $data['is_active'],
                'created_by_user_id' => $actor->id,
            ]);

            $stepIdsByKey = [];

            foreach ($data['steps'] as $order => $step) {
                $created = ProcessStep::create([
                    'process_definition_id' => $newVersion->id,
                    'step_key' => $step['step_key'],
                    'name' => $step['name'],
                    'step_type' => $step['step_type'],
                    'assignment_type' => $step['assignment_type'] ?? null,
                    'assigned_role' => $step['assigned_role'] ?? null,
                    'assigned_user_id' => $step['assigned_user_id'] ?? null,
                    'condition_field' => $step['condition_field'] ?? null,
                    'condition_operator' => $step['condition_operator'] ?? null,
                    'condition_value' => $step['condition_value'] ?? null,
                    'step_form_fields' => $step['step_form_fields'] ?? null,
                    'display_order' => $order,
                ]);

                $stepIdsByKey[$step['step_key']] = $created->id;
            }

            foreach ($data['transitions'] as $order => $transition) {
                ProcessTransition::create([
                    'from_step_id' => $stepIdsByKey[$transition['from_step_key']],
                    'to_step_id' => $stepIdsByKey[$transition['to_step_key']],
                    'on_result' => $transition['on_result'],
                    'display_order' => $order,
                ]);
            }

            return $newVersion->fresh(['steps.outgoingTransitions']);
        });
    }
}
