<?php

namespace App\Modules\SiteBuilder\Models;

use App\Modules\SiteBuilder\Enums\LayoutType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LayoutDemo extends Model
{
    use HasUuids;

    protected $fillable = [
        'layout_type',
        'name',
        'thumbnail_path',
        'widget_tree',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'layout_type' => LayoutType::class,
            'widget_tree' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
