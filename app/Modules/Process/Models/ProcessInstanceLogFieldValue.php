<?php

namespace App\Modules\Process\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessInstanceLogFieldValue extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'process_instance_log_id',
        'process_form_field_id',
        'value',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(ProcessInstanceLog::class, 'process_instance_log_id');
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(ProcessFormField::class, 'process_form_field_id');
    }
}
