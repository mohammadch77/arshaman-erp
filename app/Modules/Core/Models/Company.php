<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\BusinessType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'base_currency',
        'woocommerce_config',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'woocommerce_config' => 'encrypted:array',
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
