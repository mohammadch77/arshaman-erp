<?php

namespace App\Modules\SiteBuilder\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    use BelongsToCompany, HasUuids;

    protected $fillable = [
        'owner_company_id',
        'site_title',
        'site_tagline',
        'logo_path',
        'favicon_path',
        'homepage_page_id',
        'blog_page_id',
        'active_header_demo_id',
        'active_footer_demo_id',
    ];

    public function homepage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'homepage_page_id');
    }

    public function blogPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'blog_page_id');
    }

    public function activeHeaderDemo(): BelongsTo
    {
        return $this->belongsTo(LayoutDemo::class, 'active_header_demo_id');
    }

    public function activeFooterDemo(): BelongsTo
    {
        return $this->belongsTo(LayoutDemo::class, 'active_footer_demo_id');
    }
}
