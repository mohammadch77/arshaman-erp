<?php

namespace App\Modules\Process\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\ProcessStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessInstance extends Model
{
    use BelongsToCompany, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'owner_company_id',
        'process_definition_id',
        'subject_type',
        'subject_id',
        'request_data',
        'current_step_id',
        'status',
        'started_by_user_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'status' => ProcessStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * withTrashed() عمداً: نظارت (ProcessOversight) باید بتواند instance های
     * متعلق به یک تعریف بایگانی‌شده (soft-deleted، بند ۳ Session ۶ ماژول
     * Process) را هم نشان دهد — بدون آن، global scope پیش‌فرض SoftDeletes
     * این رابطه را برای هر تعریف بایگانی‌شده null برمی‌گرداند.
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id')->withTrashed();
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class, 'current_step_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProcessInstanceLog::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
