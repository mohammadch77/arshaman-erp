<?php

namespace App\Livewire\CRM;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Actions\AssignLead;
use App\Modules\CRM\Actions\CreateLead;
use App\Modules\CRM\Actions\UpdateLeadStage;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Lead;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * نمای قیف فروش شرکت فعال — یک ستون به‌ازای هر Lead::PIPELINE_STAGES. برخلاف
 * ContactProfile/InteractionTimeline که هلدینگ‌محورند، این پنل مثل PayrollIndex
 * فقط شرکت فعال سوییچر را می‌بیند (بدون withoutGlobalScopes) چون هدف مدیریت
 * قیف همان شرکت است، نه نمای تجمیعی.
 */
class LeadBoard extends Component
{
    public bool $showCreateForm = false;

    public string $contact_site_profile_id = '';

    public string $source = Lead::SOURCE_WEBSITE;

    public ?string $estimated_value = null;

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function getStagesProperty(): array
    {
        return Lead::PIPELINE_STAGES;
    }

    public function getLeadsProperty()
    {
        return Lead::with(['contactSiteProfile.contact', 'assignedTo'])
            ->latest()
            ->get()
            ->groupBy('pipeline_stage');
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

    /**
     * فقط کاربرانی که طبق LeadPolicy واقعاً مجاز به کار با لید این شرکت‌اند
     * (holding_admin/operator). عمداً از متد مرکزی User::hasRoleInCompany()
     * استفاده می‌شود (بند ۹/۱۱ CLAUDE.md)، نه یک کوئری مستقل روی
     * companyRoles — کوئری مستقل bypass ادمین کل (is_super_admin) را در نظر
     * نمی‌گیرد و کاربری مثل «مدیر کل» که فقط در یک شرکت رکورد واقعی
     * UserCompanyRole دارد (مثلاً «ستاد مشترک») را در بقیه شرکت‌ها اشتباهاً
     * از فهرست تخصیص حذف می‌کند، در حالی که طبق hasRoleInCompany() او در
     * همه‌جا مجاز است.
     */
    public function getAssignableUsersProperty()
    {
        $companyId = app(CompanyContext::class)->id();

        if ($companyId === null) {
            return collect();
        }

        return User::orderBy('full_name')
            ->get(['id', 'full_name', 'is_super_admin'])
            ->filter(fn (User $user) => $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']))
            ->values();
    }

    public function create(CreateLead $action): void
    {
        $this->validate([
            'contact_site_profile_id' => ['nullable', Rule::in($this->siteProfileOptions->pluck('id'))],
            'source' => ['required', Rule::in(Lead::SOURCES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle([
            'owner_company_id' => app(CompanyContext::class)->id(),
            'contact_site_profile_id' => $this->contact_site_profile_id ?: null,
            'source' => $this->source,
            'estimated_value' => $this->estimated_value ?: null,
            'notes' => $this->notes ?: null,
        ], auth()->user());

        $this->reset(['contact_site_profile_id', 'estimated_value', 'notes', 'showCreateForm']);
        $this->source = Lead::SOURCE_WEBSITE;
    }

    public function changeStage(UpdateLeadStage $action, string $leadId, string $newStage): void
    {
        $lead = Lead::findOrFail($leadId);

        $action->handle($lead, $newStage, auth()->user());
    }

    public function assign(AssignLead $action, string $leadId, string $userId): void
    {
        $lead = Lead::findOrFail($leadId);

        $action->handle($lead, $userId ?: null, auth()->user());
    }

    public function render()
    {
        return view('livewire.crm.lead-board', [
            'stages' => $this->stages,
            'leadsByStage' => $this->leads,
            'siteProfileOptions' => $this->siteProfileOptions,
            'assignableUsers' => $this->assignableUsers,
        ]);
    }
}
