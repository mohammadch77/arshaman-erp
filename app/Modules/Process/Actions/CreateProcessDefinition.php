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
 * تنها راه ساخت یک تعریف فرایند کامل (definition + steps + transitions) —
 * همیشه در یک تراکنش دیتابیسی واحد، هرگز چند insert جدا که می‌تواند
 * نیمه‌کاره بماند. authorize داخل خودِ Action (بند ۹ CLAUDE.md)، مستقل از
 * این‌که کامپوننت Livewire قبلش authorize زده یا نه.
 */
class CreateProcessDefinition
{
    public function __construct(private readonly ProcessGraphValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data  خروجی ProcessDefinitionForm::extractPayload()
     */
    public function handle(User $actor, string $companyId, array $data): ProcessDefinition
    {
        Gate::forUser($actor)->authorize('create', [ProcessDefinition::class, $companyId]);

        $this->validator->validate($data['subject_type'], $data['steps'], $data['transitions']);

        return DB::transaction(function () use ($actor, $companyId, $data) {
            $definition = ProcessDefinition::create([
                'owner_company_id' => $companyId,
                'name' => $data['name'],
                'process_key' => $data['process_key'],
                'subject_type' => $data['subject_type'],
                'request_form_fields' => $data['subject_type'] === null ? $data['request_form_fields'] : null,
                'is_active' => $data['is_active'],
                'created_by_user_id' => $actor->id,
            ]);

            $stepIdsByKey = [];

            foreach ($data['steps'] as $step) {
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
                ]);

                $stepIdsByKey[$step['step_key']] = $created->id;
            }

            foreach ($data['transitions'] as $transition) {
                ProcessTransition::create([
                    'from_step_id' => $stepIdsByKey[$transition['from_step_key']],
                    'to_step_id' => $stepIdsByKey[$transition['to_step_key']],
                    'on_result' => $transition['on_result'],
                ]);
            }

            return $definition->fresh(['steps.outgoingTransitions']);
        });
    }
}
