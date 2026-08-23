<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Models\Contact;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\ContactSubmission;
use App\Modules\CRM\Models\Interaction;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\RfmSegment;
use App\Modules\CRM\Policies\ContactPolicy;
use App\Modules\CRM\Policies\ContactSiteProfilePolicy;
use App\Modules\CRM\Policies\ContactSubmissionPolicy;
use App\Modules\CRM\Policies\InteractionPolicy;
use App\Modules\CRM\Policies\LeadPolicy;
use App\Modules\CRM\Policies\RfmSegmentPolicy;
use App\Modules\CRM\Policies\TicketPolicy;
use App\Modules\CRM\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ContactSiteProfile::class, ContactSiteProfilePolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Interaction::class, InteractionPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(RfmSegment::class, RfmSegmentPolicy::class);
        Gate::policy(ContactSubmission::class, ContactSubmissionPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
    }
}
