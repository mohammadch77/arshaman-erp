<?php

namespace App\Modules\Process\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessInstanceFieldValue extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'process_instance_id',
        'process_form_field_id',
        'value',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(ProcessFormField::class, 'process_form_field_id');
    }
}
