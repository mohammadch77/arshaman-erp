<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Actions\ChangeTicketStatus;
use App\Modules\CRM\Actions\ReplyToTicket;
use App\Modules\CRM\Enums\TicketStatus;
use App\Modules\CRM\Models\Ticket;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * صفحه جزئیات تیکت + تایم‌لاین پاسخ‌ها + تغییر وضعیت. withoutGlobalScopes
 * روی mount چون از نمای ۳۶۰ مخاطب هم قابل‌دسترس است (شرکت تیکت لزوماً شرکت
 * فعال سوییچر نیست) — دسترسی واقعی از TicketPolicy::view می‌آید، نه
 * global scope.
 */
class TicketShow extends Component
{
    use Toast;

    public Ticket $ticket;

    public string $newStatus = '';

    public string $message = '';

    public function mount(string $ticketId): void
    {
        $this->ticket = Ticket::withoutGlobalScopes()
            ->with(['contactSiteProfile.contact', 'assignedTo', 'createdBy', 'replies.user'])
            ->findOrFail($ticketId);

        $this->authorize('view', $this->ticket);

        $this->newStatus = $this->ticket->status;
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(TicketStatus::cases())->map(fn ($case) => [
            'id' => $case->value,
            'name' => $case->label(),
        ])->all();
    }

    public function reply(ReplyToTicket $action): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $action->handle($this->ticket, $this->message, auth()->user());

        $this->reset('message');
        $this->ticket->load('replies.user');

        $this->success('پاسخ ثبت شد.');
    }

    public function changeStatus(ChangeTicketStatus $action): void
    {
        $this->validate([
            'newStatus' => ['required', Rule::enum(TicketStatus::class)],
        ]);

        $this->ticket = $action->handle($this->ticket, $this->newStatus, auth()->user());

        $this->success('وضعیت تیکت به‌روزرسانی شد.');
    }

    public function render()
    {
        return view('livewire.crm.ticket-show', [
            'statusOptions' => $this->statusOptions,
        ]);
    }
}
