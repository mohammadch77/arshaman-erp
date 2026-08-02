<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سرنخ فروش. contact_site_profile_id عمداً nullable است — یک لید می‌تواند
 * قبل از این‌که مخاطب کامل ثبت شود، فقط با منبع (اینستاگرام/سایت/...) وجود
 * داشته باشد.
 */
class Lead extends Model
{
    use BelongsToCompany, HasUuids;

    public const SOURCE_INSTAGRAM = 'instagram';

    public const SOURCE_WEBSITE = 'website';

    public const SOURCE_TELEGRAM = 'telegram';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_OTHER = 'other';

    public const SOURCES = [
        self::SOURCE_INSTAGRAM,
        self::SOURCE_WEBSITE,
        self::SOURCE_TELEGRAM,
        self::SOURCE_REFERRAL,
        self::SOURCE_OTHER,
    ];

    public const STAGE_NEW = 'new';

    public const STAGE_CONTACTED = 'contacted';

    public const STAGE_QUALIFIED = 'qualified';

    public const STAGE_PROPOSAL = 'proposal';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    /**
     * ترتیب ستون‌های قیف در نمای board.
     */
    public const PIPELINE_STAGES = [
        self::STAGE_NEW,
        self::STAGE_CONTACTED,
        self::STAGE_QUALIFIED,
        self::STAGE_PROPOSAL,
        self::STAGE_WON,
        self::STAGE_LOST,
    ];

    /**
     * ماشین وضعیت قیف (بند ۶ CLAUDE.md — ترنزیشن تعریف‌نشده رد می‌شود):
     * حرکت رو به جلو یک‌به‌یک، بعلاوه «باخت» از هر مرحله فعال ممکن است.
     * won/lost پایانی‌اند — بدون Action بازگشایی، مثل بستن سال مالی.
     */
    public const TRANSITIONS = [
        self::STAGE_NEW => [self::STAGE_CONTACTED, self::STAGE_LOST],
        self::STAGE_CONTACTED => [self::STAGE_QUALIFIED, self::STAGE_LOST],
        self::STAGE_QUALIFIED => [self::STAGE_PROPOSAL, self::STAGE_LOST],
        self::STAGE_PROPOSAL => [self::STAGE_WON, self::STAGE_LOST],
        self::STAGE_WON => [],
        self::STAGE_LOST => [],
    ];

    protected $fillable = [
        'owner_company_id',
        'contact_site_profile_id',
        'source',
        'pipeline_stage',
        'assigned_to_user_id',
        'estimated_value',
        'notes',
        'contract_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
        ];
    }

    public static function canTransition(string $fromStage, string $toStage): bool
    {
        return in_array($toStage, self::TRANSITIONS[$fromStage] ?? [], true);
    }

    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::STAGE_NEW => 'جدید',
            self::STAGE_CONTACTED => 'تماس گرفته‌شده',
            self::STAGE_QUALIFIED => 'واجد شرایط',
            self::STAGE_PROPOSAL => 'پیشنهاد قیمت',
            self::STAGE_WON => 'برد',
            self::STAGE_LOST => 'باخت',
            default => $stage,
        };
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            self::SOURCE_INSTAGRAM => 'اینستاگرام',
            self::SOURCE_WEBSITE => 'سایت',
            self::SOURCE_TELEGRAM => 'تلگرام',
            self::SOURCE_REFERRAL => 'معرفی',
            self::SOURCE_OTHER => 'سایر',
            default => $source,
        };
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function contactSiteProfile(): BelongsTo
    {
        return $this->belongsTo(ContactSiteProfile::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
