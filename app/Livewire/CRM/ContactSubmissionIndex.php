<?php

namespace App\Livewire\CRM;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Actions\LogContactAttempt;
use App\Modules\CRM\Actions\UpdateContactSubmissionStatus;
use App\Modules\CRM\Enums\ContactAttemptOutcome;
use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\CRM\Models\ContactSubmission;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ContactSubmissionIndex extends Component
{
    use Toast, WithPagination;

    public string $filterStatus = '';

    public bool $showHistoryModal = false;

    public ?string $historySubmissionId = null;

    public bool $showAttemptModal = false;

    public ?string $attemptSubmissionId = null;

    public string $attemptOutcome = '';

    public string $attemptNote = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ContactSubmission::class);
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function getStatusOptionsProperty(): array
    {
        return collect([['id' => '', 'name' => 'همه وضعیت‌ها']])
            ->concat(collect(ContactSubmissionStatus::cases())->map(fn ($case) => [
                'id' => $case->value,
                'name' => $case->label(),
            ]))
            ->all();
    }

    public function getOutcomeOptionsProperty(): array
    {
        return collect(ContactAttemptOutcome::cases())->map(fn ($case) => [
            'id' => $case->value,
            'name' => $case->label(),
        ])->all();
    }

    public function markStatus(string $submissionId, string $status, UpdateContactSubmissionStatus $action): void
    {
        $submission = ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->findOrFail($submissionId);

        $action->handle($submission, ContactSubmissionStatus::from($status), auth()->user());

        $this->success('وضعیت پیام به‌روزرسانی شد.');
    }

    public function openAttemptModal(string $submissionId): void
    {
        $submission = ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->findOrFail($submissionId);

        $this->authorize('update', $submission);

        $this->attemptSubmissionId = $submissionId;
        $this->attemptOutcome = '';
        $this->attemptNote = '';
        $this->resetErrorBag();
        $this->showAttemptModal = true;
    }

    protected function attemptRules(): array
    {
        return [
            'attemptOutcome' => ['required', Rule::enum(ContactAttemptOutcome::class)],
            'attemptNote' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function attemptMessages(): array
    {
        return [
            'attemptOutcome.required' => 'نتیجه تماس را انتخاب کنید.',
        ];
    }

    public function logAttempt(LogContactAttempt $action): void
    {
        $this->validate($this->attemptRules(), $this->attemptMessages());

        $submission = ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->findOrFail($this->attemptSubmissionId);

        $action->handle(
            $submission,
            ContactAttemptOutcome::from($this->attemptOutcome),
            $this->attemptNote ?: null,
            auth()->user()
        );

        $this->showAttemptModal = false;
        $this->success('نتیجه تماس ثبت شد.');
    }

    public function getSubmissionsProperty()
    {
        return ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
            ->latest()
            ->paginate(10);
    }

    /**
     * دسترسی دیدن تاریخچه هم‌سطح دیدن خودِ پیام است (ContactSubmissionPolicy::view) —
     * بند صریح کارفرما، نه یک Policy جدا.
     */
    public function openHistory(string $submissionId): void
    {
        $submission = ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->findOrFail($submissionId);

        $this->authorize('view', $submission);

        $this->historySubmissionId = $submissionId;
        $this->showHistoryModal = true;
    }

    /**
     * تاریخچه ترکیبی دو منبع — تغییرات status از activity_log (خودکار روی
     * ContactSubmission) و تلاش‌های تماس از contact_submission_attempts —
     * به یک ترتیب زمانی واحد. هر عنصر یک آرایه با کلید 'type' است تا view
     * بداند کدام بخش را رندر کند.
     */
    public function getHistoryProperty()
    {
        if ($this->historySubmissionId === null) {
            return collect();
        }

        $submission = ContactSubmission::where('owner_company_id', app(CompanyContext::class)->id())
            ->findOrFail($this->historySubmissionId);

        $statusChanges = $submission->activities()->with('causer')->get()->map(fn ($activity) => [
            'type' => 'status_change',
            'at' => $activity->created_at,
            'user' => $activity->causer,
            'from' => $activity->properties['old']['status'] ?? null,
            'to' => $activity->properties['attributes']['status'] ?? null,
        ]);

        $attempts = $submission->attempts()->with('attemptedBy')->get()->map(fn ($attempt) => [
            'type' => 'attempt',
            'at' => $attempt->attempted_at,
            'user' => $attempt->attemptedBy,
            'outcome' => $attempt->outcome,
            'note' => $attempt->note,
        ]);

        return $statusChanges->concat($attempts)->sortByDesc('at')->values();
    }

    public function render()
    {
        return view('livewire.crm.contact-submission-index', [
            'submissions' => $this->submissions,
            'history' => $this->showHistoryModal ? $this->history : collect(),
        ]);
    }
}
