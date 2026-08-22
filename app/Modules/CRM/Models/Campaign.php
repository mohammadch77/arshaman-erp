<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\CampaignChannel;
use App\Modules\CRM\Enums\CampaignTriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تعریف کمپین (قالب پیام + trigger). ارسال واقعی فعلاً شبیه‌سازی است —
 * نگاه کن App\Modules\CRM\Services\NotificationChannel.
 */
class Campaign extends Model
{
    use BelongsToCompany, HasUuids;

    protected $fillable = [
        'owner_company_id',
        'name',
        'trigger_type',
        'channel',
        'message_template',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => CampaignTriggerType::class,
            'channel' => CampaignChannel::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }
}
