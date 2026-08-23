<?php

namespace App\Livewire\CRM;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Actions\CreateTicket;
use App\Modules\CRM\Enums\TicketPriority;
use App\Modules\CRM\Enums\TicketStatus;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Ticket;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * فهرست تیکت‌های شرکت فعال سوییچر (نه هلدینگ‌محور، مثل LeadBoard) + فرم
 * ایجاد. فیلتر وضعیت/اولویت.
 */
class TicketIndex extends Component
{
    use Toast, WithPagination;

    public string $filterStatus = '';

    public string $filterPriority = '';

    public bool $showCreateForm = false;

    public string $contact_site_profile_id = '';

    public string $subject = '';

    public string $description = '';

    public string $priority = TicketPriority::Normal->value;

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetPage();
    }

    public function getStatusOptionsProperty(): array
    {
        return collect([['id' => '', 'name' => 'همه وضعیت‌ها']])
            ->concat(collect(TicketStatus::cases())->map(fn ($case) => [
                'id' => $case->value,
                'name' => $case->label(),
            ]))
            ->all();
    }

    public function getPriorityOptionsProperty(): array
    {
        return collect([['id' => '', 'name' => 'همه اولویت‌ها']])
            ->concat(collect(TicketPriority::cases())->map(fn ($case) => [
                'id' => $case->value,
                'name' => $case->label(),
            ]))
            ->all();
    }

    public function getCreatePriorityOptionsProperty(): array
    {
        return collect(TicketPriority::cases())->map(fn ($case) => [
            'id' => $case->value,
            'name' => $case->label(),
        ])->all();
    }

    public function getSiteProfileOptionsProperty()
    {
        return ContactSiteProfile::with('contact')
            ->get()
            ->map(fn (ContactSiteProfile $profile) => [
                'id' => $profile->id,
                'label' => $profile->contact->full_name,
            ]);
    }

    public function getTicketsProperty()
    {
        return Ticket::with(['contactSiteProfile.contact', 'assignedTo'])
            ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($query) => $query->where('priority', $this->filterPriority))
            ->latest()
            ->paginate(10);
    }

    public function create(CreateTicket $action): void
    {
        $this->validate([
            'contact_site_profile_id' => ['required', Rule::in($this->siteProfileOptions->pluck('id'))],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ]);

        $action->handle([
            'owner_company_id' => app(CompanyContext::class)->id(),
            'contact_site_profile_id' => $this->contact_site_profile_id,
            'subject' => $this->subject,
            'description' => $this->description ?: null,
            'priority' => $this->priority,
        ], auth()->user());

        $this->reset(['contact_site_profile_id', 'subject', 'description', 'showCreateForm']);
        $this->priority = TicketPriority::Normal->value;

        $this->success('تیکت با موفقیت ثبت شد.');
    }

    public function render()
    {
        return view('livewire.crm.ticket-index', [
            'tickets' => $this->tickets,
            'statusOptions' => $this->statusOptions,
            'priorityOptions' => $this->priorityOptions,
            'createPriorityOptions' => $this->createPriorityOptions,
            'siteProfileOptions' => $this->siteProfileOptions,
        ]);
    }
}
