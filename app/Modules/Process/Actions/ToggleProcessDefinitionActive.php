<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Models\ProcessDefinition;
use Illuminate\Support\Facades\Gate;

/**
 * فعال/غیرفعال‌کردن مستقل یک تعریف فرایند — همیشه مجاز، حتی وقتی تعریف
 * سابقه‌ی instance دارد (بند UpdateProcessDefinition)، چون این کار فقط جلوی
 * ساخت instance *تازه* را می‌گیرد (ProcessEngine::startForSubjectIfActive)،
 * هیچ سطری از process_steps/process_transitions را دست نمی‌زند.
 */
class ToggleProcessDefinitionActive
{
    public function handle(User $actor, ProcessDefinition $definition): ProcessDefinition
    {
        Gate::forUser($actor)->authorize('toggleActive', $definition);

        $definition->update(['is_active' => ! $definition->is_active]);

        return $definition->fresh();
    }
}
