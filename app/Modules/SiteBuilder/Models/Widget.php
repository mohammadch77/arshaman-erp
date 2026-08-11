<?php

namespace App\Modules\SiteBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    use HasUuids;

    protected $fillable = [
        'widget_key',
        'name',
        'icon',
        'default_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * تعریف فیلدهای قابل‌ویرایش این ویجت، طبق default_config['editable_fields']
     * (هر آیتم: key/type/label). PageContentEditor از همین برای ساخت فرم استفاده می‌کند.
     */
    public function editableFields(): array
    {
        return $this->default_config['editable_fields'] ?? [];
    }
}
