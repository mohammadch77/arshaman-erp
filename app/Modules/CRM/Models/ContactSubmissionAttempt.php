<?php

namespace App\Modules\CRM\Models;

use App\Modules\CRM\Enums\ContactAttemptOutcome;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک تلاش تماس مشخص روی یک پیام تماس با ما، با نتیجه دقیقش. غیرقابل‌ویرایش —
 * فقط created_at دارد، نه updated_at (همان الگوی Interaction).
 */
class ContactSubmissionAttempt extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'owner_company_id',
        'contact_submission_id',
        'attempted_by_user_id',
        'outcome',
        'note',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => ContactAttemptOutcome::class,
            'attempted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ContactSubmission::class, 'contact_submission_id');
    }

    public function attemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by_user_id');
    }
}
