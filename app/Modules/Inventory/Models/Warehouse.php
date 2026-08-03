<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// عمداً بدون BelongsToCompany — طبق بند ۵.۸ CLAUDE.md انبار فیزیکاً بین شرکت‌ها
// مشترک است. ایزولاسیون مالکیت روی Stock است، نه اینجا.
class Warehouse extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
