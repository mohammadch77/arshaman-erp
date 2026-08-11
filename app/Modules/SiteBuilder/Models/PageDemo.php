<?php

namespace App\Modules\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageDemo extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_category_id',
        'name',
        'thumbnail_path',
        'widget_tree',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'widget_tree' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_category_id');
    }
}
