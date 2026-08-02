<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف تایم‌لاین تعامل با مخاطب در یک شرکت مشخص (تماس، تلگرام، فرم سایت،
 * یا خرید). فقط created_at دارد، نه updated_at — تعامل ثبت‌شده ویرایش نمی‌شود.
 */
class Interaction extends Model
{
    use BelongsToCompany, HasUuids;

    const UPDATED_AT = null;

    public const TYPE_CALL = 'call';

    public const TYPE_TELEGRAM = 'telegram';

    public const TYPE_SITE_FORM = 'site_form';

    public const TYPE_PURCHASE = 'purchase';

    public const MANUAL_TYPES = [
        self::TYPE_CALL,
        self::TYPE_TELEGRAM,
        self::TYPE_SITE_FORM,
    ];

    protected $fillable = [
        'owner_company_id',
        'contact_site_profile_id',
        'interaction_type',
        'notes',
        'source_order_id',
        'occurred_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function contactSiteProfile(): BelongsTo
    {
        return $this->belongsTo(ContactSiteProfile::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * TODO: فاز ۳ (ماژول Sales) — وقتی سفارشی ثبت می‌شود، یک Interaction از نوع
     * 'purchase' با source_order_id پر خودکار ساخته شود. طبق BACKLOG.md
     * («تبدیل خودکار خرید به تعامل»)، فعلاً فقط امضا؛ بدون فراخوانی واقعی.
     */
    public static function createFromOrder(): void
    {
        throw new \LogicException('پیاده‌سازی در فاز ۳ (ماژول Sales) — نگاه کن BACKLOG.md.');
    }
}
