# CLAUDE.md — قانون اساسی پروژه ERP آرشامان

> این فایل را Claude Code در هر session می‌خواند. هر تصمیم معماری که نباید فراموش شود، اینجاست.
> **قانون: هیچ کدی خارج از قواعد این فایل نوشته نمی‌شود.**

---

## ۱. پروژه چیست

سامانه ERP چندشرکتی برای هلدینگ آرشامان. پنج مجموعه با مدل‌های کسب‌وکار متفاوت:

| مجموعه | نوع کسب‌وکار | ویژگی |
|---|---|---|
| آرشامان | `project_services` | خدمات پروژه‌ای: طراحی وب، برنامه‌نویسی، سئو |
| Verifex | `hybrid` | هم کالای فیزیکی هم محصول دیجیتال |
| Tkart | `physical_goods` | کالای فیزیکی، انبار و ارسال |
| دعانو | `physical_goods` | محصولات مذهبی، کارت دعا هوشمند NFC |
| Pixentry | `digital_product` | محصول دانلودی، تحویل آنی |

**هدف نهایی:** یک منبع واحد حقیقت برای سود واقعی هر مجموعه + گزارش تجمیعی هلدینگ.

---

## ۲. پشته فنی (تغییرناپذیر)

- **Backend:** Laravel 11 (PHP 8.3+)
- **Frontend:** Livewire 3 + Blade (بدون SPA جدا، بدون API لایه جدا برای UI داخلی)
- **UI Kit:** Mary UI (کامپوننت‌های Blade روی پایه Tailwind + daisyUI) — نه Vuetify
- **جهت:** کل رابط راست‌چین (RTL) — `dir="rtl"` روی تگ `html` در layout اصلی
- **فونت:** Vazirmatn
- **Database:** MySQL 8 (InnoDB, `utf8mb4_unicode_ci`)
- **Queue:** Redis + Laravel Horizon
- **Test:** Pest
- **معماری:** Modular Monolith — نه میکروسرویس

### پکیج‌های مجاز
```
livewire/livewire              # تعامل reactive سمت سرور
robsontenorio/mary              # کامپوننت‌های UI (فرم، جدول، مودال، تب و ...)
spatie/laravel-permission       # نقش و دسترسی
spatie/laravel-activitylog      # audit trail
spatie/laravel-medialibrary     # پیوست فایل
morilog/jalali                  # تقویم شمسی
maatwebsite/excel               # درون‌ریزی و برون‌بری
```
```
# سمت فرانت (npm — عمدتاً build ابزار، نه فریم‌ورک UI)
tailwindcss                     # Mary UI مستقیماً روی آن سوار است
daisyui                         # کامپوننت‌های پایه CSS که Mary UI به آن‌ها استایل می‌دهد
alpinejs                        # همراه Livewire می‌آید، نیازی به نصب جدا معمولاً نیست
```
افزودن پکیج جدید نیاز به تأیید دارد. پکیج بی‌دلیل اضافه نکن.

> **نکته مهم:** Mary UI و Blade Icons به مرور نسخه عوض می‌کنند. نام دقیق تگ‌ها و کامپوننت‌ها (مثلاً نحوه فراخوانی آیکون) را Claude Code هنگام نصب واقعی از مستندات نصب‌شده تأیید کند، نه از حافظه.

---

## ۳. قواعد سراسری کد (روی همه ماژول‌ها)

| موضوع | قانون |
|---|---|
| شناسه | UUID برای همه جدول‌ها (`CHAR(36)` در MySQL، `HasUuids` در مدل — نه auto-increment) |
| مبالغ | `decimal:2` در Laravel، `DECIMAL(18,2)` در دیتابیس. **هرگز float** |
| تاریخ | ذخیره `TIMESTAMP`/`DATETIME` به وقت UTC. نمایش و ورودی شمسی (morilog/jalali) |
| ایزولاسیون شرکت | هر جدول عملیاتی `owner_company_id` دارد + Global Scope خودکار |
| حذف | Soft delete (`deleted_at`) — هرگز حذف فیزیکی |
| ردگیری | `created_by_user_id` و `updated_by_user_id` روی همه رکوردها (هرگز `created_by` خام) |
| اعتبارسنجی | هم Form Request/Livewire validation هم constraint سطح دیتابیس |
| نام‌گذاری | جدول‌ها انگلیسی جمع، ستون‌ها snake_case؛ قرارداد کامل در `docs/DATABASE_CONVENTIONS.md` |
| enum کسب‌وکاری | `VARCHAR` در دیتابیس + enum PHP در لایه اپ (نه enum سطح دیتابیس) — انعطاف بیشتر برای تغییر بعدی |
| زبان رابط کاربری | فارسی، راست‌چین |

> **قانون نام‌گذاری:** هر کلید خارجی نقش کامل خودش را در نام حمل می‌کند، حتی وقتی نقش با نام جدول مقصد یکی است.
> جزئیات کامل + مثال‌های قبل/بعد در `docs/DATABASE_CONVENTIONS.md`. مهم‌ترین‌ها: `company_id` همیشه `owner_company_id`
> نوشته می‌شود (نه به‌شکل خام)، `created_by`/`updated_by` همیشه با پسوند `_user_id`.

---

## ۳.۵. سیستم طراحی (Mary UI + daisyUI) — قواعد الزامی UI

### اصول
- از کامپوننت‌های Mary UI استفاده کن (`x-mary-*` یا معادل نصب‌شده)، نه HTML خام استایل‌دار.
- **کل رابط راست‌چین.** هیچ صفحه‌ای چپ‌چین نباشد. اعداد و تاریخ‌ها هم در بستر RTL درست بنشینند.
- سلسله‌مراتب بصری با کلاس‌های داخلی daisyUI (`card`, `shadow`, `elevation` معادل) و رنگ‌های تم، نه استایل دستی.

### همه‌چیز از تم مرکزی می‌آید (قابل‌جایگزینی)
هیچ رنگ، لوگو یا آیکونی نباید در Blade view یا کامپوننت Livewire hardcode شود. همه از یک منبع مرکزی:

- **رنگ‌ها:** فقط از تم daisyUI تعریف‌شده در `tailwind.config.js` (رنگ‌های semantic: `primary`, `secondary`, `accent`, `error`, ...). کامپوننت‌ها فقط کلاس‌های `btn-primary`, `text-primary` و مشابه را می‌گیرند، هرگز کد رنگ مستقیم (`#1976D2`).
- **لوگو:** فقط از کامپوننت Blade `<x-app-logo />` که به یک فایل در `public/images/theme/` یا `resources/images/theme/` اشاره می‌کند. جایگزینی لوگو = عوض‌کردن یک فایل.
- **آیکون‌ها:** فقط از یک نگاشت مرکزی در `config/theme.php` (نام معنایی → نام آیکون واقعی). کامپوننت‌ها نام معنایی می‌گیرند (مثل `theme_icon('order')`)، نه نام مستقیم آیکون.
- **فونت:** فقط در تنظیمات `tailwind.config.js` و `resources/css/app.css`.

### چرا این ساختار
وقتی کارفرما لوگو، رنگ برند یا آیکون جدید بدهد، فقط فایل‌های داخل `theme/` و `tailwind.config.js` عوض می‌شوند و کل اپ به‌روز می‌شود — بدون دست‌زدن به هیچ Blade view یا کامپوننتی. این «قابل‌جایگزینی» یک الزام است، نه سلیقه.

### قانون
اگر در یک Blade view یک کد رنگ (مثل `#1976D2`)، مسیر مستقیم لوگو، یا نام مستقیم آیکون دیدی — **اشتباه است**. باید به `theme/` منتقل شود.

---

## ۴. ساختار پوشه (Modular Monolith)

```
app/Modules/
├── Core/          # Company, User, Role, Party, Currency, ExchangeRate, FiscalPeriod
├── Catalog/       # Product, Category
├── Sales/         # Order, OrderLine
├── Inventory/     # Warehouse, Stock, StockMovement
├── Shipping/      # Shipment
├── Projects/      # Quote, Contract, Project, Milestone, Timesheet
├── Finance/       # Expense, Budget, Journal, Invoice, Payment, Purchase
├── HR/            # Employee, Attendance, Leave, Payroll
├── CRM/           # Contact, Interaction, Lead, Segment, Campaign, Ticket
└── Reporting/     # read models و query object ها
```

هر ماژول این ساختار را دارد:
```
Modules/Sales/
├── Models/
├── Actions/          # منطق کسب‌وکار (نه در کامپوننت Livewire، نه در مدل)
├── States/           # ماشین وضعیت
├── Policies/
├── Database/Migrations/
├── Database/Seeders/
└── Tests/
```

**لایه UI (Livewire + Blade) جدا از این ساختار زندگی می‌کند** چون Livewire به‌صورت پیش‌فرض کامپوننت‌ها را زیر `App\Livewire` جست‌وجو می‌کند:

```
app/Livewire/
├── Core/          # مثلاً CompanySwitcher, LoginForm, UserManagement
├── Sales/         # OrderIndex, OrderForm
└── ...            # به ازای هر ماژول یک زیرپوشه هم‌نام

resources/views/livewire/
├── core/
├── sales/
└── ...            # Blade view هر کامپوننت، هم‌نام و kebab-case
```

**قانون وابستگی:** ماژول‌ها فقط از طریق Action یا Event با هم حرف می‌زنند. هرگز مدل یک ماژول را مستقیم در ماژول دیگر query نکن. کامپوننت‌های Livewire هم فقط به Action ها و مدل‌های همان ماژول (یا Action های عمومی cross-module) وصل می‌شوند، نه مستقیم به مدل ماژول دیگر.

---

## ۵. قواعد حیاتی کسب‌وکار (اشتباه اینجا = بازنویسی)

### ۵.۱. ایزولاسیون شرکت
هر مدل عملیاتی `BelongsToCompany` trait دارد که:
- Global Scope روی `owner_company_id` بر پایه شرکت فعال session
- هنگام ساخت رکورد، `owner_company_id` خودکار پر می‌شود

> نام ستون طبق `docs/DATABASE_CONVENTIONS.md` عمداً `owner_company_id` است، نه `company_id` خام —
> چون قرارداد پروژه این است که هر FK نقش کامل خودش را حمل کند، حتی وقتی نقش (اینجا: مالکیت) بدیهی به‌نظر برسد.

```php
// app/Modules/Core/Concerns/BelongsToCompany.php
trait BelongsToCompany {
    protected static function bootBelongsToCompany(): void {
        static::addGlobalScope('owner_company', fn($q) =>
            $q->where('owner_company_id', app(CompanyContext::class)->id())
        );
        static::creating(fn($m) =>
            $m->owner_company_id ??= app(CompanyContext::class)->id()
        );
    }
}
```
**هر مدل عملیاتی بدون این trait، باگ امنیتی است.**

### ۵.۲. Snapshot — سه جای حیاتی
داده‌های زیر هنگام ثبت **کپی** می‌شوند، نه reference:
1. **نرخ ارز** روز تراکنش → `orders.exchange_rate`
2. **بهای تمام‌شده** لحظه فروش → `order_lines.cost_amount`
3. **نوع تحویل** محصول → `order_lines.fulfillment_type`

دلیل: تغییر داده امروز نباید تاریخ را خراب کند.

### ۵.۳. نوع تحویل در سطح محصول (نه شرکت)
چون Verifex هر دو نوع می‌فروشد:
- `products.fulfillment_type` = `physical` | `digital` | `service`
- `companies.business_type` فقط تعیین می‌کند کدام ماژول در UI فعال باشد
- **قانون:** اگر سفارش حداقل یک قلم `physical` داشته باشد، مسیر ارسال فعال می‌شود

### ۵.۴. Idempotency در sync ووکامرس
کلید یکتا: `(owner_company_id, external_id)`. هر sync دوباره، سفارش تکراری نمی‌سازد.

### ۵.۵. حسابداری دوطرفه
هر سند: `SUM(debit) = SUM(credit)`. این را در سطح دیتابیس با constraint یا trigger تضمین کن، نه فقط اپلیکیشن.
سند `posted` غیرقابل ویرایش است — اصلاح فقط با سند برگشتی (reversal).

### ۵.۶. تسهیم هزینه بر پایه تایم‌شیت
هزینه پرسنل مشترک بر پایه **ساعت واقعی کار** بین مجموعه‌ها تقسیم می‌شود، نه فرمول دلخواه.
کار داخلی (آرشامان روی سایت‌های خودی) **درآمد نیست** — هزینه است که به آن زیرمجموعه تخصیص می‌یابد.

### ۵.۷. بدون تراکنش داخلی
آرشامان به زیرمجموعه‌ها فاکتور نمی‌زند. گزارش تجمیعی = جمع ساده همه شرکت‌ها (تبدیل به تومان). بدون elimination.

### ۵.۸. تصمیم‌های نهایی کارفرما (نسخه ۴.۰ سند)
این‌ها قطعی‌اند و در پیاده‌سازی رعایت می‌شوند:
- **تسهیم هزینه مشترک:** به نسبت درآمد هر شرکت در همان دوره (نه مساوی).
- **انبار فیزیکاً مشترک:** یک انبار فیزیکی، اما موجودی به تفکیک `owner_company_id` مالک نگهداری می‌شود. یعنی جدول stock ستون owner_company_id دارد ولی warehouse می‌تواند مشترک باشد.
- **شرکت حمل:** تیپاکس. فیلد carrier در shipments پیش‌فرض «تیپاکس» و ساختار برای افزودن شرکت حمل دیگر باز بماند.
- **سفارش دستی:** علاوه بر ووکامرس، سفارش‌های شبکه‌های اجتماعی (اینستاگرام، تلگرام) دستی با `source='manual'` ثبت می‌شوند.
- **نرخ نیروی کار (فاز ۳):** ترکیبی — ساعتی از تایم‌شیت + کارمزد قطعه‌ای برای طراحی (بر پایه تعداد طرح تحویل‌شده).
- **سامانه مودیان:** لازم است، در فاز ۵. صدور صورتحساب رسمی و اتصال به سامانه مودیان مالیاتی.
- **جایگزینی شایگان:** ابتدای سال مالی. شایگان دیتابیس داخلی دارد؛ امکان خواندن مستقیم از آن به‌جای ورود دستی بررسی شود.

---

## ۶. ماشین‌های وضعیت

```
سفارش فیزیکی:  received → paid → preparing → shipped → delivered → closed
                              ↘ cancelled / returned

سفارش دیجیتال: received → paid → delivered → closed

پروژه:         lead → quote → contract → in_progress → milestone_delivered → settled → closed

فاکتور:        draft → issued → paid(partial|full) → void
```
**قانون:** ترنزیشن‌های تعریف‌نشده رد می‌شوند. بعد از `delivered` فیلدهای مالی قفل‌اند.

---

## ۷. تست‌های اجباری (بدون این‌ها merge نکن)

هر فاز باید این تست‌ها را داشته باشد:

```php
// ۱. ایزولاسیون شرکت — مهم‌ترین تست امنیتی
it('prevents cross-company data access');

// ۲. تراز حسابداری — از فاز ۵
it('ensures every journal entry balances');

// ۳. عدم تکرار sync
it('does not duplicate orders on repeated sync');

// ۴. ماشین وضعیت
it('rejects invalid state transitions');

// ۵. محاسبه سود
it('calculates order margin correctly with snapshot cost');
```

---

## ۸. دستورات پرکاربرد

```bash
php artisan test                    # همه تست‌ها
php artisan test --filter=Company   # تست یک ماژول
php artisan migrate:fresh --seed    # ریست دیتابیس
./vendor/bin/pint                   # فرمت کد
npm run dev                         # کامپایل CSS/JS (Tailwind + Alpine)
```

---

## ۹. قواعد کار با Claude Code

1. **یک ماژول در هر session.** هرگز دو ماژول را هم‌زمان دست نزن.
2. **ترتیب ساخت در هر ماژول:** migration → model → factory → test → action → policy → کامپوننت Livewire + Blade view
3. **تست قبل از پیاده‌سازی** برای منطق مالی.
4. **بعد از هر تست سبز، commit کن.**
5. **فاز بعد را زودتر شروع نکن.** اگر فیچری خارج از فاز جاری لازم شد، در `BACKLOG.md` بنویس.
6. **مهاجرت‌ها را ویرایش نکن** بعد از اجرا — migration جدید بساز.
7. اگر قاعده‌ای در این فایل مبهم بود، **بپرس، حدس نزن.**
8. **هرگز `migrate:fresh` یا `migrate:refresh` را روی دیتابیس محیط توسعه اصلی (`arshaman_erp` در `.env`) اجرا نکن** مگر کاربر صریحاً همان لحظه درخواستش کند. برای تست صحت یک migration جدید، یا از `php artisan migrate` (بدون `fresh`، که فقط migration‌های جدید را اضافه می‌کند) استفاده کن، یا `php artisan migrate:fresh --env=testing` روی دیتابیس تستی جدا بزن.
9. **Authorization همیشه داخل خود Action نوشته می‌شود، نه فقط در `mount()` کامپوننت Livewire یا لایه UI.** هر Action که یک عمل حساس (ساخت/تغییر/حذف رکورد، تخصیص نقش، هر چیزی که در Policy تعریف شده) انجام می‌دهد، اول با `Gate::forUser($actor)->authorize(...)` (یا فراخوانی صریح متد Policy) بررسی می‌کند که `$actor` واقعاً مجاز است — مستقل از اینکه از کجا صدا زده شده.
   **چرا:** لایه Livewire فقط یک caller است، نه تنها caller. اگر Action به caller اعتماد کورکورانه داشته باشد، هر مسیر دیگری (کنسول، job، Action دیگر، تست، کد آینده) که مستقیم آن را صدا بزند بدون رد شدن اجرا می‌شود — حتی با یک کاربر عادی. این دقیقاً همان دسته باگ امنیتی است که بند ۵.۱ (ایزولاسیون شرکت) درباره‌اش هشدار می‌دهد، فقط این‌بار در سطح Action.
   **این خصوصاً در فاز مالی (Finance) حیاتی است:** Action هایی مثل تأیید هزینه، صدور فاکتور، یا ثبت سند حسابداری هرگز نباید فقط به این تکیه کنند که کامپوننت Livewire قبلش authorize زده — خود Action باید authorize کند.
   مرجع پیاده‌سازی: `app/Modules/Core/Actions/CreateUser.php`, `AssignRole.php`, `ToggleUserActive.php` (Session 4) — و تست مستقیم روی Action (نه از مسیر Livewire) در `tests/Feature/Core/UserManagementTest.php`.

---

## ۱۰. وضعیت فعلی پروژه

**فاز جاری:** فاز ۱ — هسته، محصولات، سفارش‌ها، انبار پایه. پروژه کوچک `docs/PROJECT_01_AUTH.md` (احراز هویت) با تکمیل Session 5 به پایان رسید؛ فاز ۱ اکنون آماده شروع محصولات/سفارش‌ها/انبار است.

**تکمیل‌شده:**
- [x] Session 0: راه‌اندازی تم (Mary UI + RTL + ساختار theme/)
- [x] Session 1: شرکت + کاربر + صفحه ورود
- [x] Session 2: نقش‌ها و دسترسی نقش×شرکت (migrations، CompanyContext، Gate، EnsureCompanyAccess middleware)
- [x] Session 3: سوییچر شرکت + پوسته پیشخوان
- [x] Session 4: مدیریت کاربران (Action ها، UserPolicy، UserIndex/UserCreate/AssignRole، activity_log)
- [x] Session 5: دعوت‌نامه (اختیاری)

### ماژول HR (فاز ۲)

طبق `docs/PROJECT_02_HR.md`:

- [ ] Session 1: پرسنل (Employees)
- [x] Session 2: تقویم کاری و تعطیلات رسمی
- [x] Session 2.5: اتصال کارمند به کاربر سیستم
- [x] Session 3: حضور و غیاب (Attendance)
- [ ] Session 4: مرخصی‌ها (Leave)
- [ ] Session 5: جمع ماهانه کارکرد/غیبت
- [ ] Session 6: حقوق و دستمزد
- [ ] Session 7: گزارش پایه هزینه پرسنل

> این بخش را بعد از هر Session به‌روز کن. این حافظه بلندمدت پروژه است.
