<?php

namespace App\Modules\SiteBuilder\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Enums\PageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_company_id',
        'page_demo_id',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'widget_tree',
        'content_html',
        'extra_css',
        'extra_js',
        'page_status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'widget_tree' => 'array',
            'page_status' => PageStatus::class,
        ];
    }

    public function demo(): BelongsTo
    {
        return $this->belongsTo(PageDemo::class, 'page_demo_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
