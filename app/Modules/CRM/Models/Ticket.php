<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\TicketPriority;
use App\Modules\CRM\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * تیکت پشتیبانی متصل به یک پروفایل سایتِ مخاطب (contact_site_profile).
 * status/priority عمداً VARCHAR+enum PHP هستند (بند ۳ CLAUDE.md) — بدون
 * استثنای مستند ENUM نیتیو.
 */
class Ticket extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_company_id',
        'contact_site_profile_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to_user_id',
        'created_by_user_id',
    ];

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

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at');
    }

    public function statusEnum(): TicketStatus
    {
        return TicketStatus::from($this->status);
    }

    public function priorityEnum(): TicketPriority
    {
        return TicketPriority::from($this->priority);
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed->value;
    }
}
