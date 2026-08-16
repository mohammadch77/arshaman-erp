<?php

namespace App\Modules\Process\Models;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\LogAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessInstanceLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'owner_company_id',
        'process_instance_id',
        'step_id',
        'actor_user_id',
        'action',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'action' => LogAction::class,
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class, 'step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
