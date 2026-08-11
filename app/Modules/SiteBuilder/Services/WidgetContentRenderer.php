<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use Illuminate\Support\Facades\Log;

/**
 * ساختار هر نود widget_tree:
 * ['id' => uuid, 'widget_key' => 'container'|'title'|..., 'values' => [...], 'children' => [...نود...]]
 * فقط widget_key های تعریف‌شده در WidgetKey رندر می‌شوند؛ هر کلید ناشناخته
 * لاگ و از خروجی حذف می‌شود (نه throw، نه silent skip بی‌اثر).
 */
class WidgetContentRenderer
{
    /**
     * دامنه‌های مجاز embed نقشه — فقط Google Maps، فقط مسیر embed واقعی.
     */
    private const MAP_ALLOWED_HOSTS = ['www.google.com', 'google.com'];

    private const MAP_ALLOWED_PATH_PREFIX = '/maps/embed';

    public function render(array $widgetTree): string
    {
        $html = $this->renderNodes($widgetTree);

        // content_html هرگز خالی/NULL نباشد، حتی برای دموی بدون هیچ ویجتی.
        return $html !== '' ? $html : '<div class="sb-page-empty"></div>';
    }

    private function renderNodes(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    private function renderNode(array $node): string
    {
        $key = WidgetKey::tryFrom($node['widget_key'] ?? '');

        if ($key === null) {
            Log::warning('SiteBuilder: widget_key ناشناخته در widget_tree رد شد.', [
                'widget_key' => $node['widget_key'] ?? null,
                'id' => $node['id'] ?? null,
            ]);

            return '';
        }

        $values = $node['values'] ?? [];

        return match ($key) {
            WidgetKey::Container => $this->renderContainer($node),
            WidgetKey::Title => $this->renderTitle($values),
            WidgetKey::Image => $this->renderImage($values),
            WidgetKey::Button => $this->renderButton($values),
            WidgetKey::Gallery => $this->renderGallery($values),
            WidgetKey::Testimonial => $this->renderTestimonial($values),
            WidgetKey::PricingTable => $this->renderPricingTable($values),
            WidgetKey::FaqAccordion => $this->renderFaqAccordion($values),
            WidgetKey::Map => $this->renderMap($values),
            WidgetKey::Video => $this->renderVideo($values),
            WidgetKey::Spacer => $this->renderSpacer($values),
            WidgetKey::HeaderNav => $this->renderHeaderNav($values),
            WidgetKey::Footer => $this->renderFooter($values),
        };
    }

    private function renderContainer(array $node): string
    {
        $children = $this->renderNodes($node['children'] ?? []);

        return '<div class="sb-widget sb-widget-container">'.$children.'</div>';
    }

    private function renderTitle(array $values): string
    {
        $text = (string) ($values['text'] ?? '');
        $level = (int) ($values['level'] ?? 2);
        $level = max(1, min(6, $level));

        return "<h{$level} class=\"sb-widget sb-widget-title\">".e($text)."</h{$level}>";
    }

    private function renderImage(array $values): string
    {
        $src = (string) ($values['image_path'] ?? $values['src'] ?? '');

        if ($src === '') {
            return '';
        }

        $alt = (string) ($values['alt'] ?? '');

        return '<img class="sb-widget sb-widget-image" src="'.e($src).'" alt="'.e($alt).'">';
    }

    private function renderButton(array $values): string
    {
        $label = (string) ($values['label'] ?? '');
        $url = (string) ($values['url'] ?? '');

        if ($label === '' || $url === '') {
            return '';
        }

        $style = ($values['style'] ?? 'primary') === 'outline' ? 'sb-btn-outline' : 'sb-btn-primary';

        return '<a class="sb-widget sb-widget-button '.$style.'" href="'.e($url).'">'.e($label).'</a>';
    }

    private function renderGallery(array $values): string
    {
        $images = $values['images'] ?? [];

        if (! is_array($images) || $images === []) {
            return '';
        }

        $items = '';

        foreach ($images as $image) {
            $src = (string) ($image['image_path'] ?? '');

            if ($src === '') {
                continue;
            }

            $caption = trim((string) ($image['caption'] ?? ''));
            $figcaption = $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '';

            $items .= '<figure class="sb-gallery-item"><img src="'.e($src).'" alt="'.e($caption).'">'.$figcaption.'</figure>';
        }

        if ($items === '') {
            return '';
        }

        return '<div class="sb-widget sb-widget-gallery">'.$items.'</div>';
    }

    private function renderTestimonial(array $values): string
    {
        $quote = trim((string) ($values['quote_text'] ?? ''));

        if ($quote === '') {
            return '';
        }

        $name = (string) ($values['customer_name'] ?? '');
        $title = (string) ($values['customer_title'] ?? '');
        $photo = (string) ($values['customer_photo'] ?? '');

        $photoHtml = $photo !== '' ? '<img class="sb-testimonial-photo" src="'.e($photo).'" alt="'.e($name).'">' : '';
        $titleHtml = $title !== '' ? '<span class="sb-testimonial-title">'.e($title).'</span>' : '';

        return '<div class="sb-widget sb-widget-testimonial">'
            .$photoHtml
            .'<blockquote>'.e($quote).'</blockquote>'
            .'<footer><span class="sb-testimonial-name">'.e($name).'</span>'.$titleHtml.'</footer>'
            .'</div>';
    }

    private function renderPricingTable(array $values): string
    {
        $planName = (string) ($values['plan_name'] ?? '');
        $price = (string) ($values['price'] ?? '');
        $features = $values['features'] ?? [];
        $ctaLabel = (string) ($values['cta_label'] ?? '');
        $ctaUrl = (string) ($values['cta_url'] ?? '');

        $featuresHtml = '';

        if (is_array($features)) {
            foreach ($features as $feature) {
                $feature = trim((string) $feature);

                if ($feature !== '') {
                    $featuresHtml .= '<li>'.e($feature).'</li>';
                }
            }
        }

        $ctaHtml = ($ctaLabel !== '' && $ctaUrl !== '')
            ? '<a class="sb-pricing-cta" href="'.e($ctaUrl).'">'.e($ctaLabel).'</a>'
            : '';

        return '<div class="sb-widget sb-widget-pricing-table">'
            .'<h3 class="sb-pricing-plan-name">'.e($planName).'</h3>'
            .'<div class="sb-pricing-price">'.e($price).'</div>'
            .'<ul class="sb-pricing-features">'.$featuresHtml.'</ul>'
            .$ctaHtml
            .'</div>';
    }

    private function renderFaqAccordion(array $values): string
    {
        $items = $values['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return '';
        }

        $itemsHtml = '';

        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $itemsHtml .= '<details class="sb-faq-item"><summary>'.e($question).'</summary><div>'.e($answer).'</div></details>';
        }

        if ($itemsHtml === '') {
            return '';
        }

        return '<div class="sb-widget sb-widget-faq-accordion">'.$itemsHtml.'</div>';
    }

    /**
     * فقط src هایی که هاست‌شان دقیقاً google.com/www.google.com و مسیرشان با
     * /maps/embed شروع می‌شود پذیرفته می‌شوند — هر دامنه دیگر رد و لاگ می‌شود.
     * این تنها دفاع در برابر XSS/clickjacking از طریق iframe src دلخواه است.
     */
    private function renderMap(array $values): string
    {
        $url = trim((string) ($values['embed_url'] ?? ''));

        if ($url === '') {
            return '';
        }

        if (! $this->isAllowedMapEmbedUrl($url)) {
            Log::warning('SiteBuilder: لینک embed نقشه با دامنه غیرمجاز رد شد.', ['url' => $url]);

            return '';
        }

        return '<div class="sb-widget sb-widget-map"><iframe src="'.e($url).'" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
    }

    private function isAllowedMapEmbedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        if (! in_array($parts['host'], self::MAP_ALLOWED_HOSTS, true)) {
            return false;
        }

        $path = $parts['path'] ?? '';

        return str_starts_with($path, self::MAP_ALLOWED_PATH_PREFIX);
    }

    /**
     * فقط لینک‌های واقعی یوتیوب/آپارات پذیرفته می‌شوند و به embed تبدیل
     * می‌شوند؛ هر دامنه دیگر رد و لاگ می‌شود — کاربر لینک معمولی watch را
     * پیست می‌کند، نه لینک embed خام.
     */
    private function renderVideo(array $values): string
    {
        $url = trim((string) ($values['video_url'] ?? ''));

        if ($url === '') {
            return '';
        }

        $embedUrl = $this->resolveVideoEmbedUrl($url);

        if ($embedUrl === null) {
            Log::warning('SiteBuilder: لینک ویدیو با دامنه غیرمجاز رد شد.', ['url' => $url]);

            return '';
        }

        return '<div class="sb-widget sb-widget-video"><iframe src="'.e($embedUrl).'" loading="lazy" allowfullscreen></iframe></div>';
    }

    private function resolveVideoEmbedUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $videoId = (string) ($query['v'] ?? '');

            if ($videoId === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $videoId)) {
                return null;
            }

            return 'https://www.youtube.com/embed/'.$videoId;
        }

        if ($host === 'youtu.be') {
            $videoId = ltrim($parts['path'] ?? '', '/');

            if ($videoId === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $videoId)) {
                return null;
            }

            return 'https://www.youtube.com/embed/'.$videoId;
        }

        if (in_array($host, ['aparat.com', 'www.aparat.com'], true)) {
            // لینک معمولی: https://www.aparat.com/v/xxxxx
            if (preg_match('#^/v/([A-Za-z0-9]+)#', $parts['path'] ?? '', $matches) === 1) {
                return 'https://www.aparat.com/video/video/embed/videohash/'.$matches[1].'/vt/frame';
            }

            return null;
        }

        return null;
    }

    private function renderSpacer(array $values): string
    {
        $height = (int) ($values['height_px'] ?? 0);
        $height = max(0, min(1000, $height));

        if ($height === 0) {
            return '';
        }

        return '<div class="sb-widget sb-widget-spacer" style="height:'.$height.'px"></div>';
    }

    private function renderHeaderNav(array $values): string
    {
        $links = $values['nav_links'] ?? [];

        if (! is_array($links) || $links === []) {
            return '';
        }

        $itemsHtml = '';

        foreach ($links as $link) {
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $itemsHtml .= '<a href="'.e($url).'">'.e($label).'</a>';
        }

        if ($itemsHtml === '') {
            return '';
        }

        return '<nav class="sb-widget sb-widget-header-nav">'.$itemsHtml.'</nav>';
    }

    private function renderFooter(array $values): string
    {
        $copyright = trim((string) ($values['copyright_text'] ?? ''));
        $contact = trim((string) ($values['contact_text'] ?? ''));
        $socialLinks = $values['social_links'] ?? [];

        $socialHtml = '';

        if (is_array($socialLinks)) {
            foreach ($socialLinks as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                if ($label === '' || $url === '') {
                    continue;
                }

                $socialHtml .= '<a href="'.e($url).'">'.e($label).'</a>';
            }
        }

        $copyrightHtml = $copyright !== '' ? '<div class="sb-footer-copyright">'.e($copyright).'</div>' : '';
        $contactHtml = $contact !== '' ? '<div class="sb-footer-contact">'.e($contact).'</div>' : '';
        $socialWrapperHtml = $socialHtml !== '' ? '<div class="sb-footer-social">'.$socialHtml.'</div>' : '';

        if ($copyrightHtml === '' && $contactHtml === '' && $socialWrapperHtml === '') {
            return '';
        }

        return '<footer class="sb-widget sb-widget-footer">'.$copyrightHtml.$contactHtml.$socialWrapperHtml.'</footer>';
    }
}
