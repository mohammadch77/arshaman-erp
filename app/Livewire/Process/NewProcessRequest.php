<?php

namespace App\Livewire\Process;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * درخواست جدید — فقط فرایندهای «آزاد» (بدون subject_type) اینجا قابل‌شروع‌اند؛
 * فرایندهای وصل‌به‌ماژول (مثل مرخصی) باید از فرم اصلی همان ماژول شروع شوند
 * (نگاه کن ProcessEngine::startForSubjectIfActive، RequestLeave در HR).
 */
class NewProcessRequest extends Component
{
    use Toast;

    public ?string $selectedDefinitionId = null;

    /** @var array<string, mixed> */
    public array $formValues = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ProcessDefinition::class);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProcessDefinition>
     */
    public function getDefinitionsProperty()
    {
        return ProcessDefinition::query()
            ->whereNull('subject_type')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getSelectedDefinitionProperty(): ?ProcessDefinition
    {
        if ($this->selectedDefinitionId === null) {
            return null;
        }

        return $this->definitions->firstWhere('id', $this->selectedDefinitionId);
    }

    public function selectDefinition(string $definitionId): void
    {
        $this->selectedDefinitionId = $definitionId;
        $this->formValues = [];

        foreach ($this->selectedDefinition?->request_form_fields ?? [] as $field) {
            $this->formValues[$field['key']] = $field['type'] === 'boolean' ? false : null;
        }
    }

    public function submit(ProcessEngine $engine): void
    {
        $definition = $this->selectedDefinition;

        if ($definition === null) {
            $this->error('یک فرایند را انتخاب کنید.');

            return;
        }

        $fields = $definition->request_form_fields ?? [];

        $rules = [];
        foreach ($fields as $field) {
            $rules['formValues.'.$field['key']] = match ($field['type']) {
                'number' => ['required', 'numeric'],
                'boolean' => ['boolean'],
                default => ['required', 'string', 'max:2000'],
            };
        }

        Validator::make(['formValues' => $this->formValues], $rules)->validate();

        $engine->startInstance($definition, auth()->user(), null, $this->formValues);

        $this->success('درخواست شما ثبت و ارسال شد.', redirectTo: route('processes.my-requests'));
    }

    public function render()
    {
        return view('livewire.process.new-process-request');
    }
}
