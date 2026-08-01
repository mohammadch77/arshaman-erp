<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\LeaveStatus;
use App\Modules\HR\Enums\LeaveType;
use Database\Factories\LeaveFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leave extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'owner_company_id',
        'leave_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days_count',
        'hours_count',
        'leave_status',
        'reason',
        'rejection_reason',
        'approved_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'leave_type' => LeaveType::class,
            'leave_status' => LeaveStatus::class,
            'hours_count' => 'decimal:2',
        ];
    }

    /**
     * آیا این درخواست هنوز در وضعیتی است که خودِ کارمند بتواند تغییرش دهد؟
     * بعد از تأیید یا رد، درخواست فقط قابل مشاهده است.
     */
    public function isEditableByOwner(): bool
    {
        return $this->leave_status === LeaveStatus::Pending;
    }

    protected static function newFactory(): LeaveFactory
    {
        return LeaveFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
