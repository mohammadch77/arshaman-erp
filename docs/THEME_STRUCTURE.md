# ساختار تم قابل‌جایگزینی — پروژه ERP آرشامان
## Mary UI (Tailwind + daisyUI) + Blade + RTL + لوگو/آیکون/رنگ متمرکز

> هدف: وقتی کارفرما لوگو، رنگ برند یا آیکون جدید داد، فقط فایل‌های تم عوض شوند — بدون دست‌زدن به هیچ Blade view یا کامپوننت Livewire.

---

## اصل بنیادی

**هیچ رنگ، لوگو یا آیکونی در Blade view یا کامپوننت Livewire hardcode نمی‌شود.** همه از یک منبع مرکزی می‌آیند. این یک الزام معماری است، نه سلیقه.

```
config/
└── theme.php                     ← نگاشت مرکزی آیکون‌ها + متادیتای برند

resources/
├── css/
│   └── app.css                   ← وارد کردن Tailwind + تعریف فونت Vazirmatn
├── views/
│   └── components/
│       └── app-logo.blade.php    ← تنها جایی که لوگو رندر می‌شود
└── images/theme/
    ├── logo.svg                  ← لوگوی اصلی (جایگزین‌شونده)
    ├── logo-small.svg
    └── favicon.svg

tailwind.config.js                 ← تعریف تم رنگی daisyUI (رنگ‌های semantic)
app/Support/Farsi.php              ← ارقام فارسی و قالب تومان (سمت سرور)
```

---

## ۱. تم رنگ (tailwind.config.js + daisyUI)

همه رنگ‌ها اینجا. کامپوننت‌ها فقط از کلاس‌های semantic استفاده می‌کنند (`btn-primary`, `text-primary`, `bg-base-100`)، نه کد رنگ مستقیم.

```js
// tailwind.config.js
const daisyui = require('daisyui')

module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/Livewire/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Vazirmatn', 'sans-serif'],
      },
    },
  },
  plugins: [daisyui],
  daisyui: {
    themes: [
      {
        arshaman: {
          // این رنگ‌ها موقتی‌اند. وقتی برند آرشامان رسید، فقط همین‌جا عوض شود.
          primary:   '#1976D2',
          secondary: '#455A64',
          accent:    '#FF6F00',
          neutral:   '#2A2E37',
          'base-100': '#FFFFFF',
          info:      '#0288D1',
          success:   '#388E3C',
          warning:   '#F57C00',
          error:     '#D32F2F',
        },
      },
    ],
  },
}
```

راه‌اندازی RTL و فونت در layout اصلی:

```blade
{{-- resources/views/components/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="arshaman">
<head>
    <meta charset="UTF-8">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans bg-base-200">
    {{ $slot }}
    @livewireScripts
</body>
</html>
```

**قانون:** اگر در یک Blade view کد رنگ مثل `#1976D2` یا `style="color: red"` دیدی، اشتباه است. باید کلاس `text-primary` یا `btn-primary` باشد.

---

## ۲. آیکون‌ها (config/theme.php) — نگاشت معنایی

کامپوننت‌ها نام **معنایی** می‌گیرند، نه نام مستقیم آیکون. این یعنی وقتی خواستی آیکون سفارش را عوض کنی، فقط یک خط در این فایل عوض می‌شود.

> نام دقیق ست آیکون (مثلاً Heroicons از طریق Blade Icons، که معمولاً همراه Mary UI نصب می‌شود) را Claude Code هنگام نصب واقعی تأیید کند. ساختار پایین مستقل از آن انتخاب است.

```php
// config/theme.php
return [
    'icons' => [
        // ناوبری
        'dashboard' => 'o-squares-2x2',
        'company'   => 'o-building-office',
        'user'      => 'o-user',
        'users'     => 'o-user-group',
        'settings'  => 'o-cog-6-tooth',
        'logout'    => 'o-arrow-left-on-rectangle',

        // ماژول‌ها
        'order'     => 'o-shopping-cart',
        'product'   => 'o-cube',
        'inventory' => 'o-building-storefront',
        'shipping'  => 'o-truck',
        'expense'   => 'o-banknotes',
        'invoice'   => 'o-document-text',
        'project'   => 'o-briefcase',
        'timesheet' => 'o-clock',
        'crm'       => 'o-heart',
        'report'    => 'o-chart-bar',

        // عملیات
        'add'       => 'o-plus',
        'edit'      => 'o-pencil',
        'delete'    => 'o-trash',
        'search'    => 'o-magnifying-glass',
        'filter'    => 'o-funnel',
        'save'      => 'o-check',
    ],
];
```

یک هلپر ساده برای استفاده در Blade:

```php
// app/Support/helpers.php (یا یک Facade کوچک به‌جای تابع سراسری)
function theme_icon(string $key): string
{
    return config("theme.icons.{$key}", 'o-question-mark-circle');
}
```

استفاده در Blade:
```blade
<x-icon :name="theme_icon('order')" />        {{-- درست --}}
{{-- <x-icon name="o-shopping-cart" />  ← اشتباه: نام مستقیم --}}
```

**مزیت:** اگر بعداً کارفرما ست آیکون سفارشی داد، فقط مقادیر `config/theme.php` عوض می‌شوند.

---

## ۳. لوگو (app-logo.blade.php) — تنها نقطه رندر

لوگو فقط در یک کامپوننت Blade رندر می‌شود. هر جای اپ که لوگو لازم است، از این کامپوننت استفاده می‌کند.

```blade
{{-- resources/views/components/app-logo.blade.php --}}
@props(['size' => 'normal'])

<img
    src="{{ asset('images/theme/' . ($size === 'small' ? 'logo-small.svg' : 'logo.svg')) }}"
    alt="آرشامان"
    {{ $attributes->merge(['class' => $size === 'small' ? 'h-6 w-auto' : 'h-10 w-auto']) }}
/>
```

استفاده:
```blade
<x-app-logo />                 {{-- در هدر --}}
<x-app-logo size="small" />    {{-- در حالت جمع‌شده منو --}}
```

**جایگزینی لوگو:** فقط فایل‌های داخل `resources/images/theme/` (یا `public/images/theme/` بسته به روش build) را با فایل جدید عوض کن. هیچ کد دیگری تغییر نمی‌کند.

---

## ۴. راست‌چین کامل (RTL)

- `dir="rtl"` روی تگ `html` در layout اصلی، کل کامپوننت‌های daisyUI/Tailwind به‌درستی راست‌چین می‌شوند (اکثر کلاس‌های Tailwind جهت‌آگاه نیستند، پس برای فاصله‌گذاری از کلاس‌های منطقی مثل `ms-*`/`me-*` به‌جای `ml-*`/`mr-*` استفاده کن).
- `lang="fa"` روی همان تگ.
- فونت Vazirmatn به‌عنوان فونت پایه در `tailwind.config.js` (بخش ۱ بالا).
- اعداد: نمایش با ارقام فارسی (نه در ورودی/ذخیره) — با یک هلپر مرکزی سمت سرور.

```php
// app/Support/Farsi.php
namespace App\Support;

class Farsi
{
    public static function toDigits(int|string $input): string
    {
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        return preg_replace_callback('/\d/', fn($m) => $fa[$m[0]], (string) $input);
    }

    public static function toToman(float|int $amount): string
    {
        return self::toDigits(number_format($amount)) . ' تومان';
    }
}
```

استفاده در Blade: `{{ \App\Support\Farsi::toToman($order->total) }}` یا یک Blade directive اختصاصی `@toman($order->total)`.

---

## ۵. پرامپت راه‌اندازی تم (اولین کار قبل از Session 1)

قبل از شروع ماژول احراز هویت، این پرامپت را به Claude Code بده:

```
CLAUDE.md و docs/THEME_STRUCTURE.md را بخوان. نقشه بده، بعد پیاده کن:

راه‌اندازی سیستم تم قابل‌جایگزینی:
1. Livewire 3 و Mary UI را نصب کن (composer require livewire/livewire robsontenorio/mary
   و php artisan mary:install) — این Tailwind و daisyUI را هم راه‌اندازی می‌کند.
2. tailwind.config.js را طبق THEME_STRUCTURE.md بخش ۱ تنظیم کن: تم داسی‌یوآی «arshaman»
   با رنگ‌های موقت، فونت Vazirmatn.
3. config/theme.php را با نگاشت آیکون‌های بخش ۲ بساز + تابع/هلپر theme_icon().
4. کامپوننت resources/views/components/app-logo.blade.php با لوگوی placeholder
   (فایل SVG ساده با متن «آرشامان»).
5. app/Support/Farsi.php برای ارقام فارسی و قالب تومان.
6. Layout اصلی resources/views/components/layouts/app.blade.php:
   html با lang="fa" dir="rtl" data-theme="arshaman"، شامل @livewireStyles/@livewireScripts.
7. یک کامپوننت Livewire تست ساده بساز که نشان دهد:
   - یک دکمه با کلاس btn-primary، یک کارت Mary UI، یک آیکون از theme_icon()
   - همه راست‌چین
   - یک عدد با ارقام فارسی (از Farsi::toToman)

نساز: هیچ ماژول کسب‌وکاری. فقط زیرساخت تم.
تمام وقتی: صفحه تست، راست‌چین و با رنگ/آیکون از تم مرکزی نمایش داده شود.
```

بعد از این، Session 1 ماژول احراز هویت را شروع کن.

---

## ۶. چک‌لیست «قابل‌جایگزینی»

قبل از پایان هر session UI، این‌ها را چک کن:

- [ ] هیچ کد رنگی (`#...` یا `style="color:..."`) در Blade view نیست — همه از کلاس‌های تم
- [ ] هیچ نام مستقیم آیکون در Blade view نیست — همه از `theme_icon()` / `config/theme.php`
- [ ] لوگو فقط از `<x-app-logo />` می‌آید
- [ ] همه‌چیز راست‌چین است (از کلاس‌های منطقی `ms-*`/`me-*` استفاده شده)
- [ ] اعداد نمایشی با ارقام فارسی
- [ ] از کامپوننت‌های Mary UI استفاده شده، نه HTML خام استایل‌دار

اگر همه تیک خوردند، تم واقعاً قابل‌جایگزین است و بعداً توسعه‌اش آسان خواهد بود.
