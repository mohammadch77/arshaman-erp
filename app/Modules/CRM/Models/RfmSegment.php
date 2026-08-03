<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بخش‌بندی RFM یک ContactSiteProfile — حداکثر یک رکورد به‌ازای هر پروفایل
 * (UNIQUE contact_site_profile_id)؛ CalculateRfmSegment آن را بازمحاسبه
 * می‌کند، نه رکورد تازه می‌سازد. عمداً بدون created_by/updated_by و بدون
 * created_at/updated_at — این رکورد همیشه از محاسبه خودکار می‌آید، نه ورود
 * دستی کاربر؛ تنها مهر زمانی معنادار calculated_at است.
 */
class RfmSegment extends Model
{
    use BelongsToCompany, HasUuids;

    public $timestamps = false;

    public const SEGMENT_VIP = 'vip';

    public const SEGMENT_AT_RISK = 'at_risk';

    public const SEGMENT_DORMANT = 'dormant';

    public const SEGMENT_NEW = 'new';

    public const SEGMENTS = [
        self::SEGMENT_VIP,
        self::SEGMENT_AT_RISK,
        self::SEGMENT_DORMANT,
        self::SEGMENT_NEW,
    ];

    protected $fillable = [
        'owner_company_id',
        'contact_site_profile_id',
        'recency_days',
        'frequency_count',
        'monetary_amount',
        'segment',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'recency_days' => 'integer',
            'frequency_count' => 'integer',
            'monetary_amount' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * قاعده ساده‌شده این فاز (config/crm.php → rfm):
     * - غیرفعال: گذشت dormant_days از آخرین خرید، صرف‌نظر از تعداد خرید.
     * - ویژه: هم تازگی زیر at_risk_days و هم تعداد خرید >= vip_min_frequency.
     * - بقیه (شامل خرید کم اما تازه، یا تازگی بین دو آستانه): در معرض ریزش.
     */
    public static function classify(int $recencyDays, int $frequencyCount): string
    {
        $atRiskDays = config('crm.rfm.at_risk_days');
        $dormantDays = config('crm.rfm.dormant_days');
        $vipMinFrequency = config('crm.rfm.vip_min_frequency');

        if ($recencyDays > $dormantDays) {
            return self::SEGMENT_DORMANT;
        }

        if ($recencyDays <= $atRiskDays && $frequencyCount >= $vipMinFrequency) {
            return self::SEGMENT_VIP;
        }

        return self::SEGMENT_AT_RISK;
    }

    public static function segmentLabel(string $segment): string
    {
        return match ($segment) {
            self::SEGMENT_VIP => 'ویژه',
            self::SEGMENT_AT_RISK => 'در معرض ریزش',
            self::SEGMENT_DORMANT => 'غیرفعال',
            self::SEGMENT_NEW => 'جدید',
            default => $segment,
        };
    }

    public function contactSiteProfile(): BelongsTo
    {
        return $this->belongsTo(ContactSiteProfile::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }
}
