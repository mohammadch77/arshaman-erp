<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\Core\Models\Company;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\SiteSetting;
use App\Modules\SiteBuilder\Support\StorageUrl;
use Illuminate\Support\Facades\Log;

/**
 * ساختار هر نود widget_tree:
 * ['id' => uuid, 'widget_key' => 'container'|'title'|..., 'values' => [...], 'children' => [...نود...]]
 * فقط widget_key های تعریف‌شده در WidgetKey رندر می‌شوند؛ هر کلید ناشناخته
 * لاگ و از خروجی حذف می‌شود (نه throw، نه silent skip بی‌اثر).
 *
 * widget_tree می‌تواند یک کلید ریشه اختیاری 'theme' هم داشته باشد (بدون نیاز
 * به ستون/جدول جدید — فقط یک کلید دیگر در همان JSON):
 * ['theme' => [
 *     'primary_color' => '#2563EB', 'secondary_color' => '#0F172A',
 *     'font_family' => "'Vazirmatn', sans-serif",   // فونت بدنه
 *     'heading_font' => "'Tajawal', sans-serif",     // اختیاری — نبود آن یعنی همان فونت بدنه
 *     'radius' => 'sharp'|'soft'|'pill',             // اختیاری — پیش‌فرض soft
 *     'density' => 'compact'|'comfortable'|'airy',   // اختیاری — پیش‌فرض comfortable
 * ], 0 => [...node...], ...]
 * این کلید هرگز به‌عنوان یک نود رندر نمی‌شود؛ render() آن را قبل از پیمایش
 * جدا می‌کند و به‌صورت CSS custom property روی wrapper بیرونی صفحه می‌گذارد.
 * فونت‌های واقعاً فارسی‌خوان مجاز (لود شده در layouts/public-site.blade.php):
 * Vazirmatn, Tajawal, Cairo, 'Markazi Text', 'Noto Naskh Arabic', Lalezar.
 */
class WidgetContentRenderer
{
    /**
     * دامنه‌های مجاز embed نقشه — فقط Google Maps، فقط مسیر embed واقعی.
     */
    private const MAP_ALLOWED_HOSTS = ['www.google.com', 'google.com'];

    private const MAP_ALLOWED_PATH_PREFIX = '/maps/embed';

    private const DEFAULT_PRIMARY_COLOR = '#2563EB';

    private const DEFAULT_SECONDARY_COLOR = '#0F172A';

    private const DEFAULT_FONT_FAMILY = "'Vazirmatn', sans-serif";

    private const DEFAULT_HEADING_FONT = "'Vazirmatn', sans-serif";

    /**
     * سه سبک گردی گوشه از پیش‌تعریف‌شده — نه یک عدد آزاد، چون این مقدار
     * مستقیم در CSS تزریق می‌شود؛ محدودکردن به یک enum بسته یعنی هیچ رشته‌ی
     * دیگری (از جمله چیزی شبیه به کد مخرب) هرگز نمی‌تواند از این مسیر رد شود.
     */
    private const RADIUS_SCALES = [
        'sharp' => ['sm' => '.25rem', 'md' => '.375rem', 'lg' => '.5rem'],
        'soft' => ['sm' => '.5rem', 'md' => '.9rem', 'lg' => '1.25rem'],
        'pill' => ['sm' => '.75rem', 'md' => '1.25rem', 'lg' => '2.5rem'],
    ];

    private const DENSITY_SCALES = [
        'compact' => ['section' => '2.5rem 1rem', 'gap' => '.75rem', 'card' => '1.25rem'],
        'comfortable' => ['section' => '4rem 1.25rem', 'gap' => '1.25rem', 'card' => '1.75rem'],
        'airy' => ['section' => '6rem 1.5rem', 'gap' => '2rem', 'card' => '2.25rem'],
    ];

    /**
     * $company فقط برای حل کردن لینک‌های ویجت header_nav لازم است (نگاه کن
     * renderHeaderNav) — چون فقط PublicSiteController آن را برای یک شرکت
     * مشخص واقعاً می‌داند، در بقیه call site ها (پیش‌نمایش/ذخیره محتوای خودِ
     * صفحه، نه لایوت هدر/فوتر) اختیاری و null می‌ماند.
     */
    public function render(array $widgetTree, ?Company $company = null): string
    {
        $theme = $this->extractTheme($widgetTree);
        unset($widgetTree['theme']);

        $html = $this->renderNodes($widgetTree, $company);

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
     * حروف/فاصله/کاما/آپاستروف/خط تیره تشکیل شده باشد، و radius/density فقط
     * یکی از مقادیر ثابت enum بالا — وگرنه پیش‌فرض جایگزین می‌شود. دلیل: این
     * مقدار مستقیم داخل یک HTML attribute می‌رود، پس تزریق دلخواه اینجا
     * دقیقاً همان کلاس خطر XSS ویجت map/video است.
     *
     * heading_font اختیاری است و اگر داده نشود از همان font_family (بدنه)
     * استفاده می‌شود — بیشتر دموها فقط یک فونت لازم دارند، فقط دموهایی که
     * عمداً تیتر/بدنه را از هم متمایز می‌کنند این کلید را جدا ست می‌کنند.
     */
    private function extractTheme(array $widgetTree): string
    {
        $theme = is_array($widgetTree['theme'] ?? null) ? $widgetTree['theme'] : [];

        $primaryColor = $this->validColor((string) ($theme['primary_color'] ?? ''), self::DEFAULT_PRIMARY_COLOR);
        $secondaryColor = $this->validColor((string) ($theme['secondary_color'] ?? ''), self::DEFAULT_SECONDARY_COLOR);

        $bodyFont = $this->validFontFamily((string) ($theme['font_family'] ?? ''), self::DEFAULT_FONT_FAMILY);
        $headingFontRaw = (string) ($theme['heading_font'] ?? '');
        $headingFont = $headingFontRaw !== '' ? $this->validFontFamily($headingFontRaw, $bodyFont) : $bodyFont;

        $radius = self::RADIUS_SCALES[$theme['radius'] ?? ''] ?? self::RADIUS_SCALES['soft'];
        $density = self::DENSITY_SCALES[$theme['density'] ?? ''] ?? self::DENSITY_SCALES['comfortable'];

        return '--sb-primary-color:'.$primaryColor.';'
            .'--sb-secondary-color:'.$secondaryColor.';'
            .'--sb-font-family:'.$bodyFont.';'
            .'--sb-heading-font:'.$headingFont.';'
            .'--sb-radius-sm:'.$radius['sm'].';'
            .'--sb-radius-md:'.$radius['md'].';'
            .'--sb-radius-lg:'.$radius['lg'].';'
            .'--sb-space-section:'.$density['section'].';'
            .'--sb-space-gap:'.$density['gap'].';'
            .'--sb-space-card:'.$density['card'].';';
    }

    private function validColor(string $value, string $default): string
    {
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) === 1 ? $value : $default;
    }

    private function validFontFamily(string $value, string $default): string
    {
        return preg_match('/^[a-zA-Z0-9 ,\'\-]+$/', $value) === 1 ? $value : $default;
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
        .sb-page{font-family:var(--sb-font-family);color:#20242c;line-height:1.8;-webkit-font-smoothing:antialiased;}
        .sb-page *{box-sizing:border-box;}
        .sb-widget{display:block;}
        .sb-widget-container{margin:0 auto;padding:var(--sb-space-section);max-width:72rem;display:flex;flex-direction:column;gap:var(--sb-space-gap);}
        .sb-widget-title{margin:0 0 .75rem;font-family:var(--sb-heading-font);font-weight:700;letter-spacing:-.01em;line-height:1.35;}
        .sb-widget-title:is(h1){font-size:clamp(2rem,4vw,3rem);line-height:1.2;font-weight:800;}
        .sb-widget-title:is(h2){font-size:clamp(1.5rem,2.6vw,2.1rem);}
        .sb-widget-title:is(h3){font-size:1.4rem;}
        .sb-widget-title:is(h4){font-size:1.125rem;font-weight:600;color:#525a68;}
        .sb-widget-title:is(h5,h6){font-size:.95rem;font-weight:600;color:#6b7280;letter-spacing:.02em;}
        .sb-widget-image{display:block;max-width:100%;height:auto;border-radius:var(--sb-radius-lg);}
        .sb-widget-button{display:inline-flex;align-items:center;gap:.5rem;padding:.8rem 1.6rem;border-radius:var(--sb-radius-md);font-weight:600;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,opacity .18s ease;}
        .sb-btn-primary{background:linear-gradient(135deg, var(--sb-primary-color), color-mix(in srgb, var(--sb-primary-color) 75%, var(--sb-secondary-color)));color:#fff;box-shadow:0 10px 24px -10px color-mix(in srgb, var(--sb-primary-color) 65%, transparent);}
        .sb-btn-primary:hover{transform:translateY(-2px);box-shadow:0 16px 28px -10px color-mix(in srgb, var(--sb-primary-color) 70%, transparent);}
        .sb-btn-outline{background:transparent;color:var(--sb-primary-color);border:1.5px solid var(--sb-primary-color);}
        .sb-btn-outline:hover{background:color-mix(in srgb, var(--sb-primary-color) 10%, transparent);transform:translateY(-1px);}
        .sb-widget-gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:var(--sb-space-gap);margin:.5rem 0;}
        .sb-gallery-item{margin:0;background:#fff;border-radius:var(--sb-radius-lg);overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 20px -12px rgba(15,23,42,.18);transition:transform .2s ease,box-shadow .2s ease;}
        .sb-gallery-item:hover{transform:translateY(-3px);box-shadow:0 1px 2px rgba(15,23,42,.05),0 16px 28px -14px rgba(15,23,42,.28);}
        .sb-gallery-item img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;}
        .sb-gallery-item figcaption{padding:.75rem .9rem;font-size:.85rem;color:#525a68;font-weight:500;}
        .sb-widget-testimonial{margin:.5rem 0;padding:var(--sb-space-card);background:color-mix(in srgb, var(--sb-primary-color) 5%, #fff);border-radius:var(--sb-radius-lg);border-inline-start:4px solid var(--sb-primary-color);}
        .sb-widget-testimonial blockquote{margin:0 0 1rem;font-size:1.15rem;color:#20242c;line-height:1.7;}
        .sb-testimonial-photo{width:3rem;height:3rem;border-radius:9999px;object-fit:cover;float:inline-end;margin-inline-start:.75rem;box-shadow:0 0 0 3px #fff,0 0 0 4px color-mix(in srgb, var(--sb-primary-color) 30%, transparent);}
        .sb-widget-testimonial footer{display:flex;gap:.5rem;align-items:baseline;font-size:.9rem;}
        .sb-testimonial-name{font-weight:700;}
        .sb-testimonial-title{color:#6b7280;}
        .sb-widget-pricing-table{margin:.5rem 0;padding:var(--sb-space-card);border-radius:var(--sb-radius-lg);border:1px solid #e7e9ee;box-shadow:0 1px 2px rgba(15,23,42,.04),0 12px 24px -16px rgba(15,23,42,.16);text-align:center;transition:transform .2s ease,box-shadow .2s ease;}
        .sb-widget-pricing-table:hover{transform:translateY(-3px);}
        .sb-pricing-plan-name{margin:0 0 .5rem;font-family:var(--sb-heading-font);font-size:1.2rem;font-weight:700;}
        .sb-pricing-price{font-size:1.75rem;font-weight:800;color:var(--sb-primary-color);margin-bottom:1.1rem;}
        .sb-pricing-features{list-style:none;margin:0 0 1.4rem;padding:0;display:flex;flex-direction:column;gap:.6rem;color:#525a68;}
        .sb-pricing-features li{padding-inline-start:1.5rem;position:relative;text-align:start;}
        .sb-pricing-features li::before{content:'✓';position:absolute;inset-inline-start:0;color:var(--sb-primary-color);font-weight:700;}
        .sb-pricing-cta{display:inline-block;padding:.7rem 1.6rem;border-radius:var(--sb-radius-md);background:var(--sb-primary-color);color:#fff;text-decoration:none;font-weight:600;transition:transform .18s ease;}
        .sb-pricing-cta:hover{transform:translateY(-2px);}
        .sb-widget-faq-accordion{margin:.5rem 0;display:flex;flex-direction:column;gap:.6rem;}
        .sb-faq-item{padding:1.1rem 1.4rem;background:#fafafb;border-radius:var(--sb-radius-md);border:1px solid #edeef1;transition:border-color .15s ease;}
        .sb-faq-item[open]{border-color:color-mix(in srgb, var(--sb-primary-color) 35%, transparent);}
        .sb-faq-item summary{cursor:pointer;font-weight:600;list-style:none;}
        .sb-faq-item summary::-webkit-details-marker{display:none;}
        .sb-faq-item summary::after{content:'+';float:inline-end;color:var(--sb-primary-color);font-weight:700;}
        .sb-faq-item[open] summary::after{content:'−';}
        .sb-faq-item div{margin-top:.7rem;color:#525a68;line-height:1.75;}
        .sb-widget-map,.sb-widget-video{position:relative;width:100%;aspect-ratio:16/9;margin:.5rem 0;border-radius:var(--sb-radius-lg);overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04),0 12px 24px -16px rgba(15,23,42,.2);}
        .sb-widget-map iframe,.sb-widget-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}
        .sb-widget-video-unavailable{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;height:100%;width:100%;background:linear-gradient(135deg, color-mix(in srgb, var(--sb-primary-color) 12%, #fff), color-mix(in srgb, var(--sb-secondary-color) 8%, #fff));color:#6b7280;font-size:.9rem;font-weight:600;text-align:center;padding:1rem;}
        .sb-widget-header-nav{display:flex;flex-wrap:wrap;gap:1.75rem;padding:1.1rem 1.5rem;align-items:center;justify-content:space-between;}
        .sb-header-logo-link{display:inline-flex;align-items:center;flex-shrink:0;text-decoration:none;}
        .sb-header-logo{display:block;max-height:2.75rem;width:auto;object-fit:contain;}
        .sb-header-logo-text{font-family:var(--sb-heading-font);font-size:1.25rem;font-weight:800;color:#20242c;}
        .sb-header-nav-links{display:flex;flex-wrap:wrap;gap:1.75rem;align-items:center;}
        .sb-header-nav-links a{position:relative;color:#20242c;text-decoration:none;font-weight:600;padding-bottom:.3rem;transition:color .15s ease;}
        .sb-header-nav-links a::after{content:'';position:absolute;inset-inline-start:0;bottom:0;width:0;height:2px;background:var(--sb-primary-color);transition:width .2s ease;}
        .sb-header-nav-links a:hover{color:var(--sb-primary-color);}
        .sb-header-nav-links a:hover::after{width:100%;}
        .sb-widget-footer{margin-top:var(--sb-space-gap);padding:2.5rem 1.5rem;background:var(--sb-secondary-color);color:#fff;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1.25rem;font-size:.9rem;}
        .sb-widget-footer a{color:inherit;}
        .sb-footer-copyright{opacity:.75;}
        .sb-footer-contact{opacity:.85;}
        .sb-footer-social{display:flex;gap:1.25rem;}
        .sb-footer-social a{color:#fff;text-decoration:none;font-weight:600;opacity:.85;transition:opacity .15s ease;}
        .sb-footer-social a:hover{opacity:1;text-decoration:underline;}
        .sb-page-empty{padding:4rem 1.5rem;text-align:center;color:#9ca3af;font-size:.95rem;}
        .sb-widget-dynamic-placeholder{padding:2.5rem 1.5rem;text-align:center;color:#6b7280;font-size:.9rem;font-weight:600;border:1.5px dashed color-mix(in srgb, var(--sb-primary-color) 40%, #d1d5db);border-radius:var(--sb-radius-lg);background:color-mix(in srgb, var(--sb-primary-color) 4%, #fff);}
        .sb-widget-contact-form{margin:.5rem 0;}
        .sb-widget-blog-post-list{margin:.5rem 0;}
        .sb-blog-post-list-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:var(--sb-space-gap);}
        .sb-blog-post-card{display:flex;flex-direction:column;background:#fff;border-radius:var(--sb-radius-lg);overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 20px -12px rgba(15,23,42,.18);text-decoration:none;color:inherit;transition:transform .2s ease,box-shadow .2s ease;}
        .sb-blog-post-card:hover{transform:translateY(-3px);box-shadow:0 1px 2px rgba(15,23,42,.05),0 16px 28px -14px rgba(15,23,42,.28);}
        .sb-blog-post-card img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;}
        .sb-blog-post-card-body{padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.5rem;flex:1;}
        .sb-blog-post-card-title{margin:0;font-family:var(--sb-heading-font);font-size:1.05rem;font-weight:700;color:#20242c;}
        .sb-blog-post-card-excerpt{margin:0;font-size:.88rem;color:#525a68;line-height:1.7;flex:1;}
        .sb-blog-post-card-date{font-size:.78rem;color:#9ca3af;font-weight:600;}
        .sb-blog-post-list-empty{padding:2.5rem 1.5rem;text-align:center;color:#9ca3af;font-size:.9rem;border:1px dashed #e7e9ee;border-radius:var(--sb-radius-lg);}
        @media (max-width: 640px){
            .sb-widget-header-nav{gap:1rem;padding:.85rem 1rem;justify-content:center;}
            .sb-header-nav-links{gap:1rem;justify-content:center;}
            .sb-header-logo{max-height:2.25rem;}
            .sb-widget-footer{flex-direction:column;text-align:center;}
        }
        CSS;
    }

    private function renderNodes(array $nodes, ?Company $company): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->renderNode($node, $company);
        }

        return $html;
    }

    private function renderNode(array $node, ?Company $company): string
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
            WidgetKey::Container => $this->renderContainer($node, $company),
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
            WidgetKey::HeaderNav => $this->renderHeaderNav($values, $company),
            WidgetKey::Footer => $this->renderFooter($values),
            WidgetKey::ContactForm => $this->renderContactForm($values),
            WidgetKey::BlogPostList => $this->renderBlogPostList($values),
        };
    }

    private function renderContainer(array $node, ?Company $company): string
    {
        $children = $this->renderNodes($node['children'] ?? [], $company);

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
     *
     * عمداً root-relative ("/storage/...") ساخته می‌شود، نه
     * Storage::disk('public')->url() خام — آن متد آدرس کامل را از روی
     * APP_URL می‌سازد (طبق config/filesystems.php)، که این‌جا در محیط
     * توسعه با میزبان واقعی سرو صفحه (مثلاً 127.0.0.1:8000 وقتی vhost
     * دامنه Apache از کار افتاده) یکی نیست؛ نتیجه‌اش src ای بود که به
     * دامنه‌ای غیرقابل‌دسترس اشاره می‌کرد و عکس در تمام شرکت‌ها/صفحات
     * شکسته دیده می‌شد. مسیر root-relative مستقل از میزبان، همیشه نسبت
     * به همان origin ای حل می‌شود که خودِ صفحه از آن سرو شده — دقیقاً
     * چیزی که کامنت بالا از ابتدا قصدش بود.
     */
    private function resolveImageUrl(string $path): string
    {
        return StorageUrl::resolve($path);
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

            // به‌جای حذف کامل ویجت (که یک شکاف خالی توضیح‌ناپذیر در چیدمان
            // می‌گذاشت)، یک جایگزین بصری شفاف — بدون هیچ iframe/محتوای خارجی.
            return '<div class="sb-widget sb-widget-video"><div class="sb-widget-video-unavailable">'
                .'این ویدیو در دسترس نیست</div></div>';
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

    /**
     * هر آیتم منو به یک category_key اشاره می‌کند، نه یک URL آزاد (نگاه کن
     * SiteBuilderWidgetsExpansionSeeder برای دلیل). اگر $company داده نشده
     * باشد (پیش‌نمایش ادمین یک صفحه، نه رندر واقعی هدر/فوتر عمومی)، یا اگر
     * صفحه‌ی مقصد برای این شرکت وجود/انتشار نداشته باشد، آیتم بی‌صدا حذف
     * می‌شود — هرگز یک لینک مرده (href خالی/نامعتبر) رندر نمی‌شود.
     *
     * لوگو (site_settings.logo_path همان شرکت) کنار منو نمایش داده می‌شود،
     * فقط وقتی show_logo صراحتاً false نباشد (کلید غایب در widget_tree های
     * قدیمی‌تر = true، طبق پیش‌فرض فیلد در کاتالوگ ویجت). بدون $company
     * (پیش‌نمایش ادمین) لوگو اصلاً رندر نمی‌شود — دقیقاً همان رفتار لینک‌های
     * منو در نبود $company.
     */
    private function renderHeaderNav(array $values, ?Company $company): string
    {
        $links = $values['nav_links'] ?? [];
        $itemsHtml = '';

        if (is_array($links)) {
            foreach ($links as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $categoryKey = trim((string) ($link['category_key'] ?? ''));

                if ($label === '' || $categoryKey === '') {
                    continue;
                }

                $href = $this->resolveNavHref($categoryKey, $company);

                if ($href === null) {
                    continue;
                }

                $itemsHtml .= '<a href="'.e($href).'">'.e($label).'</a>';
            }
        }

        $showLogo = ($values['show_logo'] ?? true) !== false;
        $logoHtml = $showLogo ? $this->renderHeaderLogo($company) : '';
        $navLinksHtml = $itemsHtml !== '' ? '<nav class="sb-header-nav-links">'.$itemsHtml.'</nav>' : '';

        if ($logoHtml === '' && $navLinksHtml === '') {
            return '';
        }

        return '<div class="sb-widget sb-widget-header-nav">'.$logoHtml.$navLinksHtml.'</div>';
    }

    /**
     * لوگوی site_settings.logo_path همان شرکت، با همان الگوی root-relative
     * resolveImageUrl/StorageUrl بقیه ویجت‌ها — نه یک آدرس کامل بر پایه
     * APP_URL هاردکد‌شده (همان باگ سراسری قبلی تصاویر شکسته که بند ۹.۱۲
     * CLAUDE.md درباره‌اش هشدار می‌دهد، این‌بار برای مسیر جدید لوگوی هدر).
     * اگر لوگو تنظیم نشده باشد، به‌جای شکستن layout، نام سایت
     * (site_settings.site_title) جایگزین متنی می‌شود؛ اگر آن هم خالی بود،
     * هیچ‌چیز رندر نمی‌شود (نه یک عنصر خالی).
     */
    private function renderHeaderLogo(?Company $company): string
    {
        if ($company === null) {
            return '';
        }

        $siteSetting = SiteSetting::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->first();

        $homeUrl = route('public-site.home', ['companySlug' => $company->slug]);
        $logoPath = trim((string) ($siteSetting->logo_path ?? ''));

        if ($logoPath !== '') {
            $src = $this->resolveImageUrl($logoPath);

            if ($src !== '') {
                $alt = trim((string) ($siteSetting->site_title ?? $company->name ?? ''));

                return '<a href="'.e($homeUrl).'" class="sb-header-logo-link">'
                    .'<img class="sb-header-logo" src="'.e($src).'" alt="'.e($alt).'"></a>';
            }
        }

        $siteTitle = trim((string) ($siteSetting->site_title ?? ''));

        if ($siteTitle !== '') {
            return '<a href="'.e($homeUrl).'" class="sb-header-logo-link sb-header-logo-text">'.e($siteTitle).'</a>';
        }

        return '';
    }

    private function resolveNavHref(string $categoryKey, ?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $category = PageCategoryKey::tryFrom($categoryKey);

        if ($category === null) {
            return null;
        }

        if ($category === PageCategoryKey::Home) {
            return route('public-site.home', ['companySlug' => $company->slug]);
        }

        $page = Page::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->whereHas('demo.category', fn ($query) => $query->where('category_key', $category->value))
            ->published()
            ->oldest()
            ->first();

        if ($page === null) {
            return null;
        }

        return route('public-site.show', ['companySlug' => $company->slug, 'pageSlug' => $page->slug]);
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

    /**
     * برخلاف بقیه ویجت‌ها، contact_form/blog_post_list در این متد به HTML
     * نهایی رندر نمی‌شوند — این‌جا فقط یک placeholder ثابت (متن راهنما برای
     * پیش‌نمایش ادمین) داخل یک جفت کامنت HTML مشخص تولید می‌شود. کامنت‌ها
     * marker ای هستند که فقط App\Modules\SiteBuilder\Services\DynamicWidgetResolver
     * می‌شناسد و در لحظه‌ی رندر صفحه‌ی عمومی (نه در content_html ذخیره‌شده،
     * نه در پیش‌نمایش ادمین) با محتوای واقعی/زنده جایگزین می‌کند — یکی فرم
     * تماس واقعی Livewire (نیاز به session/hydrate واقعی که در یک snapshot
     * ذخیره‌شده معنا ندارد)، دیگری کوئری زنده‌ی پست‌های منتشرشده وبلاگ (که
     * باید همیشه تازه باشد، نه فریز‌شده در لحظه‌ی آخرین ذخیره صفحه).
     */
    /**
     * عنوان بخش هم (مثل blog_post_list) به‌صورت base64(json) داخل خودِ کامنت
     * marker کد می‌شود — DynamicWidgetResolver کل بلوک بین شروع/پایان کامنت
     * را با کامپوننت واقعی جایگزین می‌کند، پس هر چیزی که فقط داخل HTML بین
     * دو کامنت باشد (نه در خودِ کامنت) موقع resolve از بین می‌رود.
     */
    private function renderContactForm(array $values): string
    {
        $title = trim((string) ($values['section_title'] ?? ''));
        $titleHtml = $title !== '' ? '<h3 class="sb-widget-title sb-widget-contact-form-title">'.e($title).'</h3>' : '';

        $config = base64_encode(json_encode(['title' => $title], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return '<!--sb:contact_form:'.$config.'-->'
            .'<div class="sb-widget sb-widget-contact-form">'
            .$titleHtml
            .'<div class="sb-widget-dynamic-placeholder">فرم تماس واقعی اینجا نمایش داده می‌شود</div>'
            .'</div>'
            .'<!--/sb:contact_form-->';
    }

    /**
     * تعداد پست قابل‌نمایش بین ۱ تا ۲۴ محدود می‌شود (هم اینجا هم دوباره در
     * DynamicWidgetResolver — چون این مقدار از فیلد متنی ادمین می‌آید، نه یک
     * select با گزینه ثابت). عنوان بخش (در صورت وجود) هم در این placeholder
     * هم در کارت‌های واقعی رندر می‌شود؛ برای انتقال این دو مقدار به مرحله‌ی
     * رندر زنده، به‌جای متن خام داخل کامنت (که یک '-->' داخل عنوان می‌توانست
     * از کامنت خارج بزند) پیکربندی base64(json) می‌شود — base64 هیچ‌گاه
     * حاوی توالی '-->' یا هیچ کاراکتر خاص HTML دیگری نیست.
     */
    private function renderBlogPostList(array $values): string
    {
        $count = (int) ($values['posts_count'] ?? 3);
        $count = $count > 0 && $count <= 24 ? $count : 3;

        $title = trim((string) ($values['section_title'] ?? ''));
        $titleHtml = $title !== '' ? '<h3 class="sb-widget-title sb-widget-blog-post-list-title">'.e($title).'</h3>' : '';

        $config = base64_encode(json_encode(['count' => $count, 'title' => $title], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return '<!--sb:blog_post_list:'.$config.'-->'
            .'<div class="sb-widget sb-widget-blog-post-list">'
            .$titleHtml
            .'<div class="sb-widget-dynamic-placeholder">'.e((string) $count).' پست وبلاگ اخیر اینجا به‌صورت زنده نمایش داده می‌شود</div>'
            .'</div>'
            .'<!--/sb:blog_post_list-->';
    }
}
