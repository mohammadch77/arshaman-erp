<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\HR\Enums\RecordedBy;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $fillable = [
        'employee_id',
        'owner_company_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'recorded_by',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'recorded_by' => RecordedBy::class,
        ];
    }

    protected static function newFactory(): AttendanceFactory
    {
        return AttendanceFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * تردد هنوز باز است — ورود ثبت شده ولی خروج نه.
     *
     * هر کارمند حداکثر یک تردد باز دارد؛ این را ایندکس یکتای
     * `uq_attendance_single_open_punch` در سطح دیتابیس تضمین می‌کند.
     */
    public function isOpen(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at === null;
    }

    /**
     * مدت **همین تردد** به دقیقه، یا null تا وقتی بسته نشده.
     *
     * ⚠️ این «کارکرد روز» نیست. یک روز می‌تواند چند تردد داشته باشد؛ کسری و
     * اضافه‌کاری فقط در سطح روز معنا دارند و کارشان با AttendanceCalculator است.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if ($this->check_in_at === null || $this->check_out_at === null) {
            return null;
        }

        $minutes = (int) $this->check_in_at->diffInMinutes($this->check_out_at, false);

        // خروج قبل از ورود یک داده نامعتبر است، نه مدت منفی.
        return max($minutes, 0);
    }
}
