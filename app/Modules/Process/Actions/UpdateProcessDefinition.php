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
 * ویرایش یک تعریف فرایند موجود — فقط دو حالت را مدیریت می‌کند:
 * ۱. بدون هیچ instance: ویرایش کامل ساختار در همان رکورد (امن، چون هیچ
 *    FK ای هنوز به مراحل قدیمی این تعریف اشاره نمی‌کند).
 * ۲. حداقل یک instance «در جریان»: قفل کامل ساختار — فقط name/is_active.
 *
 * حالت سوم (فقط instance تمام‌شده، بدون هیچ در‌جریان) دیگر اینجا مدیریت
 * نمی‌شود — بخش ۴.۲ Session جاری این حالت را به یک نسخه‌ی جدید
 * (CreateProcessDefinitionVersion) منتقل کرد، چون رونویسی ساختار زیر پای
 * یک instance تمام‌شده (حتی سال‌ها پیش) تاریخچه‌ی واقعی را عوض می‌کند.
 *
 * تصمیم طراحی — چرا حالت «در جریان» قفل می‌شود: process_instances.current_step_id
 * و process_instance_logs.step_id هر دو FK با RESTRICT (بدون CASCADE) به
 * process_steps دارند — یعنی حذف مراحل قدیمی برای بازسازی ساختار، وقتی یک
 * instance واقعاً هنوز به آن‌ها اشاره دارد، در سطح دیتابیس با QueryException
 * شکست می‌خورد. is_active مستقل قابل‌تغییر می‌ماند چون فقط جلوی instance های
 * *تازه* را می‌گیرد، به سابقه دست نمی‌زند.
 */
class UpdateProcessDefinition
{
    public function __construct(private readonly ProcessGraphValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data  خروجی ProcessDefinitionForm::extractPayload()
     */
    public function handle(User $actor, ProcessDefinition $definition, array $data): ProcessDefinition
    {
        Gate::forUser($actor)->authorize('update', $definition);

        $hasActiveInstances = $definition->instances()
            ->withoutGlobalScope('owner_company')
            ->where('status', 'in_progress')
            ->exists();

        if ($hasActiveInstances) {
            $definition->update([
                'name' => $data['name'],
                'is_active' => $data['is_active'],
            ]);

            return $definition->fresh(['steps.outgoingTransitions']);
        }

        $this->validator->validate($data['subject_type'], $data['steps'], $data['transitions'], $data['request_form_fields'] ?? []);

        return DB::transaction(function () use ($definition, $data) {
            $definition->update([
                'name' => $data['name'],
                'process_key' => $data['process_key'],
                'subject_type' => $data['subject_type'],
                'request_form_fields' => $data['subject_type'] === null ? $data['request_form_fields'] : null,
                'is_active' => $data['is_active'],
            ]);

            // بدون سابقه (چک بالا)، پس هیچ FK دیگری به مراحل قدیمی اشاره
            // نمی‌کند — حذف کامل و بازسازی امن است.
            ProcessTransition::whereIn('from_step_id', $definition->steps()->pluck('id'))->delete();
            $definition->steps()->delete();

            $stepIdsByKey = [];

            foreach ($data['steps'] as $order => $step) {
                $created = ProcessStep::create([
                    'process_definition_id' => $definition->id,
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

            return $definition->fresh(['steps.outgoingTransitions']);
        });
    }
}
