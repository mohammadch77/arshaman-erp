<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\ContractType;
use App\Modules\HR\Enums\EmploymentStatus;
use Carbon\Carbon;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_company_id',
        'user_id',
        'full_name',
        'national_id',
        'phone',
        'address',
        'position',
        'hire_date',
        'termination_date',
        'employment_status',
        'contract_type',
        'contract_start_date',
        'contract_end_date',
        'base_salary',
        'currency_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'employment_status' => EmploymentStatus::class,
            'contract_type' => ContractType::class,
            'base_salary' => 'decimal:2',
        ];
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    // اتصال از طریق سیستم دعوت‌نامه موجود (User Invitation) انجام می‌شود،
    // نه در این Session — نگاه کن Session 2.5 در docs/PROJECT_02_HR.md
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isContractExpiringSoon(): bool
    {
        if ($this->contract_end_date === null) {
            return false;
        }

        return $this->contract_end_date->between(Carbon::today(), Carbon::today()->addDays(30));
    }
}
