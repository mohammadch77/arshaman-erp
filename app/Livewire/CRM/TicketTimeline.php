<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Ticket;
use Livewire\Component;

/**
 * فهرست تیکت‌های یک مخاطب، در صفحه پروفایل ۳۶۰ (ContactProfile) جاسازی
 * می‌شود. مثل InteractionTimeline هلدینگ‌محور است: تیکت‌های همه پروفایل‌های
 * سایتِ این مخاطب را کنار هم نشان می‌دهد — withoutGlobalScopes() چون تیکت‌های
 * چند شرکت هم‌زمان لازم است.
 */
class TicketTimeline extends Component
{
    public string $contactId;

    public function mount(string $contactId): void
    {
        $this->authorize('view', Contact::class);

        $this->contactId = $contactId;
    }

    public function getSiteProfileIdsProperty()
    {
        return ContactSiteProfile::withoutGlobalScopes()
            ->where('contact_id', $this->contactId)
            ->pluck('id');
    }

    public function getTicketsProperty()
    {
        return Ticket::withoutGlobalScopes()
            ->whereIn('contact_site_profile_id', $this->siteProfileIds)
            ->with('company')
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.crm.ticket-timeline', [
            'tickets' => $this->tickets,
        ]);
    }
}
