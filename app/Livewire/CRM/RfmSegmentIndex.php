<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Actions\CalculateRfmSegment;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\RfmSegment;
use Livewire\Component;

/**
 * فهرست مخاطبین شرکت فعال به تفکیک segment RFM — مثل LeadBoard شرکت‌محور
 * است (نه هلدینگ‌محور مثل ContactProfile)، چون rfm_segments هم
 * owner_company_id مستقل دارد و بخش‌بندی برای هر شرکت جدا معنا دارد.
 */
class RfmSegmentIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', RfmSegment::class);
    }

    public function getGroupedProfilesProperty()
    {
        $profiles = ContactSiteProfile::with(['contact', 'rfmSegment'])->get();

        return $profiles->groupBy(fn (ContactSiteProfile $profile) => $profile->rfmSegment?->segment ?? RfmSegment::SEGMENT_NEW);
    }

    public function recalculate(CalculateRfmSegment $action, string $profileId): void
    {
        $profile = ContactSiteProfile::findOrFail($profileId);

        $action->handle($profile, auth()->user());
    }

    public function render()
    {
        return view('livewire.crm.rfm-segment-index', [
            'segments' => RfmSegment::SEGMENTS,
            'groupedProfiles' => $this->groupedProfiles,
        ]);
    }
}
