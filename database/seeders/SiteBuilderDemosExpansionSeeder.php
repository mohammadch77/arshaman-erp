<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Enums\LayoutType;
use App\Modules\SiteBuilder\Enums\PageCategoryKey;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\LayoutDemo;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use Illuminate\Database\Seeder;

/**
 * Session گسترش دموها — append-only، seeder های قبلی (SiteBuilderSeeder،
 * SiteBuilderWidgetsExpansionSeeder) دست‌نخورده می‌مانند.
 *
 * سه دموی واقعاً متفاوت برای هر شش دسته صفحه (۱۸ دمو) + سه دموی هدر و سه
 * دموی فوتر (۶ دمو) = ۲۴ دموی جدید. هر گره widget_tree یک instance_label
 * منحصربه‌فرد و توصیفی دارد — طبق درسِ باگ قبلی (برچسب مشترک بین دو نمونه
 * از یک نوع ویجت، فرم PageContentEditor را گمراه‌کننده می‌کند).
 *
 * thumbnail_path این Session عمداً null می‌ماند (تصاویر بندانگشتی واقعی خارج
 * از scope دیتابیسی است — نگاه کن docs/BACKLOG.md).
 */
class SiteBuilderDemosExpansionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pageDemos() as $categoryKey => $demos) {
            $category = PageCategory::where('category_key', $categoryKey)->firstOrFail();

            foreach ($demos as $name => $widgetTree) {
                PageDemo::updateOrCreate(
                    ['page_category_id' => $category->id, 'name' => $name],
                    ['thumbnail_path' => null, 'widget_tree' => $widgetTree, 'is_active' => true]
                );
            }
        }

        foreach ($this->layoutDemos() as $layoutType => $demos) {
            foreach ($demos as $name => $widgetTree) {
                LayoutDemo::updateOrCreate(
                    ['layout_type' => $layoutType, 'name' => $name],
                    ['thumbnail_path' => null, 'widget_tree' => $widgetTree, 'is_active' => true]
                );
            }
        }
    }

    /**
     * گره یک widget_tree — کوتاه‌نویسی برای کاهش تکرار در این فایل حجیم.
     */
    private static function n(string $id, WidgetKey $key, string $label, array $values, array $children = []): array
    {
        return [
            'id' => $id,
            'widget_key' => $key->value,
            'instance_label' => $label,
            'values' => $values,
            'children' => $children,
        ];
    }

    /**
     * یک کلید ریشه اختیاری 'theme' به لیست نودهای یک دمو اضافه می‌کند —
     * WidgetContentRenderer::render() این را رنگ/فونت سطح صفحه (--sb-primary-color/
     * --sb-font-family) می‌خواند. هیچ ستون/جدول جدیدی لازم نبود، فقط یک کلید
     * دیگر در همان JSON که renderer/merger هر دو به‌صراحت به‌عنوان «نود نیست»
     * رد می‌کنند.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int|string, mixed>
     */
    private static function withTheme(array $nodes, string $primaryColor, string $fontFamily): array
    {
        $nodes['theme'] = ['primary_color' => $primaryColor, 'font_family' => $fontFamily];

        return $nodes;
    }

    private function pageDemos(): array
    {
        return [
            PageCategoryKey::Home->value => $this->homeDemos(),
            PageCategoryKey::About->value => $this->aboutDemos(),
            PageCategoryKey::Contact->value => $this->contactDemos(),
            PageCategoryKey::Services->value => $this->servicesDemos(),
            PageCategoryKey::Blog->value => $this->blogDemos(),
            PageCategoryKey::Login->value => $this->loginDemos(),
        ];
    }

    // ------------------------------------------------------------------
    // خانه (home): هیرو + معرفی کوتاه + حداقل یک بخش تعاملی، سه چیدمان واقعاً متفاوت
    // ------------------------------------------------------------------
    private function homeDemos(): array
    {
        return [
            'دموی خانه — کلاسیک (هیرو بالا، گالری، نظر مشتری)' => self::withTheme([
                self::n('home-classic-hero', WidgetKey::Container, 'کانتینر هیرو کلاسیک', [], [
                    self::n('home-classic-hero-title', WidgetKey::Title, 'عنوان اصلی هیرو', ['text' => 'با آرشامان کسب‌وکارتان را متحول کنید', 'level' => 1]),
                    self::n('home-classic-hero-subtitle', WidgetKey::Title, 'زیرعنوان هیرو', ['text' => 'خدمات طراحی وب، برنامه‌نویسی و سئو حرفه‌ای', 'level' => 3]),
                    self::n('home-classic-hero-image', WidgetKey::Image, 'تصویر هیرو کلاسیک', ['image_path' => null, 'alt' => 'تصویر شاخص صفحه اصلی']),
                    self::n('home-classic-hero-button', WidgetKey::Button, 'دکمه اقدام هیرو', ['label' => 'شروع کنید', 'url' => '#contact', 'style' => 'primary']),
                ]),
                self::n('home-classic-spacer', WidgetKey::Spacer, 'فاصله بعد از هیرو کلاسیک', ['height_px' => 60]),
                self::n('home-classic-intro', WidgetKey::Container, 'کانتینر معرفی کوتاه کلاسیک', [], [
                    self::n('home-classic-intro-title', WidgetKey::Title, 'عنوان بخش معرفی کلاسیک', ['text' => 'چرا آرشامان؟', 'level' => 2]),
                    self::n('home-classic-intro-text', WidgetKey::Title, 'متن معرفی کوتاه کلاسیک', ['text' => 'بیش از ده سال تجربه در حوزه دیجیتال', 'level' => 4]),
                ]),
                self::n('home-classic-gallery', WidgetKey::Gallery, 'گالری نمونه‌کارهای کلاسیک', ['images' => [
                    ['image_path' => null, 'caption' => 'پروژه طراحی وب'],
                    ['image_path' => null, 'caption' => 'پروژه برنامه‌نویسی سفارشی'],
                    ['image_path' => null, 'caption' => 'پروژه بهینه‌سازی سئو'],
                ]]),
                self::n('home-classic-testimonial', WidgetKey::Testimonial, 'نظر مشتری برجسته کلاسیک', ['quote_text' => 'تیم آرشامان دقیقاً همان چیزی بود که کسب‌وکار ما نیاز داشت.', 'customer_name' => 'علی رضایی', 'customer_title' => 'مدیرعامل فروشگاه آنلاین']),
            ], '#2563EB', "'Vazirmatn', 'Segoe UI', sans-serif"),

            'دموی خانه — ویترین محصولات (گالری اول، بنر ثانویه)' => self::withTheme([
                self::n('home-showcase-gallery-container', WidgetKey::Container, 'کانتینر گالری برجسته ویترین', [], [
                    self::n('home-showcase-gallery-title', WidgetKey::Title, 'عنوان گالری برجسته ویترین', ['text' => 'محصولات و پروژه‌های ما', 'level' => 2]),
                    self::n('home-showcase-gallery', WidgetKey::Gallery, 'گالری محصولات ویژه ویترین', ['images' => [
                        ['image_path' => null, 'caption' => 'محصول ویژه یک'],
                        ['image_path' => null, 'caption' => 'محصول ویژه دو'],
                        ['image_path' => null, 'caption' => 'محصول ویژه سه'],
                        ['image_path' => null, 'caption' => 'محصول ویژه چهار'],
                    ]]),
                ]),
                self::n('home-showcase-banner', WidgetKey::Container, 'کانتینر بنر ثانویه ویترین', [], [
                    self::n('home-showcase-banner-image', WidgetKey::Image, 'تصویر بنر معرفی ویترین', ['image_path' => null, 'alt' => 'بنر معرفی محصولات']),
                    self::n('home-showcase-banner-title', WidgetKey::Title, 'عنوان بنر معرفی ویترین', ['text' => 'همین امروز با ما همکاری کنید', 'level' => 1]),
                    self::n('home-showcase-banner-button', WidgetKey::Button, 'دکمه بنر معرفی ویترین', ['label' => 'مشاهده خدمات', 'url' => '#services', 'style' => 'primary']),
                ]),
                self::n('home-showcase-faq', WidgetKey::FaqAccordion, 'سوالات متداول خانه ویترین', ['items' => [
                    ['question' => 'چطور سفارش ثبت کنم؟', 'answer' => 'از طریق فرم تماس یا تماس تلفنی مستقیم با تیم فروش.'],
                    ['question' => 'زمان تحویل چقدر است؟', 'answer' => 'بسته به نوع پروژه، معمولاً بین دو تا شش هفته.'],
                    ['question' => 'آیا پشتیبانی بعد از تحویل هست؟', 'answer' => 'بله، همه پروژه‌ها سه ماه پشتیبانی رایگان دارند.'],
                ]]),
                self::n('home-showcase-testimonial', WidgetKey::Testimonial, 'نظر مشتری خانه ویترین', ['quote_text' => 'کیفیت محصولات و سرعت تحویل فوق‌العاده بود.', 'customer_name' => 'مریم احمدی', 'customer_title' => 'مدیر بازاریابی']),
            ], '#EA580C', "'Vazirmatn', 'Georgia', serif"),

            'دموی خانه — تک‌ستونی داستان‌محور (ویدیو، ارزش‌ها، قیمت)' => self::withTheme([
                self::n('home-story-title', WidgetKey::Title, 'عنوان کلی صفحه داستان‌محور', ['text' => 'داستان ما را ببینید', 'level' => 1]),
                self::n('home-story-video', WidgetKey::Video, 'ویدیوی معرفی برند داستان‌محور', ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']),
                self::n('home-story-values', WidgetKey::Container, 'کانتینر ارزش‌های ما داستان‌محور', [], [
                    self::n('home-story-values-title', WidgetKey::Title, 'عنوان بخش ارزش‌ها داستان‌محور', ['text' => 'ارزش‌های ما', 'level' => 2]),
                    self::n('home-story-values-faq', WidgetKey::FaqAccordion, 'پرسش‌های رایج ارزش‌ها داستان‌محور', ['items' => [
                        ['question' => 'اصل اول ما چیست؟', 'answer' => 'شفافیت کامل در قیمت‌گذاری و برنامه‌ریزی پروژه.'],
                        ['question' => 'اصل دوم ما چیست؟', 'answer' => 'تحویل به‌موقع، بدون بهانه.'],
                    ]]),
                ]),
                self::n('home-story-pricing', WidgetKey::PricingTable, 'جدول قیمت پیش‌نمایش داستان‌محور', ['plan_name' => 'پلن استارتاپی', 'price' => 'از ۵ میلیون تومان', 'features' => "طراحی اختصاصی\nپشتیبانی سه ماهه\nیک دور بازطراحی رایگان", 'cta_label' => 'مشاهده جزئیات', 'cta_url' => '#pricing']),
                self::n('home-story-pricing-button', WidgetKey::Button, 'دکمه مشاهده همه پلن‌ها داستان‌محور', ['label' => 'مشاهده همه پلن‌ها', 'url' => '#all-plans', 'style' => 'outline']),
                self::n('home-story-testimonial', WidgetKey::Testimonial, 'نظر مشتری پایانی داستان‌محور', ['quote_text' => 'داستان رشد ما با آرشامان شروع شد.', 'customer_name' => 'حسین کریمی', 'customer_title' => 'بنیان‌گذار استارتاپ']),
            ], '#0F172A', "'Vazirmatn', 'Courier New', monospace"),
        ];
    }

    // ------------------------------------------------------------------
    // درباره ما (about): تنوع واقعی — تیم / تاریخچه / ماموریت
    // ------------------------------------------------------------------
    private function aboutDemos(): array
    {
        return [
            'دموی درباره ما — معرفی تیم' => self::withTheme([
                self::n('about-team-title', WidgetKey::Title, 'عنوان اصلی معرفی تیم', ['text' => 'با تیم ما آشنا شوید', 'level' => 1]),
                self::n('about-team-gallery', WidgetKey::Gallery, 'گالری اعضای تیم', ['images' => [
                    ['image_path' => null, 'caption' => 'مدیرعامل'],
                    ['image_path' => null, 'caption' => 'مدیر فنی'],
                    ['image_path' => null, 'caption' => 'مدیر طراحی'],
                ]]),
                self::n('about-team-testimonial', WidgetKey::Testimonial, 'نقل قول بنیان‌گذار تیم', ['quote_text' => 'تیم ما با اشتیاق برای موفقیت مشتریان کار می‌کند.', 'customer_name' => 'بنیان‌گذار آرشامان', 'customer_title' => 'مدیرعامل']),
            ], '#16A34A', "'Vazirmatn', 'Trebuchet MS', sans-serif"),

            'دموی درباره ما — تاریخچه شرکت' => self::withTheme([
                self::n('about-history-title', WidgetKey::Title, 'عنوان تاریخچه شرکت', ['text' => 'مسیر ما تا امروز', 'level' => 1]),
                self::n('about-history-image', WidgetKey::Image, 'تصویر تاریخچه شرکت', ['image_path' => null, 'alt' => 'دفتر اولیه شرکت']),
                self::n('about-history-milestones', WidgetKey::FaqAccordion, 'نقاط عطف تاریخچه شرکت', ['items' => [
                    ['question' => '۱۳۹۸ — تأسیس', 'answer' => 'آرشامان با یک تیم سه‌نفره کار خود را آغاز کرد.'],
                    ['question' => '۱۴۰۰ — گسترش خدمات', 'answer' => 'خدمات سئو و برنامه‌نویسی سفارشی به سبد خدمات اضافه شد.'],
                    ['question' => '۱۴۰۳ — هلدینگ‌سازی', 'answer' => 'زیرمجموعه‌های Verifex، Tkart، دعانو و Pixentry شکل گرفتند.'],
                ]]),
            ], '#B45309', "'Vazirmatn', 'Palatino', serif"),

            'دموی درباره ما — ماموریت و چشم‌انداز' => self::withTheme([
                self::n('about-mission-container', WidgetKey::Container, 'کانتینر ماموریت', [], [
                    self::n('about-mission-title', WidgetKey::Title, 'عنوان ماموریت', ['text' => 'ماموریت ما', 'level' => 2]),
                    self::n('about-mission-text', WidgetKey::Title, 'متن ماموریت', ['text' => 'ارائه راهکارهای دیجیتال قابل‌اعتماد برای رشد کسب‌وکارهای ایرانی', 'level' => 4]),
                ]),
                self::n('about-vision-container', WidgetKey::Container, 'کانتینر چشم‌انداز', [], [
                    self::n('about-vision-title', WidgetKey::Title, 'عنوان چشم‌انداز', ['text' => 'چشم‌انداز ما', 'level' => 2]),
                    self::n('about-vision-text', WidgetKey::Title, 'متن چشم‌انداز', ['text' => 'تبدیل‌شدن به هلدینگ دیجیتال پیشرو در منطقه تا سال ۱۴۱۰', 'level' => 4]),
                ]),
                self::n('about-mission-image', WidgetKey::Image, 'تصویر ماموریت و چشم‌انداز', ['image_path' => null, 'alt' => 'تیم در حال برنامه‌ریزی استراتژیک']),
                self::n('about-mission-button', WidgetKey::Button, 'دکمه تماس درباره ماموریت', ['label' => 'با ما در تماس باشید', 'url' => '#contact', 'style' => 'primary']),
            ], '#7C3AED', "'Vazirmatn', 'Tahoma', sans-serif"),
        ];
    }

    // ------------------------------------------------------------------
    // تماس (contact): map + container/متن جای‌گیر برای فرم — طبق دستور صریح
    // این Session ویجت contact_form یکپارچه واقعی نمی‌سازد.
    // ------------------------------------------------------------------
    private function contactDemos(): array
    {
        return [
            'دموی تماس — نقشه و اطلاعات کنار هم' => self::withTheme([
                self::n('contact-side-title', WidgetKey::Title, 'عنوان صفحه تماس کنار هم', ['text' => 'با ما در تماس باشید', 'level' => 1]),
                self::n('contact-side-info', WidgetKey::Container, 'کانتینر اطلاعات تماس کنار هم', [], [
                    self::n('contact-side-address', WidgetKey::Title, 'آدرس دفتر کنار هم', ['text' => 'تهران، خیابان ولیعصر، برج آرشامان', 'level' => 4]),
                    self::n('contact-side-phone', WidgetKey::Title, 'شماره تماس کنار هم', ['text' => '۰۲۱-۱۲۳۴۵۶۷۸', 'level' => 4]),
                    self::n('contact-side-email', WidgetKey::Title, 'ایمیل کنار هم', ['text' => 'info@arshaman.example', 'level' => 4]),
                ]),
                self::n('contact-side-map', WidgetKey::Map, 'نقشه آدرس دفتر مرکزی کنار هم', ['embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1!2d51.4!3d35.7']),
            ], '#0EA5E9', "'Vazirmatn', 'Calibri', sans-serif"),

            'دموی تماس — نقشه تمام‌عرض با فرم جای‌گیر' => self::withTheme([
                self::n('contact-fullmap-map', WidgetKey::Map, 'نقشه تمام‌عرض دفتر', ['embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2!2d51.4!3d35.7']),
                self::n('contact-fullmap-form-placeholder', WidgetKey::Container, 'کانتینر جای‌گیر فرم تماس', [], [
                    self::n('contact-fullmap-form-title', WidgetKey::Title, 'عنوان فرم تماس جای‌گیر', ['text' => 'فرم تماس (به‌زودی)', 'level' => 2]),
                ]),
                self::n('contact-fullmap-info', WidgetKey::Container, 'کانتینر اطلاعات تماس ثانویه', [], [
                    self::n('contact-fullmap-hours', WidgetKey::Title, 'ساعات کاری تماس', ['text' => 'شنبه تا چهارشنبه، ۹ تا ۱۸', 'level' => 4]),
                    self::n('contact-fullmap-call-button', WidgetKey::Button, 'دکمه تماس تلفنی', ['label' => 'تماس بگیرید', 'url' => 'tel:02112345678', 'style' => 'primary']),
                ]),
            ], '#DB2777', "'Vazirmatn', 'Verdana', sans-serif"),

            'دموی تماس — شعب متعدد' => self::withTheme([
                self::n('contact-branches-title', WidgetKey::Title, 'عنوان شعب تماس', ['text' => 'دفاتر ما', 'level' => 1]),
                self::n('contact-branches-gallery', WidgetKey::Gallery, 'گالری تصاویر شعب', ['images' => [
                    ['image_path' => null, 'caption' => 'دفتر تهران'],
                    ['image_path' => null, 'caption' => 'دفتر مشهد'],
                    ['image_path' => null, 'caption' => 'دفتر اصفهان'],
                ]]),
                self::n('contact-branches-map', WidgetKey::Map, 'نقشه شعبه مرکزی', ['embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3!2d51.4!3d35.7']),
                self::n('contact-branches-faq', WidgetKey::FaqAccordion, 'سوالات متداول تماس شعب', ['items' => [
                    ['question' => 'چطور با پشتیبانی تماس بگیرم؟', 'answer' => 'از طریق شماره دفتر مرکزی یا فرم تماس هر شعبه.'],
                    ['question' => 'آیا امکان بازدید حضوری هست؟', 'answer' => 'بله، با هماهنگی قبلی امکان‌پذیر است.'],
                ]]),
            ], '#059669', "'Vazirmatn', 'Segoe UI', sans-serif"),
        ];
    }

    // ------------------------------------------------------------------
    // خدمات (services): pricing_table و/یا gallery برای نمایش خدمات
    // ------------------------------------------------------------------
    private function servicesDemos(): array
    {
        return [
            'دموی خدمات — سه پلن قیمتی' => self::withTheme([
                self::n('services-plans-title', WidgetKey::Title, 'عنوان صفحه خدمات پلنی', ['text' => 'پلن‌های خدمات ما', 'level' => 1]),
                self::n('services-plans-basic', WidgetKey::PricingTable, 'پلن پایه خدمات', ['plan_name' => 'پایه', 'price' => '۵٬۰۰۰٬۰۰۰ تومان', 'features' => "طراحی صفحه فرود\nیک دور بازخورد\nتحویل دو هفته‌ای", 'cta_label' => 'انتخاب پلن پایه', 'cta_url' => '#basic']),
                self::n('services-plans-pro', WidgetKey::PricingTable, 'پلن حرفه‌ای خدمات', ['plan_name' => 'حرفه‌ای', 'price' => '۱۵٬۰۰۰٬۰۰۰ تومان', 'features' => "طراحی سایت کامل\nسه دور بازخورد\nبهینه‌سازی سئو پایه\nتحویل چهار هفته‌ای", 'cta_label' => 'انتخاب پلن حرفه‌ای', 'cta_url' => '#pro']),
                self::n('services-plans-enterprise', WidgetKey::PricingTable, 'پلن سازمانی خدمات', ['plan_name' => 'سازمانی', 'price' => 'قیمت توافقی', 'features' => "راهکار اختصاصی\nپشتیبانی نامحدود\nمدیر پروژه اختصاصی", 'cta_label' => 'درخواست مشاوره', 'cta_url' => '#enterprise']),
                self::n('services-plans-faq', WidgetKey::FaqAccordion, 'سوالات متداول خدمات پلنی', ['items' => [
                    ['question' => 'آیا امکان ارتقا بین پلن‌ها هست؟', 'answer' => 'بله، در هر زمان می‌توانید پلن خود را ارتقا دهید.'],
                    ['question' => 'پرداخت چگونه است؟', 'answer' => 'پیش‌پرداخت ۵۰ درصد و باقی هنگام تحویل.'],
                ]]),
            ], '#1D4ED8', "'Vazirmatn', 'Segoe UI', sans-serif"),

            'دموی خدمات — گالری خدمات با جزئیات' => self::withTheme([
                self::n('services-gallery-title', WidgetKey::Title, 'عنوان گالری خدمات', ['text' => 'خدماتی که ارائه می‌دهیم', 'level' => 1]),
                self::n('services-gallery', WidgetKey::Gallery, 'گالری خدمات ارائه‌شده', ['images' => [
                    ['image_path' => null, 'caption' => 'طراحی وب'],
                    ['image_path' => null, 'caption' => 'برنامه‌نویسی سفارشی'],
                    ['image_path' => null, 'caption' => 'سئو و بازاریابی محتوا'],
                ]]),
                self::n('services-gallery-details', WidgetKey::Container, 'کانتینر توضیح خدمات گالری', [], [
                    self::n('services-gallery-details-title', WidgetKey::Title, 'عنوان توضیح خدمات گالری', ['text' => 'چرا این خدمات؟', 'level' => 2]),
                    self::n('services-gallery-details-text', WidgetKey::Title, 'متن توضیح خدمات گالری', ['text' => 'هر خدمت با تیم تخصصی خودش و متدولوژی مشخص اجرا می‌شود', 'level' => 4]),
                ]),
                self::n('services-gallery-button', WidgetKey::Button, 'دکمه مشاوره رایگان خدمات گالری', ['label' => 'دریافت مشاوره رایگان', 'url' => '#consult', 'style' => 'primary']),
            ], '#C2410C', "'Vazirmatn', 'Georgia', serif"),

            'دموی خدمات — ترکیبی پلن و گالری' => self::withTheme([
                self::n('services-combo-title', WidgetKey::Title, 'عنوان معرفی خدمات ترکیبی', ['text' => 'خدمات و نمونه‌کارهای ما', 'level' => 1]),
                self::n('services-combo-gallery', WidgetKey::Gallery, 'گالری نمونه پروژه‌های خدمات ترکیبی', ['images' => [
                    ['image_path' => null, 'caption' => 'نمونه پروژه یک'],
                    ['image_path' => null, 'caption' => 'نمونه پروژه دو'],
                ]]),
                self::n('services-combo-pricing', WidgetKey::PricingTable, 'پلن ویژه خدمات ترکیبی', ['plan_name' => 'پلن ویژه', 'price' => '۱۰٬۰۰۰٬۰۰۰ تومان', 'features' => "شامل طراحی و اجرا\nپشتیبانی شش ماهه", 'cta_label' => 'شروع پروژه', 'cta_url' => '#start']),
                self::n('services-combo-testimonial', WidgetKey::Testimonial, 'نظر مشتری خدمات ترکیبی', ['quote_text' => 'نتیجه کار فراتر از انتظارمان بود.', 'customer_name' => 'سارا محمدی', 'customer_title' => 'مدیر محصول']),
            ], '#9333EA', "'Vazirmatn', 'Tahoma', sans-serif"),
        ];
    }

    // ------------------------------------------------------------------
    // وبلاگ (blog): فقط چیدمان بصری اطراف — اتصال واقعی به فهرست پست‌ها در
    // Session یکپارچه‌سازی بعدی. طبق دستور صریح کارفرما اینجا فقط جای‌گیر است.
    // ------------------------------------------------------------------
    private function blogDemos(): array
    {
        return [
            'دموی وبلاگ — هدر ساده' => self::withTheme([
                self::n('blog-simple-title', WidgetKey::Title, 'عنوان صفحه وبلاگ ساده', ['text' => 'وبلاگ آرشامان', 'level' => 1]),
                self::n('blog-simple-description', WidgetKey::Title, 'توضیح کوتاه وبلاگ ساده', ['text' => 'آخرین مقالات و اخبار حوزه دیجیتال', 'level' => 4]),
                self::n('blog-simple-list-placeholder', WidgetKey::Container, 'کانتینر جای‌گیر فهرست پست‌های ساده', [], [
                    self::n('blog-simple-list-placeholder-title', WidgetKey::Title, 'برچسب جای‌گیر فهرست پست‌های ساده', ['text' => 'فهرست پست‌ها به‌زودی اینجا نمایش داده می‌شود', 'level' => 5]),
                ]),
            ], '#0D9488', "'Vazirmatn', 'Calibri', sans-serif"),

            'دموی وبلاگ — با تصویر شاخص و گالری موضوعات' => self::withTheme([
                self::n('blog-featured-image', WidgetKey::Image, 'تصویر شاخص وبلاگ', ['image_path' => null, 'alt' => 'تصویر شاخص وبلاگ']),
                self::n('blog-featured-title', WidgetKey::Title, 'عنوان وبلاگ با تصویر', ['text' => 'مجله دیجیتال آرشامان', 'level' => 1]),
                self::n('blog-featured-gallery', WidgetKey::Gallery, 'گالری موضوعات وبلاگ', ['images' => [
                    ['image_path' => null, 'caption' => 'طراحی وب'],
                    ['image_path' => null, 'caption' => 'کسب‌وکار دیجیتال'],
                    ['image_path' => null, 'caption' => 'بازاریابی محتوا'],
                ]]),
                self::n('blog-featured-list-placeholder', WidgetKey::Container, 'کانتینر جای‌گیر فهرست پست‌های اخیر', [], [
                    self::n('blog-featured-list-placeholder-title', WidgetKey::Title, 'برچسب جای‌گیر فهرست پست‌های اخیر', ['text' => 'جدیدترین مقالات به‌زودی اینجا نمایش داده می‌شوند', 'level' => 5]),
                ]),
            ], '#DC2626', "'Vazirmatn', 'Georgia', serif"),

            'دموی وبلاگ — دسته‌بندی‌ها و خبرنامه' => self::withTheme([
                self::n('blog-newsletter-title', WidgetKey::Title, 'عنوان وبلاگ خبرنامه', ['text' => 'وبلاگ و خبرنامه آرشامان', 'level' => 1]),
                self::n('blog-newsletter-categories', WidgetKey::FaqAccordion, 'دسته‌بندی‌های محتوا خبرنامه', ['items' => [
                    ['question' => 'طراحی وب', 'answer' => 'راهنماها و ترندهای طراحی رابط کاربری.'],
                    ['question' => 'کسب‌وکار دیجیتال', 'answer' => 'تجربه و تحلیل‌های مدیریتی هلدینگ.'],
                    ['question' => 'سئو و بازاریابی', 'answer' => 'روش‌های افزایش ترافیک ارگانیک.'],
                ]]),
                self::n('blog-newsletter-list-placeholder', WidgetKey::Container, 'کانتینر جای‌گیر فهرست پست‌های خبرنامه', [], [
                    self::n('blog-newsletter-list-placeholder-title', WidgetKey::Title, 'برچسب جای‌گیر فهرست پست‌های خبرنامه', ['text' => 'فهرست پست‌ها به‌زودی اینجا نمایش داده می‌شود', 'level' => 5]),
                ]),
                self::n('blog-newsletter-button', WidgetKey::Button, 'دکمه عضویت در خبرنامه', ['label' => 'عضویت در خبرنامه', 'url' => '#subscribe', 'style' => 'primary']),
            ], '#4338CA', "'Vazirmatn', 'Trebuchet MS', sans-serif"),
        ];
    }

    // ------------------------------------------------------------------
    // ورود (login): عنوان/توضیح کوتاه + احتمالاً تصویر برندینگ — فرم لاگین واقعی
    // بخشی از این دمو نیست، فقط چیدمان بصری اطرافش.
    // ------------------------------------------------------------------
    private function loginDemos(): array
    {
        return [
            'دموی ورود — ساده' => self::withTheme([
                self::n('login-simple-title', WidgetKey::Title, 'عنوان صفحه ورود ساده', ['text' => 'ورود به حساب کاربری', 'level' => 1]),
                self::n('login-simple-description', WidgetKey::Title, 'توضیح صفحه ورود ساده', ['text' => 'برای دسترسی به پنل خود وارد شوید', 'level' => 4]),
            ], '#2563EB', "'Vazirmatn', 'Segoe UI', sans-serif"),

            'دموی ورود — با تصویر برند کنار فرم' => self::withTheme([
                self::n('login-branded-container', WidgetKey::Container, 'کانتینر برندینگ ورود', [], [
                    self::n('login-branded-image', WidgetKey::Image, 'تصویر برند ورود', ['image_path' => null, 'alt' => 'لوگو و برند آرشامان']),
                    self::n('login-branded-slogan', WidgetKey::Title, 'شعار برند ورود', ['text' => 'همراه دیجیتال کسب‌وکار شما', 'level' => 3]),
                ]),
                self::n('login-branded-title', WidgetKey::Title, 'عنوان فرم ورود برندی', ['text' => 'خوش آمدید', 'level' => 1]),
            ], '#EA580C', "'Vazirmatn', 'Palatino', serif"),

            'دموی ورود — پیام خوش‌آمدگویی و بازگشت به صفحه اصلی' => self::withTheme([
                self::n('login-welcome-title', WidgetKey::Title, 'عنوان خوش‌آمدگویی ورود', ['text' => 'خوشحالیم دوباره می‌بینیمتان', 'level' => 1]),
                self::n('login-welcome-text', WidgetKey::Title, 'متن خوش‌آمدگویی ورود', ['text' => 'برای ادامه وارد حساب کاربری خود شوید', 'level' => 4]),
                self::n('login-welcome-back-button', WidgetKey::Button, 'دکمه بازگشت به صفحه اصلی ورود', ['label' => 'بازگشت به صفحه اصلی', 'url' => '/', 'style' => 'outline']),
            ], '#059669', "'Vazirmatn', 'Verdana', sans-serif"),
        ];
    }

    private function layoutDemos(): array
    {
        return [
            LayoutType::Header->value => [
                'دموی هدر — ساده فقط منو' => self::withTheme([
                    self::n('header-simple-nav', WidgetKey::HeaderNav, 'منوی ناوبری هدر ساده', ['nav_links' => [
                        ['label' => 'خانه', 'url' => '/'],
                        ['label' => 'درباره ما', 'url' => '/about'],
                        ['label' => 'خدمات', 'url' => '/services'],
                        ['label' => 'تماس با ما', 'url' => '/contact'],
                    ]]),
                ], '#111827', "'Vazirmatn', 'Segoe UI', sans-serif"),

                'دموی هدر — با لوگو متنی و دکمه CTA' => self::withTheme([
                    self::n('header-branded-container', WidgetKey::Container, 'کانتینر هدر برندی', [], [
                        self::n('header-branded-sitename', WidgetKey::Title, 'نام سایت در هدر برندی', ['text' => 'آرشامان', 'level' => 3]),
                        self::n('header-branded-nav', WidgetKey::HeaderNav, 'منوی ناوبری هدر برندی', ['nav_links' => [
                            ['label' => 'خانه', 'url' => '/'],
                            ['label' => 'خدمات', 'url' => '/services'],
                            ['label' => 'وبلاگ', 'url' => '/blog'],
                        ]]),
                        self::n('header-branded-cta', WidgetKey::Button, 'دکمه تماس هدر برندی', ['label' => 'مشاوره رایگان', 'url' => '#contact', 'style' => 'primary']),
                    ]),
                ], '#2563EB', "'Vazirmatn', 'Tahoma', sans-serif"),

                'دموی هدر — با نوار اطلاعات تماس بالای منو' => self::withTheme([
                    self::n('header-topbar-container', WidgetKey::Container, 'کانتینر نوار بالای هدر کامل', [], [
                        self::n('header-topbar-phone', WidgetKey::Title, 'شماره تماس نوار بالای هدر کامل', ['text' => '۰۲۱-۱۲۳۴۵۶۷۸', 'level' => 6]),
                    ]),
                    self::n('header-topbar-nav', WidgetKey::HeaderNav, 'منوی ناوبری هدر کامل', ['nav_links' => [
                        ['label' => 'خانه', 'url' => '/'],
                        ['label' => 'درباره ما', 'url' => '/about'],
                        ['label' => 'خدمات', 'url' => '/services'],
                        ['label' => 'وبلاگ', 'url' => '/blog'],
                        ['label' => 'تماس با ما', 'url' => '/contact'],
                    ]]),
                ], '#7C3AED', "'Vazirmatn', 'Georgia', serif"),
            ],

            LayoutType::Footer->value => [
                'دموی فوتر — فقط کپی‌رایت' => self::withTheme([
                    self::n('footer-minimal', WidgetKey::Footer, 'فوتر ساده فقط کپی‌رایت', ['copyright_text' => '© تمامی حقوق برای هلدینگ آرشامان محفوظ است.']),
                ], '#111827', "'Vazirmatn', 'Segoe UI', sans-serif"),

                'دموی فوتر — با شبکه‌های اجتماعی' => self::withTheme([
                    self::n('footer-social', WidgetKey::Footer, 'فوتر با شبکه‌های اجتماعی', [
                        'copyright_text' => '© تمامی حقوق برای هلدینگ آرشامان محفوظ است.',
                        'social_links' => [
                            ['label' => 'اینستاگرام', 'url' => 'https://instagram.com/arshaman'],
                            ['label' => 'تلگرام', 'url' => 'https://t.me/arshaman'],
                        ],
                    ]),
                ], '#2563EB', "'Vazirmatn', 'Tahoma', sans-serif"),

                'دموی فوتر — کامل با تماس، شبکه اجتماعی و خبرنامه' => self::withTheme([
                    self::n('footer-full-newsletter', WidgetKey::Container, 'کانتینر خبرنامه فوتر کامل', [], [
                        self::n('footer-full-newsletter-title', WidgetKey::Title, 'عنوان خبرنامه فوتر کامل', ['text' => 'عضو خبرنامه ما شوید', 'level' => 4]),
                    ]),
                    self::n('footer-full', WidgetKey::Footer, 'فوتر کامل با تماس و شبکه اجتماعی', [
                        'copyright_text' => '© تمامی حقوق برای هلدینگ آرشامان محفوظ است.',
                        'social_links' => [
                            ['label' => 'اینستاگرام', 'url' => 'https://instagram.com/arshaman'],
                            ['label' => 'تلگرام', 'url' => 'https://t.me/arshaman'],
                            ['label' => 'لینکدین', 'url' => 'https://linkedin.com/company/arshaman'],
                        ],
                        'contact_text' => 'تهران، خیابان ولیعصر، برج آرشامان — info@arshaman.example',
                    ]),
                ], '#DB2777', "'Vazirmatn', 'Verdana', sans-serif"),
            ],
        ];
    }
}
