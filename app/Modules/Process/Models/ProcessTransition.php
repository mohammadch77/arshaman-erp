<?php

namespace App\Modules\Process\Models;

use App\Modules\Process\Enums\TransitionResult;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTransition extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'from_step_id',
        'to_step_id',
        'on_result',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'on_result' => TransitionResult::class,
            'display_order' => 'integer',
        ];
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class, 'to_step_id');
    }
}
