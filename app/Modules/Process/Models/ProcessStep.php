<?php

namespace App\Modules\Process\Models;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessStep extends Model
{
    use HasUuids;

    public $timestamps = true;

    protected $fillable = [
        'process_definition_id',
        'step_key',
        'name',
        'step_type',
        'assignment_type',
        'assigned_role',
        'assigned_user_id',
        'condition_field',
        'condition_operator',
        'condition_value',
    ];

    protected function casts(): array
    {
        return [
            'step_type' => StepType::class,
            'assignment_type' => AssignmentType::class,
            'condition_operator' => ConditionOperator::class,
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(ProcessTransition::class, 'from_step_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(ProcessTransition::class, 'to_step_id');
    }
}
