<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Support\StorageUrl;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * جایگزینی marker های contact_form/blog_post_list (نگاه کن
 * WidgetContentRenderer::renderContactForm/renderBlogPostList) با محتوای
 * واقعی — فقط در مسیر رندر صفحه‌ی عمومی (PublicSiteController) صدا زده
 * می‌شود، هرگز در ذخیره/پیش‌نمایش ادمین. content_html خودِ صفحه (و پیش‌نمایش
 * ادمین) همیشه فقط همان placeholder ثابت را نگه می‌دارد؛ این سرویس روی یک
 * کپی از آن رشته HTML، در لحظه‌ی هر درخواست مهمان، عمل می‌کند — یعنی فرم
 * همیشه واقعاً hydrate می‌شود و فهرست پست‌ها همیشه واقعاً تازه است، حتی اگر
 * صفحه خودش مدت‌ها پیش ذخیره شده باشد.
 */
class DynamicWidgetResolver
{
    private const MAX_POSTS_COUNT = 24;

    public function resolve(string $html, Company $company): string
    {
        $html = $this->resolveContactForm($html, $company);
        $html = $this->resolveBlogPostList($html, $company);

        return $html;
    }

    /**
     * @livewire یک directive Blade واقعی است؛ Blade::render() آن را از صفر
     * کامپایل و اجرا می‌کند، پس خروجی یک کامپوننت واقعاً mount/hydrate‌شده
     * است (wire:id/checksum معتبر برای همین درخواست) — نه یک HTML خام کپی
     * از یک رندر قدیمی. عمداً همیشه Blade::render() صدا زده می‌شود، حتی اگر
     * صفحه چند بار این marker را داشته باشد؛ هر occurrence کامپوننت خودش را
     * با key متفاوت (uniqid) می‌گیرد تا wire:id تصادم نکند.
     */
    private function resolveContactForm(string $html, Company $company): string
    {
        $result = preg_replace_callback(
            '/<!--sb:contact_form:([A-Za-z0-9+\/=]+)-->.*?<!--\/sb:contact_form-->/s',
            function (array $matches) use ($company): string {
                $config = json_decode(base64_decode($matches[1], true) ?: '', true);
                $config = is_array($config) ? $config : [];
                $title = trim((string) ($config['title'] ?? ''));

                $titleHtml = $title !== '' ? '<h3 class="sb-widget-title sb-widget-contact-form-title">'.e($title).'</h3>' : '';

                $formHtml = Blade::render(
                    '@livewire(\'crm.public.contact-form\', ["companySlug" => $companySlug], key($key))',
                    ['companySlug' => $company->slug, 'key' => 'sb-contact-form-'.Str::random(12)]
                );

                return '<div class="sb-widget sb-widget-contact-form">'.$titleHtml.$formHtml.'</div>';
            },
            $html
        );

        return $result ?? $html;
    }

    private function resolveBlogPostList(string $html, Company $company): string
    {
        $result = preg_replace_callback(
            '/<!--sb:blog_post_list:([A-Za-z0-9+\/=]+)-->.*?<!--\/sb:blog_post_list-->/s',
            function (array $matches) use ($company): string {
                $config = json_decode(base64_decode($matches[1], true) ?: '', true);
                $config = is_array($config) ? $config : [];

                $count = (int) ($config['count'] ?? 3);
                $count = $count > 0 && $count <= self::MAX_POSTS_COUNT ? $count : 3;

                return $this->renderLivePostList($company, $count, trim((string) ($config['title'] ?? '')));
            },
            $html
        );

        return $result ?? $html;
    }

    private function renderLivePostList(Company $company, int $count, string $title): string
    {
        $posts = BlogPost::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->published()
            ->latest('published_at')
            ->take($count)
            ->get();

        $titleHtml = $title !== '' ? '<h3 class="sb-widget-title sb-widget-blog-post-list-title">'.e($title).'</h3>' : '';

        if ($posts->isEmpty()) {
            return '<div class="sb-widget sb-widget-blog-post-list">'
                .$titleHtml
                .'<div class="sb-blog-post-list-empty">هنوز هیچ پستی منتشر نشده است.</div>'
                .'</div>';
        }

        $cardsHtml = '';

        foreach ($posts as $post) {
            $url = route('public-blog.show', ['companySlug' => $company->slug, 'postSlug' => $post->slug]);
            $imageSrc = StorageUrl::resolve((string) ($post->featured_image_path ?? ''));
            $imageHtml = $imageSrc !== '' ? '<img src="'.e($imageSrc).'" alt="'.e($post->title).'">' : '';
            $excerpt = Str::limit(trim(strip_tags($post->content_html ?? '')), 120);
            $date = $post->published_at?->format('Y/m/d') ?? '';

            $cardsHtml .= '<a class="sb-blog-post-card" href="'.e($url).'">'
                .$imageHtml
                .'<div class="sb-blog-post-card-body">'
                .'<h4 class="sb-blog-post-card-title">'.e($post->title).'</h4>'
                .'<p class="sb-blog-post-card-excerpt">'.e($excerpt).'</p>'
                .'<span class="sb-blog-post-card-date">'.e($date).'</span>'
                .'</div>'
                .'</a>';
        }

        return '<div class="sb-widget sb-widget-blog-post-list">'
            .$titleHtml
            .'<div class="sb-blog-post-list-grid">'.$cardsHtml.'</div>'
            .'</div>';
    }
}
