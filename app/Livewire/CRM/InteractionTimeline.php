<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Actions\RecordInteraction;
use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Interaction;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * تایم‌لاین تعاملات یک مخاطب، در صفحه پروفایل ۳۶۰ (ContactProfile) جاسازی
 * می‌شود. مثل ContactProfile خودش، هلدینگ‌محور است: تعاملات همه پروفایل‌های
 * سایتِ این مخاطب را کنار هم، مرتب‌شده بر اساس زمان، نشان می‌دهد —
 * withoutGlobalScopes() چون تعاملات چند شرکت هم‌زمان لازم است، دقیقاً همان
 * دلیلی که ContactProfile::getSiteProfilesProperty() دارد.
 */
class InteractionTimeline extends Component
{
    public string $contactId;

    public string $contact_site_profile_id = '';

    public string $interaction_type = Interaction::TYPE_CALL;

    public string $notes = '';

    public function mount(string $contactId): void
    {
        $this->authorize('view', Contact::class);

        $this->contactId = $contactId;
    }

    public function getSiteProfilesProperty()
    {
        return ContactSiteProfile::withoutGlobalScopes()
            ->where('contact_id', $this->contactId)
            ->with('company')
            ->get();
    }

    public function getSiteProfileOptionsProperty()
    {
        return $this->siteProfiles->map(fn (ContactSiteProfile $profile) => [
            'id' => $profile->id,
            'label' => $profile->company->name.($profile->site_full_name ? " ({$profile->site_full_name})" : ''),
        ]);
    }

    public function getInteractionsProperty()
    {
        return Interaction::withoutGlobalScopes()
            ->whereIn('contact_site_profile_id', $this->siteProfiles->pluck('id'))
            ->with(['company', 'contactSiteProfile', 'createdBy'])
            ->orderByDesc('occurred_at')
            ->get();
    }

    public function record(RecordInteraction $action): void
    {
        $this->validate([
            'contact_site_profile_id' => ['required', Rule::in($this->siteProfiles->pluck('id'))],
            'interaction_type' => ['required', Rule::in(Interaction::MANUAL_TYPES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = $this->siteProfiles->firstWhere('id', $this->contact_site_profile_id);

        $action->handle($profile, [
            'interaction_type' => $this->interaction_type,
            'notes' => $this->notes ?: null,
            'occurred_at' => now(),
        ], auth()->user());

        $this->reset(['notes']);
        $this->interaction_type = Interaction::TYPE_CALL;

        $this->dispatch('interaction-recorded');
    }

    public function render()
    {
        return view('livewire.crm.interaction-timeline', [
            'siteProfiles' => $this->siteProfiles,
            'siteProfileOptions' => $this->siteProfileOptions,
            'interactions' => $this->interactions,
        ]);
    }
}
