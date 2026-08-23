<?php

namespace App\Modules\CRM\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک پاسخ روی تیکت. عمداً بدون owner_company_id/BelongsToCompany — طبق
 * جدول ۹ schema_crm_mysql.sql این جدول شرکت مستقل ندارد، شرکت از طریق
 * ticket_id قابل‌دسترس است. فقط created_at، بدون updated_at — پاسخ ثبت‌شده
 * ویرایش نمی‌شود، مثل Interaction.
 */
class TicketReply extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
