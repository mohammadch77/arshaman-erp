<?php

namespace App\Modules\Core\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'is_active',
        'is_super_admin',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function companyRoles(): HasMany
    {
        return $this->hasMany(UserCompanyRole::class);
    }

    public function hasRoleInCompany(string $companyId): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->companyRoles()->where('owner_company_id', $companyId)->exists();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->companyRoles()
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_name', 'email', 'is_active', 'is_super_admin'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
