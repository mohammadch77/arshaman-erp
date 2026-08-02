<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Models\Contact;
use Livewire\Component;

/**
 * نمای ۳۶۰ مخاطب — پروفایل هلدینگی + پروفایل هر سایت، کنار هم.
 * عمداً فقط نام/موبایل/ایمیل/جمع‌مبلغ هر سایت نشان می‌دهد، نه جزئیات سفارش
 * (طبق ملاحظه حریم خصوصی سند CRM). دسترسی با ContactPolicy کنترل می‌شود که
 * محدودتر از ContactSiteProfilePolicy است — فقط نقش‌های سطح هلدینگ.
 */
class ContactProfile extends Component
{
    public Contact $contact;

    public function mount(string $contactId): void
    {
        $this->authorize('view', Contact::class);

        $this->contact = Contact::findOrFail($contactId);
    }

    public function getSiteProfilesProperty()
    {
        return $this->contact->siteProfiles()
            ->withoutGlobalScopes()
            ->with('company')
            ->get();
    }

    public function render()
    {
        return view('livewire.crm.contact-profile', [
            'siteProfiles' => $this->siteProfiles,
        ]);
    }
}
