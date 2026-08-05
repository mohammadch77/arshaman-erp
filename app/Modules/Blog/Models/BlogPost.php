<?php

namespace App\Modules\Blog\Models;

use App\Modules\Blog\Enums\BlogPostStatus;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_company_id',
        'category_id',
        'author_user_id',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'content_html',
        'featured_image_path',
        'reading_time_minutes',
        'post_status',
        'published_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'post_status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'reading_time_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * پستی که برای بازدیدکننده مهمان قابل نمایش است: منتشرشده و زمان انتشارش
     * فرارسیده. پست scheduled با published_at آینده عمداً حذف می‌شود تا با
     * URL مستقیم هم دیده نشود.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('post_status', BlogPostStatus::Published)
            ->where('published_at', '<=', now());
    }

    /**
     * reading_time_minutes معمولاً دستی و اغلب خالی است؛ اگر ثبت نشده باشد،
     * یک تخمین ساده بر پایه تعداد کلمه (۲۰۰ کلمه در دقیقه) جایگزین می‌شود تا
     * صفحه عمومی همیشه یک عدد معنادار نشان دهد.
     */
    public function getDisplayReadingTimeAttribute(): int
    {
        return $this->reading_time_minutes
            ?? max(1, (int) ceil(str_word_count(strip_tags($this->content_html ?? '')) / 200));
    }
}
