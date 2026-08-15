<?php

namespace App\Modules\SiteBuilder\Actions;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Models\Page;
use Illuminate\Support\Facades\Gate;

/**
 * حذف نرم یک صفحه — تنها مسیر مجاز حذف (بند ۹ CLAUDE.md: authorize داخل خودِ
 * Action، نه فقط UI). ارجاع‌های site_settings.homepage_page_id/blog_page_id
 * خودکار توسط هوک Page::booted()['deleting'] پاک می‌شوند، پس اینجا فقط
 * $page->delete() لازم است.
 */
class DeletePage
{
    public function handle(Page $page, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $page);

        $page->delete();
    }
}
