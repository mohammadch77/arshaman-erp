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
        'late_minutes',
        'overtime_minutes',
        'recorded_by',
        'created_by_user_id',
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
}
