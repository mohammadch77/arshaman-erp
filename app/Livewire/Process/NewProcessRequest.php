<?php

namespace App\Livewire\Process;

use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Services\ProcessEngine;
use App\Modules\Process\Support\FormFieldValidator;
use App\Modules\Process\Support\ProcessFileUploader;
use Illuminate\Support\Collection;
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
     * @return Collection<int, ProcessDefinition>
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

    /**
     * @return Collection<int, ProcessFormField>
     */
    public function getSelectedDefinitionFieldsProperty()
    {
        return $this->selectedDefinition?->formFields ?? collect();
    }

    public function selectDefinition(string $definitionId): void
    {
        $this->selectedDefinitionId = $definitionId;
        $this->formValues = [];
        $this->fileUploads = [];

        foreach ($this->selectedDefinitionFields as $field) {
            $this->formValues[$field->field_key] = $field->field_type === 'boolean' ? false : null;
            if ($field->field_type === 'file') {
                $this->fileUploads[$field->field_key] = null;
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

        $fields = $this->selectedDefinitionFields;

        // فیلد نوع file جدا از formValues اعتبارسنجی می‌شود (روی خودِ فایل
        // آپلودی، نه رشته‌ی مسیر که هنوز ساخته نشده) — نگاه کن ProcessFileUploader::store.
        $fileRules = [];
        $rules = FormFieldValidator::rules($fields->filter(fn ($field) => $field->field_type !== 'file'));
        $otherRules = [];
        foreach ($rules as $key => $rule) {
            $otherRules['formValues.'.$key] = $rule;
        }

        foreach ($fields->filter(fn ($field) => $field->field_type === 'file') as $field) {
            $fileRules['fileUploads.'.$field->field_key] = $field->is_required ? ['required'] : ['nullable'];
        }

        Validator::make(['formValues' => $this->formValues], $otherRules)->validate();
        Validator::make(['fileUploads' => $this->fileUploads], $fileRules)->validate();

        foreach ($fields->where('field_type', 'file') as $field) {
            $file = $this->fileUploads[$field->field_key] ?? null;

            if ($file instanceof TemporaryUploadedFile) {
                $this->formValues[$field->field_key] = ProcessFileUploader::store($file);
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
