<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\CampaignTriggerType;
use App\Modules\CRM\Models\Campaign;
use App\Modules\CRM\Models\CampaignLog;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\RfmSegment;
use App\Modules\CRM\Services\NotificationChannel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * فقط برای کمپین‌های trigger_type=winback_90days. مخاطبین هدف: پروفایل‌های
 * شرکت کمپین که RfmSegment::SEGMENT_DORMANT دارند (نگاه کن CalculateRfmSegment).
 * ارسال فعلاً کاملاً شبیه‌سازی است — نگاه کن NotificationChannel.
 */
class TriggerWinbackCampaign
{
    public function __construct(private readonly NotificationChannel $notificationChannel) {}

    /**
     * @return Collection<int, CampaignLog>
     */
    public function handle(Campaign $campaign, User $actor): Collection
    {
        Gate::forUser($actor)->authorize('trigger', $campaign);

        if ($campaign->trigger_type !== CampaignTriggerType::Winback90Days) {
            throw new InvalidArgumentException('این کمپین از نوع بازگشت مشتریان غیرفعال (winback_90days) نیست.');
        }

        $dormantProfiles = ContactSiteProfile::withoutGlobalScopes()
            ->where('owner_company_id', $campaign->owner_company_id)
            ->whereHas('rfmSegment', fn ($query) => $query->withoutGlobalScopes()->where('segment', RfmSegment::SEGMENT_DORMANT))
            ->with('contact')
            ->get();

        $logs = new Collection;

        foreach ($dormantProfiles as $profile) {
            $recipientName = $profile->contact?->full_name ?? 'مشتری عزیز';
            $target = $profile->contact?->phone ?? $profile->contact?->email ?? '-';
            $message = str_replace('{نام}', $recipientName, $campaign->message_template);

            $this->notificationChannel->send($campaign->channel->value, $target, $message);

            $logs->push(CampaignLog::create([
                'campaign_id' => $campaign->id,
                'contact_site_profile_id' => $profile->id,
                'channel' => $campaign->channel->value,
                'status' => CampaignLog::STATUS_SIMULATED,
                'payload' => ['message' => $message, 'target' => $target],
                'sent_at' => now(),
            ]));
        }

        return $logs;
    }
}
