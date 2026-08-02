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

    /**
     * آیا کاربر در $companyId نقش دارد — و اگر $roleName داده شده، دقیقاً همان
     * نقش (یا یکی از نقش‌های فهرست) را در **همان** شرکت دارد؟ یک کوئری واحد،
     * scoped هم روی شرکت هم روی نام نقش.
     *
     * قبلاً Policy ها دو متد جدا را ترکیب می‌کردند:
     * `hasRoleInCompany($companyId) && hasRole('operator')` — که چون
     * hasRole() سراسری است (نقش را در *هر* شرکتی که کاربر داشته باشد پیدا
     * می‌کند)، کاربری که فقط viewer شرکت ب بود ولی operator شرکت الف هم بود،
     * برای عملیات شرکت ب هم مجاز تشخیص داده می‌شد — نشت ایزولاسیون شرکت
     * (بند ۵.۱ CLAUDE.md). این متد تنها راه مجاز برای چک نقش+شرکت با هم است؛
     * هرگز hasRoleInCompany() و hasRole() را جدا از هم صدا نزن (بند ۹).
     *
     * @param  string|array<int, string>|null  $roleName  null = فقط وجود *هر* نقشی در آن شرکت
     */
    public function hasRoleInCompany(string $companyId, string|array|null $roleName = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->companyRoles()
            ->where('owner_company_id', $companyId)
            ->when(
                $roleName !== null,
                fn ($query) => $query->whereHas(
                    'role',
                    fn ($roleQuery) => is_array($roleName)
                        ? $roleQuery->whereIn('name', $roleName)
                        : $roleQuery->where('name', $roleName)
                )
            )
            ->exists();
    }

    /**
     * سراسری است — نقش را در *هر* شرکتی که کاربر داشته باشد پیدا می‌کند، نه
     * فقط یک شرکت مشخص. فقط برای تصمیم‌های دسترسی که واقعاً holding-wide
     * هستند (مثلاً ContactPolicy — نمای ۳۶۰ چندشرکتی، یا UserPolicy —
     * مدیریت کاربران/نقش که ذاتاً محدود به یک شرکت نیست) مجاز است.
     * برای هر تصمیمی که به یک شرکت مشخص مربوط می‌شود، همیشه
     * hasRoleInCompany($companyId, $roleName) را به‌جای این متد به‌کار ببر.
     */
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
