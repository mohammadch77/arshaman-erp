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

class Leave extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $fillable = [
        'employee_id',
        'owner_company_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_count',
        'leave_status',
        'reason',
        'approved_by_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'leave_type' => LeaveType::class,
            'leave_status' => LeaveStatus::class,
        ];
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
