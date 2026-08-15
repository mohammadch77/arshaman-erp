<?php

namespace App\Modules\SiteBuilder\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Enums\PageStatus;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * برخلاف BlogPost که علاوه بر post_status یک published_at زمان‌بندی‌شده هم
     * دارد، Page فقط یک بعد وضعیت دارد (بدون زمان‌بندی انتشار در این Session) —
     * پس این scope فقط page_status را چک می‌کند.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('page_status', PageStatus::Published);
    }

    /**
     * وقتی صفحه‌ای که به‌عنوان homepage/blog_page در site_settings انتخاب شده
     * منتشرنشده می‌شود یا حذف می‌شود، آن اشاره‌ها باید خودکار null شوند — وگرنه
     * site_settings به صفحه‌ای غیرقابل‌نمایش اشاره می‌کند و PublicSiteController
     * صفحه پیدا نمی‌کند. این گارد در مدل است نه فقط در Action مشخصی، چون تغییر
     * page_status از چند مسیر ممکن است اتفاق بیفتد (بند ۹ CLAUDE.md).
     */
    protected static function booted(): void
    {
        static::updating(function (Page $page) {
            if ($page->isDirty('page_status') && $page->page_status !== PageStatus::Published) {
                static::detachFromSiteSettings($page);
            }
        });

        static::deleting(function (Page $page) {
            static::detachFromSiteSettings($page);
        });
    }

    protected static function detachFromSiteSettings(Page $page): void
    {
        SiteSetting::withoutGlobalScopes()->where('homepage_page_id', $page->id)->update(['homepage_page_id' => null]);
        SiteSetting::withoutGlobalScopes()->where('blog_page_id', $page->id)->update(['blog_page_id' => null]);
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
