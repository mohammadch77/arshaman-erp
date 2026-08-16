<?php

namespace App\Livewire\Process;

use App\Modules\Process\Actions\ToggleProcessDefinitionActive;
use App\Modules\Process\Models\ProcessDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Mary\Traits\Toast;

class ProcessDefinitionIndex extends Component
{
    use Toast;

    public function mount(): void
    {
        $this->authorize('create', ProcessDefinition::class);
    }

    public function toggleActive(string $definitionId, ToggleProcessDefinitionActive $action): void
    {
        $definition = ProcessDefinition::findOrFail($definitionId);

        try {
            $action->handle(auth()->user(), $definition);
        } catch (AuthorizationException) {
            $this->error('اجازه‌ی تغییر وضعیت این فرایند را ندارید.');

            return;
        }

        $this->success($definition->fresh()->is_active ? 'فرایند فعال شد.' : 'فرایند غیرفعال شد.');
    }

    public function render()
    {
        $definitions = ProcessDefinition::query()
            ->withCount(['instances as active_instances_count' => fn ($query) => $query->where('status', 'in_progress')])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.process.process-definition-index', [
            'definitions' => $definitions,
        ]);
    }
}
