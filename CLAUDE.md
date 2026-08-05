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
8.۱. **هرگز `DB_CONNECTION`/`DB_DATABASE` را دستی روی مقدار دیتابیس واقعی (`arshaman_erp`) ست نکن تا یک تست RefreshDatabase-محور اجرا کنی** — حتی برای یک اجرای یک‌باره و «فقط برای بررسی». `RefreshDatabase` دقیقاً کارش drop و بازسازی کامل schema است؛ اگر به‌جای دیتابیس تست به دیتابیس واقعی وصل شود، تمام داده را پاک می‌کند. اگر واقعاً نیاز به تست روی MySQL واقعی بود (نه sqlite پیش‌فرض `phpunit.xml`/`.env.testing`)، از یک دیتابیس MySQL جدا و صراحتاً نام‌گذاری‌شده مثل `arshaman_erp_testing` استفاده کن، هرگز از خودِ `arshaman_erp`.
   **چرا:** این حادثه واقعاً افتاد (نگاه کن Session اصلاح CHECK constraint های Auth/HR/CRM/Core) — یک اجرای دستی `DB_CONNECTION=mysql DB_DATABASE=arshaman_erp php artisan test` باعث شد `RefreshDatabase` کل دیتابیس توسعه اصلی را خالی کند. جبران فقط به‌خاطر وجود یک `mysqldump` قبلی ممکن شد.
   **گارد کد (نه فقط قرارداد ذهنی):** `tests/TestCase.php` قبل از هر `setUp()` واقعی چک می‌کند که `DB_DATABASE` برابر `arshaman_erp` نباشد و در آن صورت بلافاصله `RuntimeException` می‌اندازد، قبل از اینکه `RefreshDatabase` فرصت کند کاری انجام دهد. تست اثبات این گارد: `tests/Unit/TestCaseDatabaseGuardTest.php`.
9. **Authorization همیشه داخل خود Action نوشته می‌شود، نه فقط در `mount()` کامپوننت Livewire یا لایه UI.** هر Action که یک عمل حساس (ساخت/تغییر/حذف رکورد، تخصیص نقش، هر چیزی که در Policy تعریف شده) انجام می‌دهد، اول با `Gate::forUser($actor)->authorize(...)` (یا فراخوانی صریح متد Policy) بررسی می‌کند که `$actor` واقعاً مجاز است — مستقل از اینکه از کجا صدا زده شده.
   **چرا:** لایه Livewire فقط یک caller است، نه تنها caller. اگر Action به caller اعتماد کورکورانه داشته باشد، هر مسیر دیگری (کنسول، job، Action دیگر، تست، کد آینده) که مستقیم آن را صدا بزند بدون رد شدن اجرا می‌شود — حتی با یک کاربر عادی. این دقیقاً همان دسته باگ امنیتی است که بند ۵.۱ (ایزولاسیون شرکت) درباره‌اش هشدار می‌دهد، فقط این‌بار در سطح Action.
   **این خصوصاً در فاز مالی (Finance) حیاتی است:** Action هایی مثل تأیید هزینه، صدور فاکتور، یا ثبت سند حسابداری هرگز نباید فقط به این تکیه کنند که کامپوننت Livewire قبلش authorize زده — خود Action باید authorize کند.
   مرجع پیاده‌سازی: `app/Modules/Core/Actions/CreateUser.php`, `AssignRole.php`, `ToggleUserActive.php` (Session 4) — و تست مستقیم روی Action (نه از مسیر Livewire) در `tests/Feature/Core/UserManagementTest.php`.
10. **هرگز رمز عبور یک کاربر واقعی/موجود را برای تست بصری تغییر نده.** اگر نیاز به ورود
    برای تأیید بصری بود، یا یک کاربر تستی موقت جدید بساز و در پایان حذفش کن، یا از کاربر
    بخواه خودش تست کند.
11. **هرگز `hasRoleInCompany()` و `hasRole()` را جدا از هم صدا نزن؛ همیشه از متد واحد
    `User::hasRoleInCompany($companyId, $roleName)` استفاده کن** که هر دو شرط (شرکت
    مشخص + نام نقش) را در یک کوئری scoped با هم چک می‌کند.
    **چرا:** `hasRole($roleName)` سراسری است — نقش را در *هر* شرکتی که کاربر داشته
    باشد پیدا می‌کند، نه فقط شرکت هدف. ترکیب `hasRoleInCompany($companyId) &&
    hasRole($roleName)` دو شرط جدا را AND می‌کند، اما دومی هنوز به شرکت اول مقید
    نیست — کاربری که در شرکت هدف فقط `viewer` است ولی در یک شرکت کاملاً نامرتبط
    `operator`/`holding_admin` است، هر دو شرط را رد می‌کند و اشتباهاً مجاز شناخته
    می‌شود. این دقیقاً همان دسته باگ ایزولاسیون شرکتی است که بند ۵.۱ درباره‌اش هشدار
    می‌دهد، فقط در سطح چک نقش. کشف شد در بازبینی امنیتی `InteractionPolicy`
    (ماژول CRM) و بعد در سراسر پروژه تکرار پیدا شد — نگاه کن commit
    "fix(security): close cross-company role-check leak across all policies".
    **استثناهای مستند و آگاهانه** (این‌ها *باید* از `hasRole()` سراسری استفاده کنند،
    چون ذاتاً holding-wide هستند، نه یک شرکت مشخص):
    - `ContactPolicy` (CRM) — نمای ۳۶۰ مخاطب عمداً چندشرکتی هم‌زمان است.
    - `UserPolicy` — مدیریت کاربران/تخصیص نقش کار سطح هلدینگ است؛ اگر به یک
      شرکت مشخص مقید شود، هیچ‌کس نمی‌تواند اولین نقش یک شرکت تازه را تخصیص دهد
      (مشکل bootstrapping).
    - `ExchangeRatePolicy` — مدل `ExchangeRate` اصلاً `owner_company_id` ندارد
      (بند ۳.۵ سند طراحی: نرخ ارز مشترک بین کل هلدینگ است)، پس شرکتی برای مقیدکردن وجود ندارد.
    اگر Policy جدیدی هم واقعاً چنین استثنایی دارد، دلیلش را دقیقاً مثل این سه مورد
    در یک کامنت بالای متد مستند کن — حدس نزن.

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

### تکمیل هسته باقی‌مانده (`docs/PROJECT_00_CORE_REMAINING.md`)

- [x] Session 1: طرف‌حساب‌ها (Parties)
- [x] Session 2: ارز و نرخ ارز — `currencies`/`exchange_rates` (بدون `owner_company_id`، چون طبق طراحی
  سند مشترک بین کل هلدینگ‌اند)، `ExchangeRateResolver::rate()` (نرخ دقیق یا آخرین نرخ قبلی)،
  `RecordExchangeRate` (فقط `holding_admin`/`accountant`؛ مشاهده برای همه آزاد)، `CurrencySeeder`
  (USD/EUR/AED)، پنل `/exchange-rates` + `/exchange-rates/create`.
  **تصمیم این Session:** cast تاریخ `effective_date` عمداً `'date:Y-m-d'` است نه `'date'` خام —
  بدون فرمت صریح، مقایسه `<=` در resolver و کلید `updateOrCreate` به‌خاطر مهر زمانی اضافه در رشته
  ذخیره‌شده (`00:00:00`) شکست می‌خورد.
- [x] Session 3: سال مالی (Fiscal Periods)

  **چه ساخته شد:** `fiscal_periods` (بدون snapshot ستون‌های `created_by_user_id` — طبق
  طراحی سند، این جدول فقط `closed_by_user_id` دارد چون تنها نقش قابل‌ردیابی همان است)،
  Action های `CreateFiscalPeriod`/`CloseFiscalPeriod`، `FiscalPeriodPolicy`
  (مشاهده = هر نقشی در شرکت فعال، ساخت/بستن = فقط `holding_admin`)، پنل
  `FiscalPeriodIndex` (مسیر `/fiscal-periods`)، و `FiscalPeriodSeeder` (سال مالی جاری
  برای هر شش شرکت).

  **تصمیم‌های این Session:**
  - محاسبه محدوده (اول فروردین تا آخر اسفند، کبیسه‌آگاه) در یک متد استاتیک جدا
    `CreateFiscalPeriod::buildAttributes()` است، مستقل از `handle()` که authorize
    می‌کند — چون seeder هیچ کاربر واردشده‌ای برای عبور از Gate ندارد و نباید authorization
    واقعی را دور بزند؛ به‌جایش کلاً از مسیر Action دیگری (بدون Gate) استفاده می‌کند،
    دقیقاً مثل الگوی `CompanySeeder`/`CurrencySeeder`.
  - بستن سال مالی **بدون Action بازگشایی** است (برخلاف حقوق) — طبق طراحی سند این
    قفل عمداً یک‌طرفه است؛ اصلاح واقعی در فاز ۶ با منطق انتقال مانده اضافه می‌شود.
  - `FiscalPeriodSeeder` باید صریحاً `withoutGlobalScopes()` بزند وقتی برای
    `updateOrCreate` دنبال رکورد موجود می‌گردد — وگرنه Global Scope خودکار
    `BelongsToCompany` (که بدون کاربر واردشده `owner_company_id = null` می‌سازد)
    با شرط صریح `owner_company_id = $company->id` تناقض پیدا می‌کند و re-seed هر بار
    رکورد تکراری می‌سازد.

### ماژول HR (فاز ۲)

طبق `docs/PROJECT_02_HR.md`:

- [ ] Session 1: پرسنل (Employees)
- [x] Session 2: تقویم کاری و تعطیلات رسمی
- [x] Session 2.5: اتصال کارمند به کاربر سیستم
- [x] Session 3: حضور و غیاب (Attendance)
- [x] Session 4: جمع ماهانه کارکرد و غیبت (monthly_attendance_summaries، CalculateMonthlyAttendance، MonthlyAttendanceReport)
- [x] Session 5: مرخصی‌ها (Leave)
- [x] Session 6: حقوق و دستمزد

  **چه ساخته شد:** `payroll_runs` و `payslips` (+ منوی HR در `layouts/app.blade.php`)،
  Action های `CalculatePayroll`/`FinalizePayrollRun`، `PayrollPolicy`، پنل ادمین `PayrollIndex`
  (مسیر `/payroll`)، پنل خودِ کارمند `MyPayslips` (مسیر `/my/payslips`، فیش قابل چاپ)،
  و `PayrollRun::pendingExpensePosting()` طبق BACKLOG #1.

  **تصمیم‌های این Session که در Session های بعد باید رعایت شوند:**
  - `payslips` عمداً ستون `owner_company_id` مستقیم دارد (برخلاف نسخه اول
    `docs/schema_hr_mysql.sql`) تا `BelongsToCompany` یک‌لایه و بدون join کار کند —
    بند ۵.۱ بر نسخه اول اسکیما مقدم شد. انحراف در همان فایل مستند شده.
  - محاسبات پولی با `App\Support\Money` (bcmath روی رشته) انجام می‌شود، نه float.
    `Farsi::toToman()` هم برای همین رشته‌امن شد.
  - «همه یا هیچ» برای یک دوره: کل ساخت run + همه فیش‌ها در یک `DB::transaction`
    بیرونی واحد، بدون هیچ `try/catch` داخل حلقه.
  - قفل مالی **دولایه** است: هم در Action، هم نگهبان `updating`/`deleting` روی مدل
    `Payslip` — چون Action تنها caller نیست (بند ۹).
  - `net_amount` در صفر clamp می‌شود و مبلغ خام منفی در `raw_net_amount` می‌ماند
    (`needsManualReview()`)، با هشدار صریح در هر دو پنل.

  ⚠️ **فرمول بیمه، مالیات و مخرج نرخ روزانه موقت‌اند** — پارامترها در `config/payroll.php`،
  نیازمند تأیید حسابدار واقعی کارفرما. تا آن زمان، مخرج ثابت ۲۲ در برابر ۲۶–۲۷ روز کاری
  واقعی ماه شمسی می‌تواند خالص را منفی کند؛ دلیل وجود `raw_net_amount` همین است.

- [x] Session 6.5: اصلاحات بعد از تست دستی (حضور و غیاب، مرخصی، بازگشایی حقوق)

  **چه تغییر کرد:**
  - **پنجره ثبت گذشته‌نگر کارمند:** `config/hr.php` → `self_service_backdate_days` (پیش‌فرض ۳).
    کارمند از پنل خودش برای امروز تا N روز گذشته ثبت/ویرایش می‌کند؛ آینده همیشه رد است.
    گارد داخل `RecordAttendance` است، نه کامپوننت Livewire (بند ۹). چون همان Action هم
    ساخت و هم ویرایش را انجام می‌دهد، گارد خودکار روی ویرایش هم اعمال می‌شود.
  - **`attendances.updated_by_user_id`** اضافه شد. `recorded_by` معنایش عوض شد به
    «چه کسی **اولین بار** ثبت کرد» و دیگر با ویرایش بازنویسی نمی‌شود — وگرنه ویرایش
    ادمین روی یک رکورد `self` آن را به `admin` برمی‌گرداند و تفکیک از بین می‌رفت.
  - **`leaves.rejection_reason`** اضافه شد؛ `RejectLeave` پارامتر اختیاری گرفت. عمداً
    ستون جدا از `reason` (دلیل درخواست کارمند) است — دو نقش متفاوت.
  - **فیلتر پیش‌فرض `LeaveIndex`:** `filterStatus` از `pending` به `''` (همه) تغییر کرد.

  - **`ReopenPayrollRun` (منطق مالی، TDD):** تنها مسیر مجاز برای برداشتن قفل مالی.
    فقط از `finalized` → `draft`، با **دلیل اجباری** (حداقل ۱۰ کاراکتر)، ثبت در
    `activity_log`، و پاک‌کردن `finalized_at`/`finalized_by_user_id`.
    **بازگشت به `draft` و نه `calculated` عمدی است:** چون `FinalizePayrollRun` فقط از
    `calculated` قبول می‌کند، یک دوره بازگشایی‌شده بدون محاسبه دوباره قابل قفل‌شدن نیست.
    نگهبان مدل `Payslip` هم چون به `$run->isLocked()` نگاه می‌کند، بدون هیچ کد اضافه
    با بازگشایی باز و با نهایی‌سازی دوباره بسته می‌شود.
    ⚠️ ویرایش مستقیم فیش قفل‌شده همچنان ممنوع است — این Action جایگزین آن نیست.

- [x] Session 6.6: بازطراحی حضور و غیاب به مدل «تردد»

  **چه تغییر کرد (تغییر مدل داده — قبل از هر کار روی حضور و غیاب این را بخوان):**
  - **هر ردیف `attendances` یک تردد است، نه یک روز.** `UNIQUE(employee_id, attendance_date)`
    برداشته شد؛ کارمند می‌تواند در یک روز چند بار ورود/خروج بزند.
  - **`late_minutes`/`overtime_minutes` از `attendances` حذف شدند.** مفهومشان روزانه
    است نه ردیفی: با دو تردد چهارساعته، هر ردیف جدا با ۴۸۰ دقیقه مقایسه می‌شد و
    روزِ کاملاً کارشده ۴۸۰ دقیقه کسری می‌گرفت. حالا `App\Modules\HR\Services\AttendanceCalculator`
    در سطح **روز** محاسبه می‌کند (مجموع همه ترددهای آن روز در برابر
    `config('hr.standard_workday_minutes')`) و نتیجه فقط در
    `monthly_attendance_summaries` ذخیره می‌شود.
  - **اگر روزی حتی یک تردد باز داشته باشد، کسری/اضافه‌کاری آن روز صفر می‌ماند** —
    کارکرد روز تمام نشده و هر عددی حدس است.
  - **`open_punch_marker`** (ستون تولیدشده) + `UNIQUE(employee_id, open_punch_marker)`:
    تضمین سطح دیتابیس که هر کارمند حداکثر یک تردد باز دارد. تنها راه این تضمین در
    MySQL 8 که ایندکس یکتای شرطی ندارد.
  - **دو Action جدا:** `PunchAttendance` (خودِ کارمند، **بدون هیچ پارامتر زمان** —
    ساعت فقط از سرور) و `RecordAttendance` (ادمین، هر تاریخی، با `?Attendance $target`
    برای ویرایش یک تردد مشخص). تفکیک عمدی است: تا وقتی امضای یک متد زمان می‌گیرد،
    هر caller می‌تواند زمان دلخواه بفرستد، و آن اختیار فقط باید دست ادمین باشد.
  - **پنل خودِ کارمند هیچ ورودی تاریخ/ساعتی ندارد** — فقط دو دکمه. خصوصیت public
    در Livewire از سمت مرورگر قابل دستکاری است، پس حذف فیلد از UI کافی نبود؛
    خودِ خصوصیت هم حذف شد.
  - `config/hr.php` → `self_service_backdate_days` **حذف شد** (بی‌موضوع شد).
  - شیفت شبانه: تردد باز **بدون قید تاریخ** پیدا می‌شود، پس خروج بامداد همان تردد
    دیشب را می‌بندد. `attendance_date` روزِ **ورود** می‌ماند — یک شیفت یک رکورد.
  - خروج فراموش‌شده عمداً خودکار بسته نمی‌شود (ورود بعدی رد می‌شود تا ادمین
    رسیدگی کند): بستن خودکار یعنی ساختن ساعتی که هرگز اتفاق نیفتاده، و آن عدد
    مستقیم وارد محاسبه حقوق می‌شود.

- [x] Session 6.7: منطقه زمانی — ذخیره UTC، نمایش و روزشماری تهران

  **قاعده‌ای که از این پس همه‌جا رعایت می‌شود:**
  - `config('app.timezone')` = **`UTC`** و عوض نمی‌شود. همه لحظه‌ها UTC ذخیره
    می‌شوند (بند ۳). سرور می‌تواند هرجای دنیا باشد.
  - `config('app.display_timezone')` = **`Asia/Tehran`** (از `APP_DISPLAY_TIMEZONE`).
    فقط برای نمایش و برای تعیین اینکه یک لحظه به کدام **روز کاری** تعلق دارد.

  **ابزار مرکزی — `App\Support\Jalali`. هیچ‌جا `->format('H:i')` خام روی یک
  Carbon ذخیره‌شده ننویس:**
  | متد | کاربرد |
  |---|---|
  | `local($instant)` | لحظه UTC → همان لحظه به وقت نمایش |
  | `fromLocal($wallClock)` | ساعت دیواری که کاربر وارد کرده → لحظه UTC برای ذخیره |
  | `today()` | «امروز» به وقت محلی، نه UTC |
  | `localDateString($instant)` | یک لحظه به کدام روز تقویمی محلی تعلق دارد |
  | `calendarDay($dateColumn)` | ستون DATE (روز تقویمی، نه لحظه) در نیمه‌شب محلی |
  | `toDisplayTime` / `toDisplay` / `toDisplayDateTime` | نمایش ساعت / تاریخ شمسی / هر دو |

  **تفاوت `local()` با `calendarDay()` مهم است:** اولی یک **لحظه** را جابه‌جا
  می‌کند؛ دومی برای ستون‌های DATE است که اصلاً لحظه نیستند و نباید جابه‌جا شوند
  (وگرنه «۱ مرداد» به «۳۱ تیر ساعت ۲۰:۳۰» تبدیل می‌شود).

  **چهار باگ واقعی که همین قاعده گرفت:** نمایش ساعت با ۳:۳۰ اختلاف؛ تاریخ شمسی
  یک روز عقب نزدیک نیمه‌شب؛ `attendance_date` که ورود بامدادی را زیر روز قبل
  ثبت می‌کرد (سطح داده، نه نمایش)؛ و فرم ادمین که ساعت UTC نشان می‌داد و هر
  «اصلاحی» رویش داده درست را خراب می‌کرد. هشدار پایان قرارداد هم به مقایسه
  روز-با-روز محلی تغییر کرد.

- [x] Session 7: گزارش پایه هزینه پرسنل

  **چه ساخته شد:** `PayrollExpenseReport` (مسیر `/payroll/expense-report`، منوی «گزارش هزینه
  حقوق» زیر منابع انسانی) — جمع `net_amount` همه فیش‌های **نهایی‌شده** یک ماه، به تفکیک شرکت.

  **تصمیم‌های این Session:**
  - این گزارش عمداً **هلدینگ‌محور** است، نه شرکت‌محور: با `withoutGlobalScopes()` روی
    `PayrollRun`/`Payslip`/`Employee` همه شرکت‌ها را هم‌زمان کنار هم نشان می‌دهد (بند ۱
    CLAUDE.md — «گزارش تجمیعی هلدینگ»)، برخلاف `PayrollIndex` که فقط شرکت فعال سوییچر را
    می‌بیند. دسترسی همان `PayrollPolicy::viewAny` پنل ادمین حقوق است — رل جدیدی تعریف نشد.
  - فقط دوره‌های `finalized` وارد جمع می‌شوند؛ `draft`/`calculated` نادیده گرفته می‌شوند
    چون مبلغشان هنوز قطعی نیست.
  - فیش‌های `needsManualReview()` (خالص clamp‌شده در صفر) از جمع اصلی کنار گذاشته نمی‌شوند
    چون `net_amount` آن‌ها همین حالا صفر است — ولی تعداد و نام کارمندانشان جدا در یک
    `x-alert` هشدار داده می‌شود تا مدیر بداند جمع گزارش ممکن است کامل نباشد.
  - **موقتی است** (طبق BACKLOG.md #1): فقط نمایش، هیچ رکورد `expense` نمی‌سازد؛ در فاز ۴
    با `PostPayrollToExpenses` واقعی جایگزین می‌شود.

### ماژول CRM (فاز جدید — طبق `docs/DECISIONS.md`، منتقل‌شده زودتر از فاز ۶ اصلی سند)

- [x] Session 1: مخاطبین (Contacts)

  **چه ساخته شد:** `contacts` (Golden Record هلدینگی — عمداً بدون `owner_company_id`،
  مثل `Holiday` در HR) و `contact_site_profiles` (`BelongsToCompany`، `UNIQUE(contact_id, owner_company_id)`)،
  سرویس `ContactMatcher::findOrCreateContact()` (تطبیق بر پایه موبایل یا ایمیل)،
  Action `CreateContactSiteProfile` (authorize داخل خودِ Action)، دو Policy جدا —
  `ContactSiteProfilePolicy` (سطح شرکت) و `ContactPolicy` (سطح هلدینگ، محدودتر:
  فقط `holding_admin`/`accountant`، نه `operator`)، و کامپوننت‌های `ContactIndex`/
  `ContactForm`/`ContactProfile` (نمای ۳۶۰، مسیر `/contacts/{contactId}/profile`).

  **تصمیم‌های این Session:**
  - امضای `ContactMatcher::findOrCreateContact()` نسبت به تعریف اولیه سند دو
    پارامتر اضافه گرفت: `fullName` (چون `contacts.full_name` ستون اجباری است و
    بدون آن نمی‌شد Contact جدید ساخت) و `User $actor` به‌جای `companyId` خام
    (برای `created_by_user_id`؛ چون خودِ جدول `contacts` اصلاً `owner_company_id`
    ندارد، پارامتر شرکت در سطح تطبیق مخاطب بی‌معنا بود).
  - نمای ۳۶۰ هلدینگی عمداً **Policy جدا** دارد، نه استفاده از همان
    `ContactSiteProfilePolicy` با یک متد اضافه — چون دامنه دسترسی متفاوت است
    (چندشرکتی هم‌زمان، نه فقط شرکت فعال سوییچر) و باید بتواند مستقل‌تر محدود شود.
  - در مسیر Livewire، پارامتر `mount()` عمداً `contactId` نام‌گذاری شد نه `contact`
    — همنام‌بودن با یک public property تایپ‌شده (`public Contact $contact`) باعث
    می‌شود Livewire پیش از اجرای `mount()` سعی کند مقدار خام رشته‌ای route را
    مستقیم روی همان property بنشاند و با خطای type mismatch شکست بخورد.

  **اصلاح بعد از Session (دو مرحله):**
  1. دسترسی `ContactSiteProfilePolicy` (مشاهده/ساخت/ویرایش) از «هر نقشی در
     شرکت + holding_admin/accountant/operator برای ساخت» به فقط
     `holding_admin`/`operator` محدود شد — کار `accountant` با `Party`
     (طرف‌حساب مالی) است، نه `Contact`؛ `viewer` هم اساساً دسترسی مدیریتی ندارد.
  2. همین استدلال به `ContactPolicy` سطح هلدینگ هم تسری داده شد (که در مرحله ۱
     فراموش شده بود): از `holding_admin`/`accountant` به `holding_admin`/`operator`
     تغییر کرد. علت جدابودن این دو Policy («شرکت جاری» در برابر «چندشرکتی
     هم‌زمان») همچنان پابرجاست، فقط مجموعه نقش مجازشان حالا یکی است.

- [x] Session 2: تعاملات (Interactions)

  **چه ساخته شد:** `interactions` (فقط `created_at`، بدون `updated_at` —
  تعامل ثبت‌شده ویرایش نمی‌شود؛ `source_order_id` بدون FK با کامنت TODO فاز ۳
  طبق `docs/schema_crm_mysql.sql`)، مدل `Interaction` (با
  `Interaction::createFromOrder()` فقط امضا+TODO برای اتصال آینده خرید)،
  Action `RecordInteraction` (authorize داخل خودِ Action، فقط انواع دستی
  `call`/`telegram`/`site_form` — نه `purchase`)، `InteractionPolicy`، و
  کامپوننت `InteractionTimeline` (تایم‌لاین + فرم ثبت دستی، جاسازی‌شده در
  همان صفحه پروفایل ۳۶۰ `ContactProfile`).

  **تصمیم‌های این Session:**
  - `InteractionPolicy` برخلاف `ContactSiteProfilePolicy` به `CompanyContext`
    فعال سوییچر تکیه **نمی‌کند** — چون از نمای ۳۶۰ هلدینگی می‌شود برای هر
    پروفایل سایتِ این مخاطب (در هر شرکتی) تعامل ثبت کرد، نه فقط شرکت فعال
    session. به‌جایش `isAuthorizedForCompany($user, $profile->owner_company_id)`
    شرکت هدف را از خودِ `ContactSiteProfile` می‌خواند، نه از session جاری.
    همان دو نقش (`holding_admin`/`operator`) رعایت شده، فقط منبع تشخیص شرکت فرق دارد.
  - `InteractionTimeline` هم مثل `ContactProfile::getSiteProfilesProperty()`
    از `withoutGlobalScopes()` استفاده می‌کند — تایم‌لاین باید تعاملات همه
    شرکت‌های این مخاطب را کنار هم نشان بدهد، نه فقط شرکت فعال.
  - زمان تعامل دستی (`occurred_at`) عمداً از **سرور** گرفته می‌شود
    (`now()`)، نه از یک ورودی تاریخ/ساعت در فرم — مثل الگوی `PunchAttendance`
    در HR (بند ۹؛ ساعت قابل‌دستکاری فقط باید دست ادمین/Action مشخص باشد،
    نه هر فرم ثبت دستی).
  - تبدیل خودکار خرید→تعامل عمداً پیاده نشد؛ در `docs/BACKLOG.md` («تبدیل
    خودکار خرید به تعامل») از قبل ثبت بود، وابسته به ماژول Sales (فاز ۳).

- [x] Session 3: قیف فروش (Lead)

  **چه ساخته شد:** `leads` (طبق جدول ۴ سند، `contact_site_profile_id` nullable —
  لید می‌تواند بدون مخاطب کامل باشد؛ `contract_id` بدون FK با کامنت TODO فاز ۵)،
  مدل `Lead` با ثابت‌های `SOURCES`/`PIPELINE_STAGES` و نقشه `TRANSITIONS`،
  Action های `CreateLead`/`UpdateLeadStage`/`AssignLead` (authorize داخل خودِ
  Action با متد مرکزی `hasRoleInCompany`)، `LeadPolicy` (همان دو نقش
  `holding_admin`/`operator` بقیه CRM)، و کامپوننت `LeadBoard` (نمای قیف،
  مسیر `/leads`، ستون به‌ازای هر مرحله + فرم ساخت + دکمه تغییر مرحله + تخصیص).

  **تصمیم‌های این Session:**
  - ماشین وضعیت قیف (بند ۶ CLAUDE.md) صریح تعریف شد: `new→contacted→qualified
    →proposal→won` رو-به-جلو یک‌به‌یک، به‌علاوه «باخت» از هر مرحله فعال؛
    `won`/`lost` پایانی‌اند و بدون Action بازگشایی — مثل بستن سال مالی
    (بند ۲ تکمیل هسته). هر ترنزیشن تعریف‌نشده در `UpdateLeadStage` با
    `InvalidArgumentException` رد می‌شود.
  - `LeadBoard` برخلاف `ContactProfile`/`InteractionTimeline` هلدینگ‌محور
    **نیست** — مثل `PayrollIndex` فقط شرکت فعال سوییچر را نشان می‌دهد، چون
    مدیریت قیف کار همان شرکت است.
  - اتصال «لید برد شد → قرارداد» طبق درخواست صریح کارفرما ساخته **نشد** —
    از قبل در `docs/BACKLOG.md` («اتصال «لید برد شد → قرارداد» برای آرشامان»)
    ثبت بود.

- [x] RFM

  **چه ساخته شد:** `rfm_segments` (طبق جدول ۵ سند، بدون `created_by_user_id`/
  `updated_by_user_id` و بدون `created_at`/`updated_at` — این رکورد همیشه از
  محاسبه خودکار می‌آید، نه ورود دستی؛ تنها مهر زمانی معنادار `calculated_at`
  است)، مدل `RfmSegment` با `classify()` (آستانه‌ها در `config/crm.php`، نه
  hardcode — چون طبق هشدار صریح کارفرما موقتی‌اند)، Action
  `CalculateRfmSegment` (authorize داخل خودِ Action با متد مرکزی
  `hasRoleInCompany`)، `RfmSegmentPolicy` (همان دو نقش
  `holding_admin`/`operator` بقیه CRM)، و کامپوننت `RfmSegmentIndex` (مسیر
  `/rfm-segments`، فهرست شرکت جاری به تفکیک segment + دکمه «محاسبه دوباره»).

  **تصمیم‌های این Session:**
  - چون `interactions` هیچ ستون مبلغی ندارد، `monetary_amount` از
    `contact_site_profiles.total_purchase_amount` خوانده می‌شود، نه از جمع
    تعاملات purchase — تنها منبع مبلغ موجود همان است (طبق طراحی سند، فعلاً
    صفر تا سفارش واقعی فاز ۳). **مهم:** چون این ستون تا فاز ۳ هیچ‌جا
    به‌روزرسانی نمی‌شود و همیشه `DEFAULT 0` پایگاه‌داده می‌ماند،
    `CalculateRfmSegment` وقتی خرید دستی ثبت شده ولی این ستون هنوز صفر است،
    عمداً `monetary_amount = null` ذخیره می‌کند (نه صفر) — وگرنه یک مشتری با
    چند خرید دستی‌ثبت‌شده در UI «۰ تومان خرج کرده» نشان داده می‌شود که
    گمراه‌کننده است، نه فقط ناکامل. بعد از فاز ۳ که این ستون واقعاً پر
    می‌شود، شرط `> 0` در `CalculateRfmSegment::handle()` دیگر لازم نیست.
  - قاعده دسته‌بندی ساده‌شده است (۳ آستانه در `config/crm.php`): گذشت
    `dormant_days` از آخرین خرید → غیرفعال (صرف‌نظر از تعداد خرید)؛ تازگی زیر
    `at_risk_days` و تعداد خرید >= `vip_min_frequency` → ویژه؛ بقیه (شامل
    خرید کم اما تازه) → در معرض ریزش. هیچ حالتی به `new` نمی‌رسد مگر پروفایل
    اصلاً تعامل purchase نداشته باشد.
  - `RfmSegmentIndex` مثل `LeadBoard` شرکت‌محور است (نه هلدینگ‌محور مثل
    `ContactProfile`)، چون `rfm_segments` هم `owner_company_id` مستقل دارد.
  - `CalculateRfmSegment` با `updateOrCreate` + `withoutGlobalScopes()` کار
    می‌کند (نه `create` ساده مثل `RecordInteraction`) چون UNIQUE
    `contact_site_profile_id` یعنی هر پروفایل حداکثر یک رکورد دارد و
    بازمحاسبه باید همان را به‌روزرسانی کند، مستقل از شرکت فعال سوییچر.
  - ⚠️ هشدار دقت («این بخش‌بندی بر پایه تعاملات دستی‌ثبت‌شده است») مستقیم در
    UI پنل چاپ می‌شود — طبق الگوی هشدارهای موقتی Payroll (بند Session 6 ماژول HR).

- [ ] کمپین (Campaign)
- [ ] تیکتینگ (Ticket)

### ماژول Catalog (Epic 5)

- [x] Session 1: محصولات (Products)

  **چه ساخته شد:** `product_categories` (جدول ساده، `BelongsToCompany`، بدون
  CRUD/UI این Session — فقط FK واقعی برای آینده) و `products`
  (`owner_company_id`, `category_id` nullable FK به `product_categories`,
  `sale_price` DECIMAL(18,2), `cost_price` DECIMAL(18,2) nullable,
  `currency_id` nullable FK به `currencies` — خالی یعنی تومان، `fulfillment_type`
  VARCHAR(20) + CHECK دیتابیس (`physical`/`digital`/`service`)،
  `woocommerce_product_id` nullable، `is_active`)، enum PHP
  `App\Modules\Catalog\Enums\FulfillmentType`، `ProductPolicy` (مشاهده = هر
  نقشی در شرکت، ساخت/ویرایش = holding_admin/accountant/operator — دقیقاً الگوی
  `PartyPolicy`)، Action های `CreateProduct`/`UpdateProduct` (authorize داخل
  خودِ Action)، و کامپوننت‌های `ProductIndex`/`ProductForm` (مسیر `/products`،
  فیلتر fulfillment_type + جستجوی نام + badge هشدار «بهای تمام‌شده نامشخص»).

  **تصمیم‌های این Session:**
  - `fulfillment_type` در سطح محصول است نه شرکت، طبق بند ۵.۳ CLAUDE.md — حتی
    برای Verifex که هر دو نوع می‌فروشد.
  - `cost_price` عمداً nullable است و هرگز صفر فرض نمی‌شود: متد
    `Product::needsCostReview()` هم در فهرست هم در فرم یک badge هشدار صریح
    نشان می‌دهد، دقیقاً طبق درخواست کارفرما (این عدم‌قطعیت نباید محو شود، باید
    دیده شود).
  - `currency_id` nullable طبق الگوی معماری چندارزی موجود (`exchange_rates`):
    عدم وجود مقدار یعنی ارز پایه هلدینگ (تومان)، نه یک ردیف واقعی در
    `currencies`.
  - `product_categories` یک جدول واقعی ساخته شد (نه فقط ستون بی‌FK) تا یکپارچگی
    مرجع از همین Session تضمین شود؛ CRUD/UI مدیریت دسته‌بندی در `docs/BACKLOG.md`
    ثبت شد، چون خارج از تعریف این Session بود.
  - منطق محاسبه سود/تسهیم هزینه به این Session مربوط نیست (فقط ذخیره‌سازی
    محصول) — مرور کد مالی بند ۲ رویه بستن Session لازم نبود.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): CRUD دسته‌بندی، انبار
(Inventory)، سفارش‌ها (Sales)، اتصال واقعی ووکامرس.

### ماژول Inventory (فاز ۱ — Epic 7: انبار پایه)

- [x] Session 1: انبار پایه (Warehouse/Stock/StockMovement)

  **چه ساخته شد:** `app/Modules/Inventory` (ماژول جدا، نه زیر Catalog).
  `warehouses` (بدون `owner_company_id` — طبق بند ۵.۸ CLAUDE.md انبار فیزیکاً
  بین شرکت‌ها مشترک است، همان الگوی `contacts`/`holidays`؛ مدل بدون
  `BelongsToCompany`)، `stocks` (`owner_company_id` + `BelongsToCompany`،
  UNIQUE روی `product_id`+`warehouse_id`+`owner_company_id`)، `stock_movements`
  (snapshot `owner_company_id` از stock در لحظه ثبت، `movement_type`
  VARCHAR(10)+CHECK `in`/`out`/`adjust`)، migration اصلاحی
  `products.reorder_point` (nullable، مثل الگوی `cost_price` — عدم قطعیت
  صفر فرض نمی‌شود). Action های `ReceiveStock`/`IssueStock` (تنها مسیر مجاز
  تغییر `quantity`، هر دو در `DB::transaction` با `lockForUpdate`)،
  `WarehousePolicy` (مشاهده آزاد، مدیریت فقط `holding_admin` — استثنای
  مستند بند ۱۱ چون Warehouse اصلاً owner_company ندارد)، `StockPolicy`
  (مشاهده = هر نقشی در شرکت، مدیریت = holding_admin/accountant/operator،
  همان الگوی `ProductPolicy`)، کامپوننت‌های `StockIndex`/`StockMovementForm`/
  `LowStockReport` (مسیرهای `/inventory/stock`, `/inventory/receive`,
  `/inventory/issue`, `/inventory/low-stock-report`)، `WarehouseSeeder`
  (یک انبار مرکزی هلدینگ).

  **تصمیم‌های این Session:**
  - `IssueStock` موجودی منفی را رد می‌کند (`InvalidArgumentException`) —
    قید کسب‌وکاری منطقی، نه فقط ذخیره خام.
  - `movement_type=adjust` فقط سطح CHECK رزرو شده؛ هیچ Action ای در این
    Session آن را نمی‌سازد (طبق scope صریح کارفرما).
  - منوی «انبار» (placeholder قبلی `link="#"`) با یک زیرمنوی واقعی جایگزین
    شد، دقیقاً با شرط `hasRoleInCompany` هم‌راستا با `StockPolicy::viewAny`
    (همان اصلاح سراسری هماهنگی منو با Policy واقعی).
  - `reorder_point` هم به `ProductForm`/مدل `Product` اضافه شد تا از همان
    فرم محصول قابل تنظیم باشد (فیلد داده بدون UI تنظیم، بی‌فایده بود).
  - تست پایگاه‌داده CHECK با `Schema::getConnection()->getDriverName() ===
    'sqlite'` skip می‌شود (محیط تست sqlite است)؛ روی MySQL واقعی دستی تأیید
    شد (`php artisan migrate --force` روی `arshaman_erp` بدون خطا).
  - بازدید بصری با یک کاربر تستی موقت (`inventory-visual-test@example.com`)
    انجام و در پایان کامل حذف شد — طبق بند ۱۰ CLAUDE.md (هرگز رمز کاربر
    واقعی تغییر نمی‌کند).

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): اتصال واقعی به
سفارش (خروج خودکار از انبار)، Action برای `movement_type=adjust`، CRUD
کامل Warehouse (ساخت/ویرایش چند انبار از UI).

### اصلاح سراسری: هماهنگی شرط نمایش منو با Policy واقعی صفحه

طبق همان استثنای بند ۹ برای اصلاحات امنیتی/UX سراسری (bypass قانون
یک-ماژول-در-هر-Session)، این اصلاح روی `layouts/app.blade.php` بود، نه یک
ماژول مشخص.

**باگ:** چند آیتم منوی سایدبار با شرطی شل‌تر از Policy واقعی همان صفحه نمایش
داده می‌شدند — کاربر آیتم منو را می‌دید ولی با کلیک ۴۰۳ می‌گرفت:
- منوی «مخاطبین» (مخاطبین/قیف فروش/RFM) با `hasRoleInCompany($id)` بدون فهرست
  نقش (یعنی «هر نقشی در شرکت») نمایش داده می‌شد، در حالی که
  `ContactSiteProfilePolicy`/`LeadPolicy`/`RfmSegmentPolicy::viewAny` همه فقط
  `holding_admin`/`operator` را قبول می‌کنند — یک `viewer` یا `accountant`
  آیتم را می‌دید و ۴۰۳ می‌گرفت.
- منوی «منابع انسانی» با `hasRole('holding_admin') || hasRole('accountant')`
  **سراسری** (نه `hasRoleInCompany` مقید به شرکت فعال) نمایش داده می‌شد — دقیقاً
  همان الگوی نشتی نقش بند ۹/۱۱ (کاربری که `accountant` شرکت دیگری بود، منو را
  در این شرکت هم می‌دید). `EmployeePolicy`/`AttendancePolicy`/`LeavePolicy`/
  `PayrollPolicy::viewAny` همه مقید به شرکت فعال‌اند.

**قانون رعایت‌شده از این پس:** شرط نمایش هر آیتم منو باید **دقیقاً** همان
Policy/متد `viewAny` صفحه مقصد را با `hasRoleInCompany($activeCompany->id, [...])`
تکرار کند — نه یک تقریب («هر نقشی در شرکت») و نه `hasRole()` سراسری. اگر این
دو از هم جدا بیفتند، تشخیصش سخت است چون فقط با کلیک کاربر بدون نقش درست دیده
می‌شود، نه در تست‌های خودِ آن صفحه.

**بخش‌های ممیزی‌شده که از قبل درست بودند (بدون تغییر):**
- «مدیریت کاربران»: `is_super_admin || hasRole('holding_admin')` سراسری —
  عمداً همینطور، چون `UserPolicy::viewAny` هم دقیقاً همین‌ها را holding-wide
  می‌پذیرد (استثنای مستند بند ۱۱).
- «طرف‌حساب‌ها»/«سال‌های مالی»: `hasRoleInCompany($activeCompany->id)` بدون
  فهرست نقش — چون `PartyPolicy`/`FiscalPeriodPolicy::viewAny` هم واقعاً «هر
  نقشی در شرکت» را قبول می‌کنند.
- «نرخ ارز»: بدون شرط نقش — چون `ExchangeRatePolicy::viewAny` عمداً `true`
  است (مشاهده آزاد برای همه، طبق تصمیم سند).

**«پنل من» (self-service):** قبلاً برای *هر* کاربر لاگین‌شده نشان داده
می‌شد. حالا فقط برای کاربری که یک `Employee` مرتبط دارد
(`employees.user_id === auth()->id()`, `withoutGlobalScopes` چون کارمند
ممکن است متعلق به شرکتی غیر از شرکت فعال باشد) — چون self-service طبق طراحی
سند HR به نقش کسب‌وکاری وابسته نیست، فقط به داشتن پرونده پرسنلی.

**تست:** `tests/Feature/CRM/CrmNavigationTest.php` (جدید) و
`tests/Feature/HR/HrNavigationTest.php` (به‌روزشده — تست self-service قبلی
که فرض می‌کرد «هر کاربر لاگین‌شده» را می‌بیند جایگزین شد، به‌علاوه یک تست
نشتی نقش بین‌شرکتی جدید).

### ماژول Blog (جدید — طبق درخواست صریح کارفرما)

- [x] Session 1: دسته‌بندی، برچسب، پست با گردش کار انتشار محدود به نقش

  **چه ساخته شد:** `app/Modules/Blog` (`BlogCategory`/`BlogTag`/`BlogPost`،
  همه `BelongsToCompany`، پیوت `blog_post_tag` بدون audit مستقل)، enum PHP
  `BlogPostStatus` (draft/scheduled/published/archived)، `post_status` عمداً
  نه `status` خام (بند ۳ `DATABASE_CONVENTIONS.md`)، CHECK دوگانه دیتابیس
  (`chk_blog_posts_status` + `chk_blog_posts_scheduled_needs_date`: scheduled
  بدون `published_at` رد می‌شود). `BlogPostPolicy::canPublish()` متد جدا از
  `update()` — فقط `holding_admin`. Action های `CreateBlogPost`/`UpdateBlogPost`
  عمداً silent coercion دارند نه Exception: برای operator، `author_user_id`
  و `post_status` هرچه در ورودی باشد نادیده گرفته می‌شود (خودکار خودش/draft) —
  چون Policy از قبل تضمین کرده این مسیر فقط برای پست‌های از قبل draft خودِ
  operator باز است. کامپوننت‌های `BlogCategoryIndex`/`Form`،
  `BlogTagIndex`/`Form`، `BlogPostIndex`/`Form` (مسیر `/blog/posts`,
  `/blog/categories`, `/blog/tags`).

  **تصمیم‌های این Session:**
  - **اولین الگوی آپلود فایل پروژه:** `WithFileUploads` لایوایر (نه
    medialibrary که در CLAUDE.md مجاز است ولی تا امروز هیچ‌جا واقعاً وصل
    نشده بود) — ذخیره در دیسک `public` مسیر `blog/featured-images/`،
    `storage:link` این Session اجرا شد (قبلاً وجود نداشت).
  - **اولین الگوی اسلاگ پروژه:** `Str::slug()` با fallback به
    `Str::slug(Str::random(8))` اگر عنوان کاملاً غیرلاتین بود و رشته خالی
    برگرداند؛ در عمل `Str::slug()` عناوین فارسی را هم ترجمه آوایی می‌کند
    (نه همیشه خوانا)، پس فیلد همیشه قابل ویرایش دستی ماند. یکتایی در سطح
    شرکت با `Rule::unique(...)->where('owner_company_id', ...)->ignore(...)`
    روی هر سه فرم (دسته‌بندی/برچسب/پست).
  - `BlogCategoryPolicy`/`BlogTagPolicy::canManage` عمداً بدون `accountant`
    (برخلاف `ProductPolicy`) — مدیریت taxonomy محتوایی است نه مالی؛ تصمیم
    این Session، نه دستور صریح کارفرما.
  - زمان‌بندی انتشار از `x-jalali-date-select` + یک `x-input type="time"`
    جدا ترکیب می‌شود، با `Jalali::fromLocal()` به UTC تبدیل می‌شود — الگوی
    دقیق `ExchangeRateForm`، به‌علاوه ساعت که آنجا لازم نبود.
  - `content_blocks` این Session فقط یک آرایه یک‌آیتمی `[{type: paragraph,
    text}]` از یک textarea ساده است؛ `content_html` همیشه `null` می‌ماند —
    عمداً جای‌گیر تا Session بعد با Editor.js واقعی.
  - منوی «وبلاگ» بدون محدودیت `business_type` (برخلاف انبار) — محتوای
    وبلاگ برای هر پنج مجموعه معنا دارد؛ شرط دقیقاً `BlogPostPolicy::viewAny`.
  - بازدید بصری کامل (دسته‌بندی → برچسب → پست با انتخاب برچسب چندتایی +
    زمان‌بندی شمسی) با کاربر تستی موقت `blog-visual-test@example.com` انجام
    و در پایان کامل حذف شد (بند ۱۰ CLAUDE.md).

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): ادیتور Editor.js
واقعی، رندر HTML سمت سرور، صفحه عمومی وبلاگ، زمان‌بندی خودکار انتشار
(cron/job که وقتی `published_at` رسید status را به `published` تغییر دهد).

- [x] Session 2: ادیتور بلوکی واقعی (Editor.js) + رندر امن سمت سرور

  **چه ساخته شد:** نصب `@editorjs/editorjs` + پلاگین‌های `header`/`list`/`quote`/
  `image` از npm (نه CDN — سازگار با build فعلی Vite)، `resources/js/editor.js`
  (`window.initBlogEditor`/`window.saveBlogEditor`، پل بین Alpine/Livewire و
  نمونه Editor.js با `wire:ignore`)، کنترلر جدید (نه Livewire — چون Editor.js
  Image tool یک درخواست HTTP مستقل multipart می‌زند، نه از طریق `wire:model`)
  `EditorImageUploadController` (مسیر POST `/blog/editor-image-upload`،
  authorize با همان `BlogPostPolicy::create`)، و سرویس جدید
  `App\Modules\Blog\Services\BlockContentRenderer` که `content_blocks` را با
  whitelist انواع بلوک (`paragraph`/`header`/`list`/`quote`/`image`) به HTML
  امن تبدیل می‌کند. `CreateBlogPost`/`UpdateBlogPost` حالا خودشان
  `content_html` را از این سرویس پر می‌کنند (نه فرم) — طبق بند ۹ CLAUDE.md.

  **تصمیم‌های این Session:**
  - **کشف حین بازدید بصری واقعی (نه فقط تست Unit با داده دستی):** نسخه فعلی
    `@editorjs/list` هر آیتم را به‌صورت شیء تودرتو `{content, items, meta}`
    برمی‌گرداند، نه رشته ساده — اگر `BlockContentRenderer::renderList()` فقط
    `is_string` را می‌پذیرفت (نسخه اول پیاده‌سازی)، **هر لیست واقعی از UI به‌طور
    خاموش حذف می‌شد**، چون هیچ آیتمی رشته خام نبود. اصلاح شد: هم رشته ساده هم
    شیء با کلید `content` پذیرفته می‌شود؛ زیرلیست‌های تودرتو (`items` داخلی)
    عمداً نادیده گرفته می‌شوند (خارج از scope این Session). **درس: تست Unit با
    فیکسچر دستی کافی نیست وقتی فرمت خروجی واقعی یک کتابخانه خارجی را
    مفروض می‌گیری — باید حتماً یک‌بار خروجی واقعی کتابخانه را از مرورگر خواند.**
  - امنیت XSS در دو لایه: (۱) whitelist نوع بلوک (بلوک ناشناخته silent skip)،
    (۲) `strip_tags()` روی متن با تگ‌های اینلاین مجاز محدود
    (`<b><strong><i><em><u>`, **بدون `<a>`** — لینک داخل متن عمداً خارج از
    scope تا نیاز به sanitize کردن `href` نباشد). **نکته حیاتی کشف‌شده:**
    `strip_tags()` در PHP روی تگ‌های whitelist‌شده attributeها را حذف
    **نمی‌کند** (یک gotcha شناخته‌شده PHP) — یعنی `<b onclick="evil()">` بدون
    اصلاح اضافه از همان whitelist رد می‌شد. یک `preg_replace` اضافه شد که
    attribute هر تگ مجاز را پاک می‌کند. تست Unit این حالت را صریح پوشش می‌دهد.
  - `url` تصویر با `e()` escape می‌شود (نه `strip_tags`، چون URL است نه متن
    غنی)؛ کپشن خالی (`''`) دیگر یک `<figcaption></figcaption>` خالی تولید
    نمی‌کند (چک `trim() !== ''` اضافه شد بعد از مشاهده مستقیم HTML واقعی).
  - بازدید بصری کامل با کاربر تستی موقت (`blog-editor-visual-test@example.com`،
    رمز موقت set شد چون کاربر تازه ساخته شده بود نه یک کاربر واقعی — طبق بند ۱۰
    CLAUDE.md) در فرم واقعی (نه فقط تست خودکار): افزودن بلوک پاراگراف/تیتر/
    لیست/نقل‌قول، ذخیره، ویرایش مجدد (بارگذاری صحیح `content_blocks` موجود در
    ادیتور)، و فراخوانی مستقیم endpoint آپلود تصویر — همه با موفقیت. کاربر و
    پست تستی و فایل آپلودشده در پایان کامل حذف شدند.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): صفحه عمومی نمایش
وبلاگ (مصرف‌کننده `content_html`)، زیرلیست‌های تودرتو در رندر، زمان‌بندی
خودکار انتشار.

- [x] Session 3: جایگزینی Editor.js با Quill (WYSIWYG) + sanitize با mews/purifier

  **چه تغییر کرد:** بازخورد کاربر بعد از استفاده واقعی از Editor.js (Session ۲):
  تجربه بلوک‌به‌بلوک کند و ضدشهودی بود، کاربر یک ادیتور معمولی مثل Word
  می‌خواست. Editor.js و همه `@editorjs/*` npm حذف شدند؛ `quill` جایگزین شد
  (`resources/js/editor.js` بازنویسی کامل — همان نام‌های تابع
  `window.initBlogEditor`/`saveBlogEditor` حفظ شد تا Blade دست‌نخورده بماند،
  فقط آرگومان دوم از آرایه بلوک به رشته HTML خام تغییر کرد). ستون
  `content_blocks` (JSON مخصوص Editor.js) با migration جدید (نه ویرایش
  migration قدیمی — بند ۹.۷) حذف شد؛ `content_html` تنها منبع محتواست.
  `BlockContentRenderer` (سرویس Session ۲) کاملاً منسوخ و حذف شد؛ چون خروجی
  این‌بار HTML خام است نه JSON ساختاریافته، `strip_tags` کافی نبود — به‌جایش
  `mews/purifier` (روی `ezyang/htmlpurifier`) با whitelist دقیق در
  `config/purifier.php` (`p,h1-h6,strong,em,u,s,a[href|target|rel],
  img[src|alt|width|height],blockquote,ul,ol,li[data-list],br` + محدودیت
  `URI.AllowedSchemes` به `http/https/mailto`).

  **تصمیم‌های این Session:**
  - **انحراف آگاهانه از محل پیشنهادی کارفرما:** درخواست گفته بود sanitize در
    `save()` کامپوننت Livewire انجام شود؛ به‌جایش داخل خودِ
    `CreateBlogPost`/`UpdateBlogPost` گذاشته شد (`Purifier::clean()` قبل از
    ذخیره) — دقیقاً طبق بند ۹ CLAUDE.md و الگوی همین ماژول در Session ۲.
    **دلیل عملی:** تست‌های `BlogPostTest.php` مستقیم Action را بدون عبور از
    کامپوننت صدا می‌زنند؛ اگر sanitize فقط در Livewire بود، این مسیر (و هر
    caller آینده) HTML خام و ناامن ذخیره می‌کرد.
  - **کشف حین بازدید بصری واقعی (نه فرض از مستندات):** Quill 2 هر دو نوع
    لیست را به‌صورت `<ol><li data-list="bullet|ordered">` تولید می‌کند، نه
    `<ul>`/`<ol>` جدا. whitelist اولیه (دقیقاً طبق متن درخواست کارفرما) این
    attribute را نداشت، پس بعد از sanitize هر لیستی (بولت یا شماره‌دار) به
    `<ol><li>` ساده تبدیل می‌شد و تفاوتشان محو می‌شد — کشف شد فقط چون واقعاً
    یک لیست بولت در مرورگر ساخته و ذخیره شد، نه در تست واحد با HTML دستی.
    رفع شد با افزودن `li[data-list]` به `HTML.Allowed` + یک
    `custom_attributes` entry (`Enum#ordered,bullet,checked,unchecked`) —
    چون `data-list` یک attribute غیراستاندارد است و بدون تعریف صریح در
    HTMLPurifier، حتی با ذکر در `HTML.Allowed` هم پذیرفته نمی‌شد. **درس
    تکراری از Session ۲ (فرمت واقعی Editor.js list) دوباره تأیید شد: فرمت
    خروجی واقعی یک کتابخانه خارجی را هرگز از مستندات/انتظار فرض نکن، حتماً
    یک‌بار در مرورگر واقعی تولید و ذخیره‌اش کن.**
  - آپلود تصویر از همان endpoint/کنترلر Session ۲
    (`EditorImageUploadController`, مسیر `blog.editor-image-upload`) بدون
    تغییر استفاده شد — فقط handler سمت کلاینت روی دکمه `image` نوار ابزار
    Quill عوض شد.
  - بازدید بصری کامل با کاربر تستی موقت (`blog-quill-visual-test@example.com`)
    شامل تایپ پیوسته، Bold، Header (دراپ‌داون H1-H6)، Link (با tooltip
    داخلی Quill)، Bullet List، و آپلود واقعی تصویر — ذخیره و بارگذاری مجدد
    هر دو تأیید شد. کاربر، پست، و فایل‌های آپلودشده در پایان کامل حذف شدند.

- [x] Session 4: صفحه عمومی وبلاگ (guest) + زمان‌بندی خودکار انتشار

  **چه ساخته شد:** دو route عمومی بدون middleware auth —
  `GET /blog/{companySlug}` و `GET /blog/{companySlug}/{postSlug}` (عمداً
  *بعد از* route‌های ثابت ادمین `/blog/posts`, `/blog/categories`, `/blog/tags`
  ثبت شدند تا این segmentهای ثابت به‌اشتباه `{companySlug}` تفسیر نشوند) —
  `PublicBlogController` (Controller + Blade ساده، نه Livewire: کنترل کامل
  روی `<head>` برای متا تگ سئو لازم بود و هیچ تعامل reactive نیاز نبود)،
  scope جدید `BlogPost::scopePublished()` (`post_status=published AND
  published_at<=now()`)، accessor `display_reading_time` (fallback تخمین
  ۲۰۰ کلمه/دقیقه وقتی `reading_time_minutes` خالی است)، layout عمومی جدید
  `layouts/public.blade.php` (متا تگ‌های سئوی واقعی از طریق `@yield`، نه
  `layouts/guest.blade.php` موجود که مخصوص فرم تک‌ستونی contact-us است)،
  `resources/views/public/blog/{index,show}.blade.php`. برای زمان‌بندی خودکار:
  Action جدید `PublishScheduledPost` (بدون actor/Gate — پایین توضیح داده شده)،
  Artisan command `blog:publish-scheduled`، و اولین ثبت `Schedule::` پروژه در
  `routes/console.php` (`->everyMinute()`).

  **تصمیم‌های این Session:**
  - ایزولاسیون شرکت برای مهمان با `withoutGlobalScopes()` + فیلتر صریح
    `owner_company_id` انجام می‌شود (نه تکیه بر `BelongsToCompany`/`CompanyContext`
    که برای مهمان بی‌معناست) — همان الگوی `ContactProfile`/`RfmSegmentIndex`.
    روی `show()`، `published()` و فیلتر شرکت هر دو **قبل از** `firstOrFail()`
    اعمال می‌شوند، نه به‌عنوان یک چک جدا بعد از یافتن رکورد — یعنی پست
    scheduled/draft/archived یا متعلق به شرکت دیگر مستقیماً ۴۰۴ می‌دهد، بدون
    فاصله‌ای که نشتی داده در آن ممکن باشد.
  - **`PublishScheduledPost` عمداً بدون پارامتر `actor` و بدون
    `Gate::authorize` ساخته شد** — برخلاف بقیه Action های ماژول Blog. تنها
    caller این Action یک فرآیند سیستمی زمان‌بندی‌شده است، نه یک کاربر؛ الگویش
    دقیقاً `CreateFiscalPeriod::buildAttributes()`/`CompanySeeder` (بند
    «تکمیل هسته Session ۳») است: وقتی کاربری برای authorize کردن وجود ندارد،
    از مسیر Gate/Policy عبور نمی‌کنیم، نه اینکه یک actor جعلی (مثلاً اولین
    holding_admin) بسازیم — آن کار در activity_log به‌اشتباه به نام یک کاربر
    واقعی ثبت می‌شد.
  - **اولین استفاده `causedBy(null)` در کل پروژه.** `PublishScheduledPost`
    فعالیت را با causer خالی (`null`) ثبت می‌کند — الگوی جدید «system-caused
    activity» برای هر فرآیند زمان‌بندی‌شده آینده (مثل انتقال مانده حقوق فاز
    ۶). تست مستقیم چک می‌کند `Activity::causer` برابر `null` است، نه یک User.
  - `content_html` مستقیم و بدون escape اضافه چاپ می‌شود (`{!! !!}`) — از قبل
    در `CreateBlogPost`/`UpdateBlogPost` با `Purifier::clean()` پاک‌سازی شده
    (Session ۳)؛ این Session escape دومی اضافه نکرد چون تکراری/بی‌فایده بود.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): RSS feed، سیستم
کامنت، لایک/امتیاز.

- [x] Session 5: Autosave پیش‌نویس، حذف بایگانی، حذف پست، برچسب آزاد، پیش‌نمایش

  **چه ساخته شد:**
  - **Autosave:** Action جدید `AutosaveBlogPostDraft` (authorize داخل خودش،
    Purifier، تولید اسلاگ یکتا با پسوند عددی به‌جای رد‌کردن با خطا) که فقط
    `title`/`slug`/`content_html` می‌نویسد — عمداً بسیار محدودتر از
    `CreateBlogPost`/`UpdateBlogPost` چون با هر ضربه کلید (بعد از
    debounce ۲ ثانیه‌ای `wire:model.live.debounce.2000ms`) صدا زده می‌شود و
    نباید اعتبارسنجی کامل (متا، تصویر، وضعیت) اجرا کند. `BlogPostForm` در
    `updatedTitle`/`updatedContent` آن را صدا می‌زند؛ فقط وقتی رکورد نداریم
    یا رکورد `draft` است اجرا می‌شود؛ خطاها (`try/catch(Throwable)`) بی‌صدا
    نادیده گرفته می‌شوند تا تایپ کاربر قطع نشود — ذخیره نهایی همچنان
    اعتبارسنجی کامل خودش را دارد.
  - **باگ واقعی کشف‌شده حین نوشتن تست:** اگر autosave همیشه
    `$this->slug` نمایشی را با اسلاگ واقعاً ذخیره‌شده همگام می‌کرد، وقتی
    کاربر خودش دستی یک اسلاگ تکراری تایپ می‌کرد، autosave به‌خاطر تصادم یک
    نسخه یکتای دیگر می‌ساخت و بی‌صدا مقدار تایپ‌شده کاربر را در UI بازنویسی
    می‌کرد — نتیجه: خطای «اسلاگ تکراری» که باید در ذخیره نهایی نشان داده
    شود، هرگز دیده نمی‌شد. اصلاح شد: `$this->slug` فقط وقتی
    `! $this->slugManuallyEdited` است از رکورد همگام می‌شود.
  - **حذف کامل «بایگانی‌شده»:** `BlogPostStatus` به سه حالت
    (`Draft`/`Scheduled`/`Published`) کاهش یافت؛ migration اصلاحی
    `2026_08_13_100001` هر رکورد `archived` قدیمی را به `draft` برمی‌گرداند
    و بعد CHECK را با `DROP CHECK`/`ADD CONSTRAINT` به سه مقدار تنگ می‌کند
    (طبق بند ۳.۲ `DATABASE_CONVENTIONS.md`).
  - **حذف پست:** `BlogPostPolicy::delete()` دقیقاً همان `update()` را صدا
    می‌زند (holding_admin هر پستی، operator فقط پیش‌نویس خودش). Action
    `DeleteBlogPost` (authorize داخل خودش، soft-delete واقعی). دکمه در
    `BlogPostIndex` با `wire:confirm` — همان الگوی تأییدیه غیرقابل‌بازگشت
    `FiscalPeriodIndex::close`، نه یک مودال اختصاصی جدا.
  - **برچسب آزاد:** select چندتایی قبلی (`x-choices` روی `tag_ids`) با یک
    ورودی متنی Alpine جایگزین شد (`$wire.entangle('tagNames')`، همان الگوی
    `time-picker.blade.php`؛ Enter/کاما یک chip قابل‌حذف اضافه می‌کند). موقع
    ذخیره، `BlogPostForm::resolveTagIds()` هر نام را با
    `BlogTag::firstOrCreate` به id واقعی تبدیل می‌کند — محدود به برچسب‌های
    از‌پیش‌ساخته در پنل تگ‌ها نیست. **نکته:** تطبیق بر پایه اسلاگ است نه نام
    خام، و چون `Str::slug()` روی نام کاملاً فارسی رشته خالی برمی‌گرداند
    (همان مشکل قبلاً مستندشده در Session ۱ برای اسلاگ پست)، اینجا برخلاف
    `generateSlug` فرم (که یک fallback تصادفی کافی بود چون کاربر بعداً
    دستی ویرایش می‌کند) fallback باید decisive باشد — از `sha1(name)` کوتاه
    استفاده شد، وگرنه تایپ دوباره‌ی همان نام فارسی هر بار یک تگ تکراری جدید
    می‌ساخت.
  - **پیش‌نمایش:** بخش `<article>` صفحه عمومی پست
    (`public/blog/show.blade.php`) به `resources/views/blog/partials/post-content.blade.php`
    استخراج شد (فقط به `$post` نیاز دارد). کنترلر+روت جدید
    `blog.posts.preview` (`GET /blog/posts/{post}/preview`، پشت `auth` +
    `BlogPostPolicy::view` — نه `published()`؛ یعنی صرف‌نظر از
    `post_status` قابل مشاهده است) همان partial را در یک بنر هشدار زرد
    نشان می‌دهد. `findOrFail` عمداً global scope مدل (`BelongsToCompany`)
    را حفظ می‌کند — دقیقاً مثل `BlogPostForm::mount`، فقط پست شرکت فعال
    سوییچر قابل پیش‌نمایش است، نه هر شرکتی.

  **محدودیت این Session:** بازدید بصری واقعی در مرورگر ممکن نشد (sandbox
  دسترسی به دامنه/پورت لوکال arshaman-erp.test را رد کرد)؛ تأیید فقط از
  طریق ۴۰ تست خودکار جدید/به‌روزشده (`BlogPostManagementTest` + اصلاح
  `PublicBlogPageTest`) و کل سوییت پروژه (۳۶۸ سبز، ۱۰ skip — همان
  CHECKهای mysql-only) انجام شد؛ تأیید بصری chip‌های برچسب و دکمه‌های
  پیش‌نمایش/حذف در UI واقعی هنوز روی کاربر باقی است.

  **باگ ۵۰۰ کشف‌شده بعد از این Session (توسط کاربر، در استفاده واقعی):**
  دقیقاً همین محدودیت («بازدید بصری ممکن نشد») واقعیت پیدا کرد.
  `/blog/posts` با `Undefined variable $activeCompanySlug` خطای ۵۰۰
  می‌داد. **علت:** دایرکتیو `@scope('actions', $post)` بسته mary UI
  (`Table.php`/`MaryServiceProvider::registerScopeDirective`) هیچ
  متغیری از scope بیرونی view را خودکار capture نمی‌کند — فقط همان‌هایی
  که صریحاً به‌عنوان آرگومان اضافه به خودِ `@scope` داده شوند وارد
  `use()` کلوژر می‌شوند. چون `render()` در `BlogPostIndex.php`
  `activeCompanySlug` را به view پاس می‌داد ولی `@scope('actions', $post)`
  آن را در آرگومان‌هایش نداشت، هر جدولی که واقعاً حداقل یک ردیف رندر
  می‌کرد (نه Livewire::test بدون رکورد) با این خطا مواجه می‌شد. رفع شد با
  `@scope('actions', $post, $activeCompanySlug)`.
  **درسِ تکراری (سومین‌بار در تاریخچه این ماژول، بعد از Editor.js list و
  Quill data-list در Session‌های قبلی):** رفتار دقیق یک کتابخانه/بسته
  خارجی (اینجا: دایرکتیو سفارشی Blade یک پکیج) را هرگز از روی نام یا
  شهود فرض نکن — کد واقعی پکیج را بخوان، مخصوصاً وقتی متغیرهای «بدیهاً
  در دسترس» ناگهان undefined می‌شوند.
  **تست اضافه‌شده برای جلوگیری از تکرار:** تست‌های قبلی این Session یک
  خلأ داشتند — همه از `Livewire::test(BlogPostForm::class)` یا
  `$this->get()` روی `blog.posts.preview` استفاده می‌کردند، هیچ‌کدام
  `BlogPostIndex` را با حداقل یک ردیف واقعی و از طریق یک درخواست HTTP
  کامل (نه Livewire::test) رندر نمی‌کردند — دقیقاً شرطی که این باگ را آشکار
  می‌کند. تست جدید در `BlogPostManagementTest` (`renders the real
  /blog/posts index page over HTTP...`) این خلأ را می‌بندد: سه پست با هر
  سه وضعیت می‌سازد و `/blog/posts` را با `$this->get()` واقعی باز می‌کند.
  **قاعده‌ای که از این پس رعایت می‌شود:** هر صفحه Livewire ای که از
  `@scope`/کامپوننت‌های مشابه mary UI استفاده می‌کند، حداقل یک تست باید
  آن را با یک درخواست HTTP واقعی و حداقل یک ردیف داده رندر کند — نه فقط
  از طریق `Livewire::test` روی خودِ کامپوننت فرم.

> این بخش را بعد از هر Session به‌روز کن. این حافظه بلندمدت پروژه است.
