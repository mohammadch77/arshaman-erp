<?php

namespace App\Modules\CRM\Models;

use App\Modules\CRM\Enums\CampaignChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاریخچه ارسال یک کمپین برای یک ContactSiteProfile. طبق schema_crm_mysql.sql
 * عمداً بدون owner_company_id مستقل (نه BelongsToCompany) — ایزولاسیون شرکت
 * از طریق campaign_id/contact_site_profile_id تضمین می‌شود، مثل ticket_replies.
 * بدون created_at/updated_at — فقط sent_at.
 */
class CampaignLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_SIMULATED = 'simulated';

    protected $fillable = [
        'campaign_id',
        'contact_site_profile_id',
        'channel',
        'status',
        'payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => CampaignChannel::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contactSiteProfile(): BelongsTo
    {
        return $this->belongsTo(ContactSiteProfile::class);
    }
}
