<?php

namespace App\Livewire\Process;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Support\ProcessFileUploader;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * درخواست جدید — فقط فرایندهای «آزاد» (بدون subject_type) اینجا قابل‌شروع‌اند؛
 * فرایندهای وصل‌به‌ماژول (مثل مرخصی) باید از فرم اصلی همان ماژول شروع شوند
 * (نگاه کن ProcessEngine::startForSubjectIfActive، RequestLeave در HR).
 */
class NewProcessRequest extends Component
{
    use Toast, WithFileUploads;

    public ?string $selectedDefinitionId = null;

    /** @var array<string, mixed> */
    public array $formValues = [];

    /**
     * فقط فیلدهای نوع file — کلید = field key، مقدار = TemporaryUploadedFile
     * تا انتخاب شود؛ در submit() به مسیر ذخیره‌شده تبدیل و در formValues
     * جایگزین می‌شود (دقیقاً الگوی imageUploads ماژول SiteBuilder).
     *
     * @var array<string, mixed>
     */
    public array $fileUploads = [];

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
            ->where('is_current_version', true)
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
        $this->fileUploads = [];

        foreach ($this->selectedDefinition?->request_form_fields ?? [] as $field) {
            $this->formValues[$field['key']] = $field['type'] === 'boolean' ? false : null;
            if ($field['type'] === 'file') {
                $this->fileUploads[$field['key']] = null;
            }
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

        // فیلد نوع file جدا از formValues اعتبارسنجی می‌شود (روی خودِ فایل
        // آپلودی، نه رشته‌ی مسیر که هنوز ساخته نشده) — نگاه کن ProcessFileUploader::store.
        $fileRules = [];
        $otherRules = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'file') {
                $fileRules['fileUploads.'.$field['key']] = ['required'];

                continue;
            }

            $otherRules['formValues.'.$field['key']] = match ($field['type']) {
                'number' => ['required', 'numeric'],
                'boolean' => ['boolean'],
                default => ['required', 'string', 'max:2000'],
            };
        }

        Validator::make(['formValues' => $this->formValues], $otherRules)->validate();
        Validator::make(['fileUploads' => $this->fileUploads], $fileRules)->validate();

        foreach ($fields as $field) {
            if ($field['type'] !== 'file') {
                continue;
            }

            $file = $this->fileUploads[$field['key']] ?? null;

            if ($file instanceof TemporaryUploadedFile) {
                $this->formValues[$field['key']] = ProcessFileUploader::store($file);
            }
        }

        $engine->startInstance($definition, auth()->user(), null, $this->formValues);

        $this->success('درخواست شما ثبت و ارسال شد.', redirectTo: route('processes.my-requests'));
    }

    public function render()
    {
        return view('livewire.process.new-process-request');
    }
}
