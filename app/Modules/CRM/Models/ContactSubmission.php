<?php

namespace App\Modules\CRM\Models;

use App\Modules\CRM\Enums\ContactSubmissionStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * پیام فرم «تماس با ما» — عمداً بدون BelongsToCompany. ثبت‌کننده کاربر مهمان
 * (بدون session/CompanyContext) است، پس owner_company_id همیشه صریح از
 * پارامتر route (company:slug) توسط SubmitContactForm پر می‌شود.
 *
 * تاریخچه کامل تغییر status در activity_log نگه داشته می‌شود (logOnly)؛
 * ستون‌های status/replied_at خودِ مدل فقط «خلاصه وضعیت فعلی»اند، نه تاریخچه —
 * این دو مکمل‌اند، نه جایگزین هم. causer هر رکورد لاگ توسط
 * UpdateContactSubmissionStatus صریح ست می‌شود (CauserResolver::setCauser)،
 * نه به‌صورت خودکار از Auth::user()، تا اگر بعداً از یک context بدون session
 * (job/queue) صدا زده شد causer گم نشود.
 */
class ContactSubmission extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    /**
     * فقط رویداد 'updated' لاگ می‌شود، نه 'created'/'deleted' (پیش‌فرض تریت).
     * چون هدف تاریخچه *تغییر* وضعیت است، نه ثبت اولیه پیام (که خودش
     * created_at ستون دارد) یا حذف آن.
     */
    protected static array $recordEvents = ['updated'];

    protected $fillable = [
        'owner_company_id',
        'full_name',
        'phone',
        'email',
        'subject',
        'message',
        'source',
        'status',
        'ip_address',
        'read_at',
        'replied_at',
        'replied_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactSubmissionStatus::class,
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ContactSubmissionAttempt::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('contact_submission')
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
