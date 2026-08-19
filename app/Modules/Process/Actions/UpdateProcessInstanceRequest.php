<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Services\ProcessFormFieldResolver;
use App\Modules\Process\Support\FormFieldValidator;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * فرستنده‌ی اصلی یک فرایند آزاد (بدون subject_type)، قبل از این‌که مرحله‌ی
 * فعلی هیچ اقدامی داشته باشد، می‌تواند مقادیر فرم درخواست را ویرایش کند.
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است — تنها منبع حقیقت
 * ProcessInstancePolicy::updateRequest.
 */
class UpdateProcessInstanceRequest
{
    public function __construct(private readonly ProcessFormFieldResolver $formFieldResolver) {}

    /**
     * @param  array<string, mixed>  $requestData
     */
    public function handle(User $actor, ProcessInstance $instance, array $requestData): ProcessInstance
    {
        Gate::forUser($actor)->authorize('updateRequest', $instance);

        // withoutGlobalScopes() عمداً: همان دلیل ProcessEngine::resolveSubject —
        // این Action ممکن است بدون یک CompanyContext فعال صدا زده شود.
        $definition = ProcessDefinition::withoutGlobalScopes()->find($instance->process_definition_id);
        $fields = $definition !== null
            ? ProcessFormField::query()
                ->where('formable_type', ProcessFormField::FORMABLE_DEFINITION)
                ->where('formable_id', $definition->id)
                ->orderBy('display_order')
                ->get()
            : collect();

        if ($fields->isEmpty()) {
            throw new RuntimeException('این فرایند فیلد درخواستی برای ویرایش ندارد.');
        }

        $validated = FormFieldValidator::validate($fields, $requestData);

        $this->formFieldResolver->storeForInstance($instance, ProcessFormField::FORMABLE_DEFINITION, $definition->id, $validated);

        ProcessInstanceLog::create([
            'owner_company_id' => $instance->owner_company_id,
            'process_instance_id' => $instance->id,
            'step_id' => $instance->current_step_id,
            'actor_user_id' => $actor->id,
            'action' => LogAction::RequestUpdated,
            'comment' => null,
        ]);

        return $instance->fresh();
    }
}
