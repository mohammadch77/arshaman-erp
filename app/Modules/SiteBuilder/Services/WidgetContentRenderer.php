<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ساختار هر نود widget_tree:
 * ['id' => uuid, 'widget_key' => 'container'|'title'|..., 'values' => [...], 'children' => [...نود...]]
 * فقط widget_key های تعریف‌شده در WidgetKey رندر می‌شوند؛ هر کلید ناشناخته
 * لاگ و از خروجی حذف می‌شود (نه throw، نه silent skip بی‌اثر).
 *
 * widget_tree می‌تواند یک کلید ریشه اختیاری 'theme' هم داشته باشد (بدون نیاز
 * به ستون/جدول جدید — فقط یک کلید دیگر در همان JSON):
 * ['theme' => ['primary_color' => '#2563EB', 'font_family' => "'Vazirmatn', sans-serif"], 0 => [...node...], ...]
 * این کلید هرگز به‌عنوان یک نود رندر نمی‌شود؛ render() آن را قبل از پیمایش
 * جدا می‌کند و به‌صورت CSS custom property روی wrapper بیرونی صفحه می‌گذارد.
 */
class WidgetContentRenderer
{
    /**
     * دامنه‌های مجاز embed نقشه — فقط Google Maps، فقط مسیر embed واقعی.
     */
    private const MAP_ALLOWED_HOSTS = ['www.google.com', 'google.com'];

    private const MAP_ALLOWED_PATH_PREFIX = '/maps/embed';

    private const DEFAULT_PRIMARY_COLOR = '#2563EB';

    private const DEFAULT_FONT_FAMILY = "'Vazirmatn', sans-serif";

    public function render(array $widgetTree): string
    {
        $theme = $this->extractTheme($widgetTree);
        unset($widgetTree['theme']);

        $html = $this->renderNodes($widgetTree);

        // content_html هرگز خالی/NULL نباشد، حتی برای دموی بدون هیچ ویجتی.
        if ($html === '') {
            $html = '<div class="sb-page-empty"></div>';
        }

        return '<div class="sb-page" style="'.e($theme).'">'
            .'<style>'.$this->baseStyles().'</style>'
            .$html
            .'</div>';
    }

    /**
     * مقادیر theme را از ریشه widget_tree می‌خواند و به یک رشته inline style
     * امن تبدیل می‌کند. رنگ باید دقیقاً هگز ۶ یا ۳ رقمی باشد، فونت فقط از
     * حروف/فاصله/کاما/آپاستروف/خط تیره تشکیل شده باشد — وگرنه پیش‌فرض
     * جایگزین می‌شود. دلیل: این مقدار مستقیم داخل یک HTML attribute می‌رود،
     * پس تزریق دلخواه اینجا دقیقاً همان کلاس خطر XSS ویجت map/video است.
     */
    private function extractTheme(array $widgetTree): string
    {
        $theme = is_array($widgetTree['theme'] ?? null) ? $widgetTree['theme'] : [];

        $primaryColor = (string) ($theme['primary_color'] ?? '');
        if (! preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $primaryColor)) {
            $primaryColor = self::DEFAULT_PRIMARY_COLOR;
        }

        $fontFamily = (string) ($theme['font_family'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9 ,\'\-]+$/', $fontFamily)) {
            $fontFamily = self::DEFAULT_FONT_FAMILY;
        }

        return '--sb-primary-color:'.$primaryColor.';--sb-font-family:'.$fontFamily.';';
    }

    /**
     * پوسته CSS مشترک هر ۱۳ ویجت — هم در iframe پیش‌نمایش ادمین (ذخیره‌شده
     * یا هنوز ذخیره‌نشده) هم در content_html نهایی صفحه عمومی استفاده می‌شود.
     * فقط از دو متغیر سطح صفحه (--sb-primary-color/--sb-font-family) رنگ و
     * فونت می‌گیرد — هیچ رنگ دیگری اینجا هاردکد نیست تا هر دمو با یک پالت
     * کاملاً متفاوت هم از همین یک فایل CSS درست دیده شود.
     */
    public function baseStyles(): string
    {
        return <<<'CSS'
        .sb-page{font-family:var(--sb-font-family);color:#1f2430;line-height:1.8;}
        .sb-page *{box-sizing:border-box;}
        .sb-widget{display:block;}
        .sb-widget-container{margin:0 auto;padding:1.5rem 1rem;max-width:72rem;}
        .sb-widget-title{margin:0 0 .75rem;font-weight:700;letter-spacing:-.01em;}
        .sb-widget-title:is(h1){font-size:2.25rem;line-height:1.25;}
        .sb-widget-title:is(h2){font-size:1.75rem;line-height:1.3;}
        .sb-widget-title:is(h3){font-size:1.375rem;}
        .sb-widget-title:is(h4){font-size:1.125rem;font-weight:600;color:#4b5261;}
        .sb-widget-title:is(h5,h6){font-size:.95rem;font-weight:600;color:#6b7280;}
        .sb-widget-image{display:block;max-width:100%;height:auto;border-radius:.75rem;}
        .sb-widget-button{display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.4rem;border-radius:.6rem;font-weight:600;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,opacity .15s ease;}
        .sb-btn-primary{background:var(--sb-primary-color);color:#fff;box-shadow:0 6px 16px -6px color-mix(in srgb, var(--sb-primary-color) 60%, transparent);}
        .sb-btn-primary:hover{transform:translateY(-1px);box-shadow:0 10px 20px -6px color-mix(in srgb, var(--sb-primary-color) 65%, transparent);}
        .sb-btn-outline{background:transparent;color:var(--sb-primary-color);border:1.5px solid var(--sb-primary-color);}
        .sb-btn-outline:hover{background:color-mix(in srgb, var(--sb-primary-color) 10%, transparent);}
        .sb-widget-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:1rem 0;}
        .sb-gallery-item{margin:0;background:#f4f5f7;border-radius:.75rem;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,.08);}
        .sb-gallery-item img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;}
        .sb-gallery-item figcaption{padding:.6rem .75rem;font-size:.85rem;color:#4b5261;}
        .sb-widget-testimonial{margin:1rem 0;padding:1.5rem;background:#f8f9fb;border-radius:1rem;border-inline-start:4px solid var(--sb-primary-color);}
        .sb-widget-testimonial blockquote{margin:0 0 1rem;font-size:1.1rem;color:#1f2430;}
        .sb-testimonial-photo{width:2.75rem;height:2.75rem;border-radius:9999px;object-fit:cover;float:inline-end;margin-inline-start:.75rem;}
        .sb-widget-testimonial footer{display:flex;gap:.5rem;align-items:baseline;font-size:.9rem;}
        .sb-testimonial-name{font-weight:700;}
        .sb-testimonial-title{color:#6b7280;}
        .sb-widget-pricing-table{margin:1rem 0;padding:1.75rem;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 4px 14px rgba(15,23,42,.06);text-align:center;}
        .sb-pricing-plan-name{margin:0 0 .5rem;font-size:1.15rem;}
        .sb-pricing-price{font-size:1.6rem;font-weight:800;color:var(--sb-primary-color);margin-bottom:1rem;}
        .sb-pricing-features{list-style:none;margin:0 0 1.25rem;padding:0;display:flex;flex-direction:column;gap:.5rem;color:#4b5261;}
        .sb-pricing-features li{padding-inline-start:1.4rem;position:relative;}
        .sb-pricing-features li::before{content:'✓';position:absolute;inset-inline-start:0;color:var(--sb-primary-color);font-weight:700;}
        .sb-pricing-cta{display:inline-block;padding:.6rem 1.5rem;border-radius:.6rem;background:var(--sb-primary-color);color:#fff;text-decoration:none;font-weight:600;}
        .sb-widget-faq-accordion{margin:1rem 0;display:flex;flex-direction:column;gap:.6rem;}
        .sb-faq-item{padding:1rem 1.25rem;background:#f8f9fb;border-radius:.75rem;border:1px solid #edeef1;}
        .sb-faq-item summary{cursor:pointer;font-weight:600;}
        .sb-faq-item div{margin-top:.6rem;color:#4b5261;}
        .sb-widget-map,.sb-widget-video{position:relative;width:100%;aspect-ratio:16/9;margin:1rem 0;border-radius:1rem;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.08);}
        .sb-widget-map iframe,.sb-widget-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}
        .sb-widget-header-nav{display:flex;flex-wrap:wrap;gap:1.5rem;padding:1rem 1.5rem;align-items:center;}
        .sb-widget-header-nav a{color:#1f2430;text-decoration:none;font-weight:600;}
        .sb-widget-header-nav a:hover{color:var(--sb-primary-color);}
        .sb-widget-footer{margin-top:2rem;padding:2rem 1.5rem;background:#f4f5f7;display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;font-size:.9rem;color:#4b5261;}
        .sb-footer-social{display:flex;gap:1rem;}
        .sb-footer-social a{color:var(--sb-primary-color);text-decoration:none;font-weight:600;}
        .sb-page-empty{padding:3rem;text-align:center;color:#9ca3af;}
        CSS;
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
        $src = $this->resolveImageUrl((string) ($values['image_path'] ?? $values['src'] ?? ''));

        if ($src === '') {
            return '';
        }

        $alt = (string) ($values['alt'] ?? '');

        return '<img class="sb-widget sb-widget-image" src="'.e($src).'" alt="'.e($alt).'">';
    }

    /**
     * مقدار ذخیره‌شده در widget_tree برای یک فیلد تصویر می‌تواند سه شکل داشته
     * باشد: (۱) مسیر نسبی روی دیسک public (مثلاً 'sitebuilder/images/x.jpg' —
     * از PageContentEditor::mergeUploadedFiles ذخیره واقعی)، (۲) یک URL کامل
     * یا root-relative از قبل حل‌شده (مثلاً temporaryUrl() یک آپلود هنوز
     * ذخیره‌نشده در پیش‌نمایش زنده)، یا (۳) خالی. فقط حالت اول نیاز به
     * Storage::url() دارد — بدون این تفکیک، هر src خام مستقیم رندر می‌شد و
     * نسبت به آدرس صفحه‌ی جاری (نه ریشه سایت) حل می‌شد، هم در iframe
     * پیش‌نمایش هم در content_html نهایی صفحه عمومی.
     */
    private function resolveImageUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(https?://|//|/)#i', $path) === 1) {
            return $path;
        }

        return Storage::disk('public')->url($path);
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
            $src = $this->resolveImageUrl((string) ($image['image_path'] ?? ''));

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
        $photo = $this->resolveImageUrl((string) ($values['customer_photo'] ?? ''));

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
