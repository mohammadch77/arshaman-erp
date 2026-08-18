<?php

namespace App\Livewire\Process;

use App\Modules\Process\Actions\DeleteProcessDefinition;
use App\Modules\Process\Actions\ToggleProcessDefinitionActive;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Services\ProcessFlowchartBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Mary\Traits\Toast;

class ProcessDefinitionIndex extends Component
{
    use Toast;

    public ?string $flowchartDefinitionId = null;

    public bool $showFlowchartModal = false;

    public function mount(): void
    {
        $this->authorize('create', ProcessDefinition::class);
    }

    /**
     * بخش ۴.۱ Session جاری — دکمه‌ی «مشاهده فلوچارت». رشته‌ی مرمید را در یک
     * رویداد مرورگر dispatch می‌کند تا Alpine (resources/js/process-flowchart.js)
     * همان لحظه رندرش کند — مستقل از این‌که Livewire دوباره کل مودال را morph
     * کند یا نه.
     */
    public function showFlowchart(string $definitionId, ProcessFlowchartBuilder $builder): void
    {
        $definition = ProcessDefinition::findOrFail($definitionId);

        $this->authorize('view', $definition);

        $this->flowchartDefinitionId = $definitionId;
        $this->showFlowchartModal = true;

        $this->dispatch('process-flowchart-ready', mermaid: $builder->build($definition));
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

    public function delete(string $definitionId, DeleteProcessDefinition $action): void
    {
        $definition = ProcessDefinition::findOrFail($definitionId);

        try {
            $hardDeleted = $action->handle(auth()->user(), $definition);
        } catch (AuthorizationException) {
            $this->error('اجازه‌ی حذف این فرایند را ندارید.');

            return;
        }

        $this->success($hardDeleted ? 'فرایند برای همیشه حذف شد.' : 'فرایند بایگانی شد — داده‌ی تاریخی محفوظ ماند.');
    }

    public function render()
    {
        $definitions = ProcessDefinition::query()
            // فقط نسخه‌ی جاری هر خانواده (بخش ۴.۲ Session جاری) — نسخه‌های
            // قدیمی‌تر همچنان در دیتابیس/تاریخچه می‌مانند ولی از فهرست فعال
            // مخفی‌اند؛ از دید UI فقط «یک فرایند با نام ثابت» دیده می‌شود.
            ->where('is_current_version', true)
            ->withCount([
                'instances as active_instances_count' => fn ($query) => $query->where('status', 'in_progress'),
                'instances as instances_count',
            ])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.process.process-definition-index', [
            'definitions' => $definitions,
        ]);
    }
}
