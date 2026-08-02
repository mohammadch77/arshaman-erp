<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PartyType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        // نگهبان دومِ قاعده «حداقل مشتری یا تأمین‌کننده» — مکمل CHECK سطح دیتابیس
        // (که در SQLite محیط تست قابل تعریف نیست)، طبق الگوی نگهبان دولایه Payslip در ماژول HR.
        static::saving(function (self $party) {
            if (! $party->is_customer && ! $party->is_supplier) {
                throw new \RuntimeException('طرف‌حساب باید حداقل مشتری یا تأمین‌کننده باشد.');
            }
        });
    }

    protected $fillable = [
        'owner_company_id',
        'name',
        'party_type',
        'is_customer',
        'is_supplier',
        'phone',
        'email',
        'economic_code',
        'address',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
        ];
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
