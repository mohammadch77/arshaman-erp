<?php

namespace App\Modules\SiteBuilder\Models;

use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_key',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'category_key' => PageCategoryKey::class,
        ];
    }

    public function demos(): HasMany
    {
        return $this->hasMany(PageDemo::class);
    }
}
