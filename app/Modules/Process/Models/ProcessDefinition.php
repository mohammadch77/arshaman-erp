<?php

namespace App\Modules\Process\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessDefinition extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    public $timestamps = true;

    protected $fillable = [
        'owner_company_id',
        'name',
        'process_key',
        'version',
        'is_current_version',
        'subject_type',
        'request_form_fields',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'request_form_fields' => 'array',
            'is_active' => 'boolean',
            'is_current_version' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcessStep::class)->orderBy('display_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function isFreeForm(): Attribute
    {
        return Attribute::get(fn () => $this->subject_type === null);
    }
}
