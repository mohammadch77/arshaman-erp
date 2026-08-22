<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Actions\CreateCampaign;
use App\Modules\CRM\Actions\ToggleCampaignActive;
use App\Modules\CRM\Actions\TriggerWinbackCampaign;
use App\Modules\CRM\Enums\CampaignChannel;
use App\Modules\CRM\Enums\CampaignTriggerType;
use App\Modules\CRM\Models\Campaign;
use App\Modules\CRM\Models\CampaignLog;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * مدیریت کمپین‌ها (فهرست + ساخت) + فهرست CampaignLog شرکت فعال سوییچر —
 * شرکت‌محور، همان الگوی LeadBoard/RfmSegmentIndex.
 */
class CampaignIndex extends Component
{
    use Toast;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|string')]
    public string $triggerType = '';

    #[Validate('required|string')]
    public string $channel = '';

    #[Validate('required|string')]
    public string $messageTemplate = '';

    public bool $showCreateForm = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Campaign::class);
    }

    public function getCampaignsProperty()
    {
        return Campaign::query()->latest()->get();
    }

    public function getLogsProperty()
    {
        return CampaignLog::withoutGlobalScopes()
            ->whereIn('campaign_id', $this->campaigns->pluck('id'))
            ->with(['campaign', 'contactSiteProfile.contact'])
            ->latest('sent_at')
            ->limit(50)
            ->get();
    }

    public function getTriggerTypeOptionsProperty(): array
    {
        return collect(CampaignTriggerType::cases())
            ->map(fn (CampaignTriggerType $case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();
    }

    public function getChannelOptionsProperty(): array
    {
        return collect(CampaignChannel::cases())
            ->map(fn (CampaignChannel $case) => ['id' => $case->value, 'name' => $case->label()])
            ->all();
    }

    public function create(CreateCampaign $action): void
    {
        $this->authorize('create', [Campaign::class, app(CompanyContext::class)->id()]);

        $validated = $this->validate();

        $action->handle([
            'owner_company_id' => app(CompanyContext::class)->id(),
            'name' => $validated['name'],
            'trigger_type' => $validated['triggerType'],
            'channel' => $validated['channel'],
            'message_template' => $validated['messageTemplate'],
            'is_active' => true,
        ], auth()->user());

        $this->reset(['name', 'triggerType', 'channel', 'messageTemplate']);
        $this->showCreateForm = false;

        $this->success('کمپین ساخته شد.');
    }

    public function toggleActive(string $campaignId, ToggleCampaignActive $action): void
    {
        $campaign = Campaign::findOrFail($campaignId);

        $action->handle($campaign, auth()->user());

        $this->success('وضعیت فعال‌بودن کمپین تغییر کرد.');
    }

    public function trigger(string $campaignId, TriggerWinbackCampaign $action): void
    {
        $campaign = Campaign::findOrFail($campaignId);

        $logs = $action->handle($campaign, auth()->user());

        $this->success("شبیه‌سازی ارسال برای {$logs->count()} مخاطب غیرفعال انجام شد.");
    }

    public function canTrigger(Campaign $campaign): bool
    {
        return $campaign->trigger_type === CampaignTriggerType::Winback90Days
            && Gate::forUser(auth()->user())->allows('trigger', $campaign);
    }

    public function render()
    {
        return view('livewire.crm.campaign-index', [
            'campaigns' => $this->campaigns,
            'logs' => $this->logs,
        ]);
    }
}
