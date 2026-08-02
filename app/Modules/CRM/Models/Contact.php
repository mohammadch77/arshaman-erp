<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * پروفایل واحد هلدینگی مخاطب (Golden Record) — عمداً بدون BelongsToCompany،
 * درست مثل Holiday در ماژول HR: بین‌شرکتی است. یک مخاطب می‌تواند در چند شرکت
 * (چند ContactSiteProfile) سابقه داشته باشد اما فقط یک رکورد Contact دارد.
 */
class Contact extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function siteProfiles(): HasMany
    {
        return $this->hasMany(ContactSiteProfile::class);
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
