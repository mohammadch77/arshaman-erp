<?php

namespace App\Modules\SiteBuilder\Actions;

use App\Modules\Core\Models\User;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Support\Facades\Gate;

class CreatePageFromDemo
{
    public function __construct(private WidgetContentRenderer $renderer) {}

    /**
     * @param  array{owner_company_id: string, page_demo_id: string, title: string, slug: string, meta_title: ?string, meta_description: ?string}  $data
     * @param  array<int, array<string, mixed>>|null  $widgetTree  اگر داده شود (مسیر PageCreateFlow —
     *   کاربر پیش از ذخیره مقادیر دمو را در حافظه ویرایش کرده)، به‌جای widget_tree
     *   خام دمو همین درخت ذخیره می‌شود. ساختار/تعداد/ترتیب گره‌ها همچنان دست‌کاری
     *   نمی‌شود، فقط منبع تعیین می‌کند از کجا خوانده شود.
     */
    public function handle(array $data, User $actor, ?array $widgetTree = null): Page
    {
        Gate::forUser($actor)->authorize('create', [Page::class, $data['owner_company_id']]);

        $demo = PageDemo::findOrFail($data['page_demo_id']);

        // widget_tree دموی مرجع عیناً کپی می‌شود — کاربر فقط بعداً مقادیر
        // داخل فیلدها را عوض می‌کند، ساختار همیشه همان دموست.
        $widgetTree ??= $demo->widget_tree;

        return Page::create([
            'owner_company_id' => $data['owner_company_id'],
            'page_demo_id' => $demo->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'widget_tree' => $widgetTree,
            'content_html' => $this->renderer->render($widgetTree),
            'page_status' => PageStatus::Draft->value,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }
}
