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
12. **هر بار `WidgetContentRenderer` تغییر می‌کند** (رفع باگ رندر، تغییر CSS،
    افزودن ویجت جدید)، دستور `php artisan sitebuilder:regenerate-content-html`
    باید روی دیتابیس واقعی اجرا شود تا `content_html` همه صفحات موجود
    به‌روز بماند — وگرنه صفحات قدیمی‌تر با رندر کهنه گیر می‌کنند.
    **چرا:** `pages.content_html` یک snapshot است که فقط هنگام ذخیره
    (`CreatePageFromDemo`/`UpdatePageWidgetValues`) از `widget_tree` ساخته
    می‌شود؛ صفحه‌ی سایت عمومی مستقیم همین ستون را می‌خواند
    (`PublicSiteController::renderPage`)، نه اینکه هر بار از نو رندر کند.
    اگر رندرر بعداً عوض شود، صفحاتی که قبل از آن تغییر ذخیره شده‌اند تا وقتی
    خودشان دوباره ذخیره نشوند (که ممکن است هیچ‌وقت اتفاق نیفتد) با خروجی کهنه
    گیر می‌کنند — این دقیقاً همان باگی بود که باعث نمایش آیکون شکسته به‌جای
    تصویر در `/site/arshaman/services` و صفحات مشابه شد (تصویرها روی دیسک و
    مسیرشان سالم بودند؛ فقط `content_html` ذخیره‌شده قبل از رفع باگ
    `resolveImageUrl` بود).

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

### ماژول SiteBuilder (جدید — طبق درخواست صریح کارفرما)

- [x] Session 1: شش جدول پایه، کاتالوگ سه ویجت، جریان «انتخاب دمو + فرم مقدار»

  **پیشینه:** اولین تلاش این ماژول بر پایه GrapesJS (بوم drag-and-drop) بود.
  کارفرما بعد از استفاده واقعی، تجربه GrapesJS را نپسندید (نه شبیه المنتور)
  و چون آن Session هرگز commit نشده بود، کل رویکرد از صفر با معماری زیر
  بازنویسی شد — نه patch، یک طراحی کاملاً متفاوت.

  **معماری جدید (بدون drag-and-drop):** مثل انتخاب تم آماده در وردپرس/Astra
  — کاربر یک دمو از پیش‌طراحی‌شده انتخاب می‌کند، بعد فقط مقادیر داخل فیلدهای
  همان دمو را پر می‌کند. ساختار/تعداد/ترتیب ویجت هرگز از UI قابل تغییر نیست.

  **چه ساخته شد:** `app/Modules/SiteBuilder` با شش جدول: `widgets`،
  `page_categories`، `page_demos` (جدید — کاتالوگ دموی آماده هر دسته)،
  `layout_demos` (جدید — دموی هدر/فوتر سراسری سایت، جدا از صفحات)، `pages`
  (حالا با `page_demo_id` به‌جای `page_category_id` مستقیم)، `site_settings`
  (حالا با `active_header_demo_id`/`active_footer_demo_id`). چهار جدول اول
  سراسری‌اند (بدون `owner_company_id`، کاتالوگ مشترک هلدینگ مثل
  `contacts`/`holidays`)؛ `pages`/`site_settings` با `BelongsToCompany`.
  enum های PHP `PageCategoryKey`/`LayoutType`/`PageStatus`/`WidgetKey`.
  `page_categories.category_key` و `pages.page_status` همچنان **استثنای
  مستند** ENUM نیتیو MySQL (`docs/DATABASE_CONVENTIONS.md` بخش ۱۴)؛
  `layout_demos.layout_type` عمداً VARCHAR+CHECK استاندارد است، نه ENUM —
  آن استثنا فقط برای همان دو ستون مستند شده، نه کل ماژول.

  `PagePolicy` (مشاهده = هر نقشی در شرکت؛ ساخت = holding_admin/operator؛
  ویرایش = holding_admin هر صفحه، operator فقط draft؛ `canPublish` فقط
  holding_admin؛ `canEditExtraCode` جدید = holding_admin/operator بدون قید
  draft، طبق `docs/DECISIONS.md`). `SiteSettingPolicy` جدید (همان دو نقش
  مدیریتی). `WidgetContentRenderer` (بدون تغییر مفهومی: whitelist بر پایه
  `WidgetKey`، نوع ناشناخته لاگ+حذف؛ خروجی خالی هرگز رشته خالی نمی‌ماند —
  یک `<div class="sb-page-empty">` جایگزین می‌شود، چون این‌بار طبق مشخصات
  صریح کارفرما «content_html هرگز خالی/NULL نباشد»، نه فقط «هرگز NULL»
  مثل تصمیم Session قبلی).

  فقط دو Action اصلی: `CreatePageFromDemo` (widget_tree دمو را عیناً کپی
  می‌کند، `content_html` را همان‌جا می‌سازد) و `UpdatePageWidgetValues`
  (نگاشت widget-instance-id → مقادیر را می‌گیرد، فقط `values` همان نودهای
  موجود را جایگزین می‌کند — هرگز تعداد/ترتیب/فرزندان را دست نمی‌زند؛
  کلیدهای فیلد خارج از `editable_fields` تعریف‌شده در `widgets.default_config`
  بی‌صدا نادیده گرفته می‌شوند). یک Action کمکی سوم، `UpdateSiteSettings`
  (خارج از فهرست اصلی کارفرما ولی طبق بند ۹ لازم بود — نوشتن تنظیمات سایت
  هم باید authorize مستقل خودش را داشته باشد، نه فقط از طریق کامپوننت).

  کامپوننت‌ها: `PageDemoGallery` (`/sitebuilder/pages/create` — کارت دموها
  به تفکیک دسته، بعد فرم عنوان/نشانی)، `PageContentEditor`
  (`/sitebuilder/pages/{id}/edit` — جایگزین کامل `PageForm`/بوم قبلی؛ فرم
  تخت از فیلدهای قابل‌ویرایش هر نود، بدون هیچ canvas)، `LayoutDemoSelector`
  (`/sitebuilder/settings` — لوگو/فاوآیکون/عنوان سایت + انتخاب رادیویی دموی
  هدر/فوتر فعال)، `PageIndex` (بدون تغییر مفهومی). منوی «سایت‌ساز» یک زیرمنوی
  «تنظیمات سایت» گرفت.

  **تصمیم‌های این Session:**
  - `UpdatePageWidgetValues` مقادیر ویجت را از extra_css/extra_js **جدا**
    authorize می‌کند: تغییر مقادیر/انتشار همان قید معمول `update()` (operator
    فقط draft) را دارد، ولی `canEditExtraCode` همیشه مستقل بررسی می‌شود —
    چون طبق `docs/DECISIONS.md` operator باید بتواند حتی روی صفحه‌ی
    published هم extra_css/extra_js را ویرایش کند. اگر همه در یک Gate واحد
    بسته می‌شدند، آن تصمیم قبلی نقض می‌شد.
  - `PageContentEditor` یک computed property `canEditWidgetValues` دارد که
    مستقیماً `PagePolicy::update()` را صدا می‌زند تا فیلدهای ویجت را در UI
    غیرفعال کند وقتی operator روی صفحه‌ی published است — ولی حتی اگر UI را
    کسی دور بزند، خودِ Action دوباره authorize می‌کند (بند ۹).
  - GrapesJS و همه `@editorjs/*`/وابستگی‌های بوم قبلی کامل حذف شدند
    (`package.json`، `resources/js/sitebuilder.js`، کامپوننت‌های قبلی).
    مهاجرت‌های Session قبلی روی دیتابیس توسعه هرگز commit نشده بودند، پس
    با `migrate:rollback --path=...` تک‌تک (نه batch کامل، چون همان batch
    یک migration نامرتبط بلاگ هم داشت) پاک‌سازی و از صفر با شش جدول جدید
    دوباره migrate شدند — بدون هیچ `migrate:fresh`.
  - `page_demos`/`layout_demos` این Session فقط یک دموی نمونه (`about`) و
    یک هدر/فوتر نمونه دارند؛ دموهای واقعی و متعدد برای بقیه شش دسته در
    Session بعدی طراحی می‌شوند (`docs/BACKLOG.md`).
  - بازدید بصری واقعی این Session **ممکن نشد** (sandbox دسترسی به دامنه
    لوکال `arshaman-erp.test` را رد کرد) — تأیید فقط از طریق ۹ تست Feature
    جدید (`tests/Feature/SiteBuilder/PageManagementTest.php`) و کل سوییت
    پروژه (۳۸۰ سبز) انجام شد. تأیید بصری واقعی فرم پرکردن فیلد، آپلود
    تصویر، و انتخاب رادیویی هدر/فوتر هنوز روی کاربر باقی است.

- [x] Session 2: گسترش کاتالوگ ویجت — از سه به سیزده ویجت

  **چه ساخته شد:** ده ویجت جدید (`button`, `gallery`, `testimonial`,
  `pricing_table`, `faq_accordion`, `map`, `video`, `spacer`, `header_nav`,
  `footer`) با ده مقدار جدید در `WidgetKey` enum و متد رندر امن مختص خودشان
  در `WidgetContentRenderer`. Seeder جدید و جدا
  `database/seeders/SiteBuilderWidgetsExpansionSeeder.php` (نه ویرایش
  `SiteBuilderSeeder.php` قبلی — append، طبق درخواست صریح کارفرما)، ثبت‌شده
  در `DatabaseSeeder` بعد از seeder قبلی.

  **چهار نوع فیلد جدید در `editable_fields`/`PageContentEditor`/Blade، علاوه
  بر `text`/`image` قبلی:**
  - `select` — dropdown با `options` ثابت در `default_config` (مثل سبک دکمه).
  - `textarea` — متن چندخطی تک‌مقداری (مثل متن نظر مشتری).
  - `lines` — textarea که مقدار ذخیره‌شده‌اش آرایه‌ای از رشته‌هاست (هر خط یک
    آیتم، برای فهرست ویژگی‌های `pricing_table`). چون textarea نمی‌تواند
    مستقیم به آرایه bind شود، یک property جدا `linesRaw` (رشته‌ای، join شده
    با `\n`) staging می‌کند و فقط در `save()` split و trim می‌شود.
  - `repeater` — آرایه‌ای از ردیف‌ها با `item_fields` خودشان (زیرفیلد می‌تواند
    `text`/`textarea`/`image` باشد؛ برای `gallery`, `faq_accordion`,
    `header_nav`, `footer.social_links`). `addRepeaterRow`/`removeRepeaterRow`
    در `PageContentEditor` روی خودِ `fieldValues` کار می‌کنند (نه
    `$record->widget_tree` که immutable و فقط برای مقدار اولیه mount استفاده
    می‌شود) — در Blade هم iterate روی `fieldValues` انجام می‌شود، نه
    `node['values']` ثابت، وگرنه ردیف تازه‌اضافه‌شده هرگز رندر نمی‌شد.

  **امنیت map/video (دستور صریح کارفرما):** `WidgetContentRenderer` فقط
  `<iframe>` با `src` از دامنه‌های صراحتاً مجاز رندر می‌کند — نقشه فقط
  `www.google.com`/`google.com` با مسیر `/maps/embed`، ویدیو فقط
  یوتیوب (`youtube.com`/`youtu.be`، لینک watch/short را خودش به فرمت embed
  تبدیل می‌کند) یا آپارات (`aparat.com/v/...` به فرمت embed تبدیل می‌شود).
  هر دامنه دیگر رد و لاگ می‌شود، نه پذیرفته با یک escape ساده — چون اینجا
  خطر واقعی XSS/clickjacking از طریق `src` دلخواه است، نه فقط متن.

  **باگ واقعی کشف‌شده حین بازدید بصری واقعی در مرورگر (نه فقط تست PHP):**
  کامپوننت `<x-file>` در Mary UI همیشه `wire:model`اش را با
  `@entangle` (سمت Alpine) باز می‌کند. اگر یک فیلد تصویر اختیاری در دمو
  اصلاً مقداردهی نشده باشد (مثلاً `testimonial.customer_photo` وقتی دمو فقط
  `quote_text`/`customer_name` را ست کرده)، مسیر آن در `imageUploads` هرگز
  ساخته نمی‌شد و Alpine در کنسول مرورگر خطای «Livewire property ... cannot be
  found» می‌داد — این خطا فقط در مرورگر واقعی دیده می‌شود، هیچ تست PHP feature
  (که فقط HTML خروجی را چک می‌کند، نه اجرای JS) آن را نشان نمی‌دهد. رفع شد:
  `mount()` حالا برای هر فیلد تعریف‌شده در `editable_fields` (نه فقط
  کلیدهایی که دمو واقعاً داشت) هم `fieldValues` و هم `imageUploads` را
  صریحاً پیش‌فرض می‌زند (`null` برای اسکالر/تصویر، `[]` برای repeater)؛
  `addRepeaterRow` هم برای زیرفیلدهای تصویر همان مقداردهی پیش‌فرض
  `imageUploads` را برای ردیف تازه انجام می‌دهد. تست PHP جدید
  (`it defaults fieldValues and imageUploads for a declared field the demo
  never set`) این را در سطح property کامپوننت اثبات می‌کند، هرچند خودِ خطای
  کنسول را نمی‌تواند شبیه‌سازی کند — **درسِ تکرارشونده این ماژول (چهارمین‌بار،
  بعد از Editor.js list/Quill data-list/باگ `@scope` بلاگ): بعضی کلاس باگ‌ها
  (رفتار واقعی یک پکیج خارجی، این‌بار Alpine entangle) فقط با بازدید بصری
  واقعی در مرورگر دیده می‌شوند، نه با تست HTML-محور.**

  **تصمیم‌های دیگر این Session:**
  - `PageContentEditor::save()` برای merge کردن فایل‌های آپلودی از یک حلقه
    flat دولایه (`imageUploads[nodeId][fieldKey]`) به یک تابع بازگشتی عمومی
    `mergeUploadedFiles()` تغییر کرد تا هم فیلد تصویر top-level هم زیرفیلد
    تصویر داخل هر عمقی از repeater را با همان منطق واحد پوشش دهد.
  - آیکون‌های ویجت جدید (`icon` ستون `widgets`) مستقیماً نام blade-icon
    heroicons هستند (`o-cursor-arrow-rays` و مشابه) — همان الگوی سه ویجت
    قبلی (`o-squares-2x2` و ...)؛ این نقض قانون «آیکون فقط از `theme_icon()`»
    بخش ۳.۵ نیست چون این‌ها داده‌ی سطح دیتابیس هستند (مثل آیکون محصول)، نه
    نام آیکون hardcode‌شده در یک Blade view — دکمه‌های افزودن/حذف ردیف در
    خودِ `page-content-editor.blade.php` هم از `theme_icon('add')`/
    `theme_icon('delete')` موجود استفاده کردند، کلید جدیدی به `config/theme.php`
    اضافه نشد.
  - بازدید بصری کامل با کاربر تستی موقت (`sitebuilder-visual-test@example.com`)
    شامل هر ده ویجت جدید در یک صفحه واحد: افزودن/حذف ردیف gallery و FAQ،
    تغییر select سبک دکمه، ذخیره واقعی، و تأیید مستقیم از دیتابیس (`widget_tree`
    و `content_html`) که مقادیر درست پایدار شدند. کاربر، صفحه، و دموی تستی
    در پایان کامل حذف شدند (بند ۱۰ CLAUDE.md).

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): دموهای واقعی و
متعدد برای شش دسته صفحه با این ۱۳ ویجت (Session بعدی)، ویجت‌های یکپارچه
وبلاگ/تماس، رندر عمومی نهایی صفحات با استفاده از دموی هدر/فوتر فعال.

- [x] Session 3: دموهای واقعی و متعدد — از یک دموی نمونه به ۳۰ دمو

  **چه ساخته شد:** Seeder جدید append-only
  `database/seeders/SiteBuilderDemosExpansionSeeder.php` (ثبت‌شده در
  `DatabaseSeeder` بعد از دو seeder قبلی ماژول، بدون ویرایش آن‌ها) —
  ۱۸ دموی صفحه (سه دموی واقعاً متفاوت برای هر شش دسته: home/about/contact/
  services/blog/login) + ۳ دموی هدر + ۳ دموی فوتر = ۲۴ دموی جدید، به‌علاوه
  دموی نمونه Session ۱ که دست‌نخورده ماند (جمع نهایی: about و هر دو layout
  چهار دمو، بقیه دسته‌ها سه دمو).

  **تصمیم‌های این Session:**
  - چون کاتالوگ فعلی فقط ۱۳ ویجت دارد و هیچ ویجت متن‌آزاد (paragraph) وجود
    ندارد، برای توضیحات کوتاه از ویجت `title` با `level` بالاتر (۴ تا ۶)
    استفاده شد — نه یک ویجت جدید. تصمیم طراحی این Session، نه محدودیت فنی
    غیرقابل‌دور زدن.
  - دموهای `contact`/`blog` طبق دستور صریح کارفرما فقط جای‌گیر بصری‌اند
    (`Container`+`Title` به‌جای فرم تماس واقعی/فهرست واقعی پست‌ها) — دو
    آیتم متناظر (`contact_form`, `blog_post_list`) در `docs/BACKLOG.md`
    ثبت شد.
  - هر گره هر ۳۰ دمو یک `instance_label` منحصربه‌فرد و توصیفی گرفت (تست
    `DemoExpansionTest` این را برای همه دموها بازگشتی بررسی می‌کند) — طبق
    درسِ باگ قبلی این ماژول (برچسب مشترک بین دو نمونه هم‌نوع، فرم
    `PageContentEditor` را گمراه‌کننده می‌کند).
  - `thumbnail_path` همه دموهای جدید عمداً `null` ماند؛ تصویر بندانگشتی
    واقعی در `docs/BACKLOG.md` ثبت شد (خارج از scope دیتابیسی این Session).
  - **بازدید بصری واقعی در مرورگر این Session هم ممکن نشد** — همان محدودیت
    قبلی این ماژول (sandbox نمی‌تواند `arshaman-erp.test` را resolve کند؛
    این‌بار تأیید شد که Laragon این دامنه را از طریق DNS داخلی خودش سرویس
    می‌دهد، نه فایل hosts، پس شبکه sandbox اصلاً مسیری به آن ندارد). تأیید
    از طریق ۵ تست Feature جدید (`tests/Feature/SiteBuilder/DemoExpansionTest.php`)
    + کل سوییت پروژه (۴۰۴ سبز) + شمارش مستقیم رکوردهای seed‌شده در دیتابیس
    توسعه واقعی (`arshaman_erp`، با `php artisan tinker`، بدون هیچ
    `migrate:fresh`) انجام شد. کاربر تستی موقتی که برای تلاش بازدید بصری
    ساخته شد (`sitebuilder-demos-visual-test@example.com`) در پایان کامل
    حذف شد (بند ۱۰ CLAUDE.md).

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): ویجت‌های یکپارچه
واقعی وبلاگ/تماس، تصاویر بندانگشتی واقعی دموها، رندر عمومی نهایی صفحات با
استفاده از دموی هدر/فوتر فعال.

- [x] Session 4: پیش‌نمایش زنده در PageContentEditor (split view)

  **چه ساخته شد:** سرویس جدید `WidgetTreeValueMerger` (استخراج‌شده از منطق
  خصوصی `applyValues` قبلی داخل `UpdatePageWidgetValues`) — تنها منبع
  جایگزینی مقدار در widget_tree، حالا هم مسیر ذخیره واقعی (`UpdatePageWidgetValues`)
  هم پیش‌نمایش زنده از همین سرویس + همان `WidgetContentRenderer` سمت سرور
  استفاده می‌کنند. متد جدید `PageContentEditor::refreshPreview()` (بدون
  پارامتر، وابستگی‌ها از `app()` گرفته می‌شوند تا هم از Alpine هم از داخل
  `addRepeaterRow`/`removeRepeaterRow` قابل صدا زدن باشد) یک widget_tree
  موقت از روی `fieldValues` فعلی فرم می‌سازد و فقط دو property جدید
  (`previewHtml`/`previewCss`) را پر می‌کند — رکورد `pages` در دیتابیس
  دست‌نخورده می‌ماند. پنل پیش‌نمایش یک `<iframe sandbox="allow-same-origin">`
  با `srcdoc` مجزاست (نه inline در DOM ادمین) تا `extra_css` صفحه با استایل
  پنل تداخل نکند؛ اسکریپت (`extra_js`) عمداً در پیش‌نمایش اجرا نمی‌شود.

  **تصمیم‌های این Session:**
  - برخلاف autosave وبلاگ (که چند `wire:model.live` مستقل داشت و race
    واقعی ایجاد کرده بود)، اینجا از روز اول فقط یک تایمر debounce واحد در
    Alpine (`schedulePreview()`، الگوی دقیق `scheduleAutosave` بلاگ) روی
    `x-on:input`/`x-on:change` همه فیلدهای متنی/select/textarea/repeater
    گذاشته شد — فیلد تصویر (`x-file`) عمداً از این trigger کنار گذاشته شد
    چون آپلود Livewire مسیر شبکه‌ی جداگانه خودش را دارد.
  - تبدیل فیلد نوع `lines` (خط به آرایه) از save() به یک متد خصوصی مشترک
    `fieldValuesWithLinesResolved()` منتقل شد — دقیقاً همان استدلال merger:
    یک منبع واحد بین ذخیره و پیش‌نمایش، نه دو منطق که ممکن است از هم جدا
    بیفتند.
  - بازدید بصری واقعی این Session با کاربر تستی موقت
    (`sitebuilder-preview-visual-test@example.com`) انجام شد، ولی نه از
    طریق دامنه Laragon (`arshaman-erp.test` — همان محدودیت شبکه sandbox
    مستندشده در Session‌های قبلی این ماژول) بلکه با `php artisan serve` روی
    `127.0.0.1:8123`. تست شامل تایپ سریع پشت‌سرهم (stress test مشابه رفع
    باگ autosave وبلاگ) و یک payload کامل `<script>alert(1)</script>` بود؛
    هر دو با بازرسی مستقیم DOM/`iframe.contentDocument` (نه اسکرین‌شات — پنل
    Browser در این محیط فریم را کمپوزیت نمی‌کرد) تأیید شدند: مقدار نهایی
    پیش‌نمایش دقیقاً با آخرین ورودی مطابق بود (بدون باقی‌ماندن نسخه قدیمی)،
    صفر تگ `<script>` در iframe، و رکورد `pages` در دیتابیس در تمام این مدت
    دست‌نخورده ماند. کاربر تستی در پایان کامل حذف شد.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): جابه‌جایی ویجت‌ها،
ذخیره خودکار صفحه (autosave واقعی، نه فقط پیش‌نمایش).

- [x] Session 5: رندر عمومی واقعی — URLی که بازدیدکننده مهمان می‌بیند

  **چه ساخته شد:** `Page::scopePublished()` (فقط `page_status`، برخلاف
  `BlogPost::scopePublished()` که `published_at` هم دارد — Page در این
  Session هنوز بعد زمان‌بندی انتشار ندارد)، کنترلر جدید
  `App\Modules\SiteBuilder\Http\Controllers\PublicSiteController` (متدهای
  `home()`/`show()`، دقیقاً همان الگوی ایزولاسیون `PublicBlogController`:
  `withoutGlobalScope('owner_company')` + `where('owner_company_id', ...)`
  از روی `companySlug` مسیر، نه `CompanyContext` session)، مسیرهای عمومی
  `GET /site/{companySlug}` (نام `public-site.home`) و
  `GET /site/{companySlug}/{pageSlug}` (نام `public-site.show`)، layout جدید
  `resources/views/layouts/public-site.blade.php` (جدا از
  `layouts/public.blade.php` وبلاگ چون هدر/فوتر اینجا از دموی رندرشده
  می‌آید، نه یک نوار هدر ثابت)، و دو ویو
  `resources/views/public/sitebuilder/{show,not-configured}.blade.php`.

  **تصمیم‌های این Session:**
  - هدر/فوتر عمومی از همان `WidgetContentRenderer` موجود ساخته می‌شوند (منبع
    رندر واحد، نه کپی جدید) — ولی چون `content_html` خودِ صفحه از قبل هنگام
    ذخیره ساخته و در دیتابیس نگه داشته شده (`CreatePageFromDemo`/
    `UpdatePageWidgetValues`)، `PublicSiteController` آن را دوباره رندر
    نمی‌کند، فقط مستقیم می‌خواند؛ فقط `widget_tree` هدر/فوتر (که چنین
    ستونی برای HTML از‌پیش‌رندرشده ندارند) در لحظه رندر می‌شود.
  - «سایت هنوز راه‌اندازی نشده» به‌جای ۴۰۴/خطای خام: وقتی `site_settings`
    اصلاً وجود نداشت یا `homepage_page_id` آن خالی بود، یک ویو مستقل
    (`not-configured.blade.php`) با پیام واضح نمایش داده می‌شود (۲۰۰، نه
    ۴۰۴) — چون این حالت یک خطای برنامه نیست، وضعیت طبیعی «هنوز پیکربندی
    نشده» است.
  - `logo_path` در `site_settings` این Session به هیچ‌جای صفحه عمومی متصل
    نشد (نه در `<head>`، نه در ویجت `header_nav`) — چون طبق درخواست صریح
    کارفرما اتصال لوگو مشروط بود به اینکه «آن ویجت از قبل جایی برای لوگو
    داشته باشد»، و `header_nav` (بند Session ۲ همین ماژول) فقط `nav_links`
    دارد، فیلد لوگو ندارد. اضافه‌کردن آن یک قابلیت جدید به ویجت است، خارج
    از scope این Session — در `docs/BACKLOG.md` ثبت شد. `favicon_path` و
    `site_title`/`site_tagline` (برای `<title>`/meta description) اما واقعاً
    متصل شدند، چون این‌ها ورودی مستقیم `<head>` هستند، نه ویجت.
  - `extra_js` صفحه در این مسیر عمومی واقعاً اجرا می‌شود (برخلاف iframe
    پیش‌نمایش ادمین `PageContentEditor::getPreviewDocumentProperty()` که
    عمداً اسکریپت را اجرا نمی‌کند) — طبق درخواست صریح کارفرما، چون این‌جا
    صفحه واقعی است نه یک پیش‌نمایش امن‌سازی‌شده؛ ریسک عدم-sanitize بودن
    `extra_css`/`extra_js` از قبل در Session قبلی این ماژول آگاهانه پذیرفته
    شده بود (طبق `docs/DECISIONS.md`).
  - بازدید بصری واقعی این Session (برخلاف چند Session قبلی این ماژول) با
    موفقیت انجام شد: با `php artisan serve` روی `127.0.0.1` (همان محدودیت
    شبکه sandbox نسبت به دامنه Laragon `arshaman-erp.test`، مستندشده در
    Session‌های قبلی)، یک صفحه/دموی هدر/دموی فوتر موقت برای شرکت واقعی
    `arshaman` ساخته شد و در `/site/arshaman` و `/site/arshaman/{slug}`
    باز شد — هدر (نوار ناوبری)، محتوای اصلی، و فوتر دقیقاً همان چیزی بودند
    که در widget_tree تعریف شده بود؛ favicon پیش‌فرض/عنوان تب هم تأیید شد.
    حالت «سایت راه‌اندازی نشده» هم برای یک شرکت بدون `site_settings` تأیید
    شد. همه داده‌های موقت (صفحه، دو دموی layout، شرکت تستی بدون سایت) در
    پایان کامل حذف شدند؛ هیچ رمز کاربر واقعی تغییر نکرد (این Session اصلاً
    نیازی به ورود کاربر نداشت، چون مسیرهای عمومی بدون auth‌اند).
  - تست‌ها: `tests/Feature/SiteBuilder/PublicSitePageTest.php` (۱۱ تست) —
    هم‌راستا با الگوی `PublicBlogPageTest`: انتشار/عدم‌انتشار، ایزولاسیون
    شرکت روی هم صفحه اصلی هم صفحه مستقیم، پیام «راه‌اندازی نشده»، متا
    تگ‌های واقعی، و دو تست امنیتی که whitelist دامنه map/XSS escape موجود
    `WidgetContentRenderer` را از طریق مسیر عمومی جدید (نه فقط تست واحد
    renderer) بازآزمایی می‌کنند — با `content_html` واقعاً از طریق همان
    renderer ساخته‌شده، نه دستی‌نویسی‌شده، تا این تست‌ها رفتار واقعی مسیر
    عمومی را بسنجند.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): فیلد لوگو در ویجت
`header_nav`، رندر عمومی وبلاگ/تماس یکپارچه با سایت‌ساز، زمان‌بندی انتشار
صفحه (مثل `PublishScheduledPost` بلاگ).

- [x] Session 6: جابه‌جایی (drag-and-drop) ویجت‌ها در PageContentEditor/PageCreateFlow

  **چه ساخته شد:** کتابخانه `sortablejs` (npm) با یک wrapper نازک
  `resources/js/sitebuilder-sortable.js` (`window.initSitebuilderSortable`،
  بسته‌شده در `app.js` مثل `editor.js`). سرویس جدید و مستقل
  `App\Modules\SiteBuilder\Services\WidgetTreeReorderer` (فقط جابه‌جایی
  ساختاری — استخراج نود از هرجای درخت + درج در محل جدید + رد حلقه‌ی
  بی‌نهایت با `InvalidArgumentException`، کاملاً جدا از `WidgetTreeValueMerger`
  که فقط مقدار جایگزین می‌کند). هر دو کامپوننت متد جدید `moveWidgetNode($draggedId,
  $targetParentId, $targetIndex)` گرفتند که روی `widgetTree`/`workingWidgetTree`
  در حافظه کار می‌کند (بدون DB write)؛ `PageContentEditor` علاوه‌براین اول
  `PagePolicy::update()` را authorize می‌کند (دقیقاً همان قید operator-فقط-draft
  بقیه مسیر). `UpdatePageWidgetValues::handle()` یک پارامتر اختیاری جدید
  `?array $widgetTree` گرفت (مثل الگوی از‌قبل‌موجود `CreatePageFromDemo`) تا
  ساختار reorder-شده، نه یک بازخوانی خام از DB، پایه‌ی merge مقادیر شود.
  Blade فرم مسطح قبلی (که اصلاً محفظه‌ها را نشان نمی‌داد — فقط نودهای دارای
  فیلد را flatten می‌کرد) با یک درخت واقعی recursive جایگزین شد: سه partial
  جدید `partials/widget-fields.blade.php` (فرم فیلدها، استخراج‌شده از تکرار
  قبلی بین دو کامپوننت)، `partials/widget-tree.blade.php` (یک لیست
  sortable — هم سطح بالا هم فرزندان هر محفظه، همه با یک `group` مشترک متصل)،
  و `partials/widget-tree-node.blade.php` (کارت هر نود + دستگیره‌ی درگ +
  اگر محفظه بود یک widget-tree تودرتوی دیگر برای فرزندانش).

  **تصمیم‌های این Session:**
  - **کتابخانه فقط برای انتخاب/جابه‌جایی است، نه افزودن.** طبق تصمیم قبلی
    کارفرما (این Session، بندی جدید)، هیچ دکمه/drag-handle‌ای برای افزودن
    نوع ویجت جدید از کتابخانه اضافه نشد — `moveWidgetNode` فقط جای یک نود
    *موجود* را عوض می‌کند، هرگز نمی‌سازد یا حذف نمی‌کند (تست مستقیم:
    `WidgetTreeReordererTest` تعداد نودهای قبل/بعد را چک می‌کند).
  - **دفاع دولایه در برابر حلقه‌ی بی‌نهایت** (بند ۹ CLAUDE.md، الگوی
    authorize-در-Action): سمت کلاینت `onMove` در sitebuilder-sortable.js
    فوراً درِ drop روی خودِ محفظه/فرزندانش را می‌بندد (بازخورد آنی UI)؛ سمت
    سرور `WidgetTreeReorderer::move()` مستقل و بدون‌اعتماد به کلاینت همان
    چک را دوباره انجام می‌دهد و throw می‌کند — حتی اگر کسی مستقیم
    `$wire.call('moveWidgetNode', ...)` را با پارامتر دستکاری‌شده صدا بزند
    (تأیید شده هم با تست Feature هم با فراخوانی مستقیم از کنسول مرورگر در
    بازدید بصری واقعی).
  - **SortableJS با `forceFallback: true` مقداردهی شد**، نه HTML5 Drag-and-Drop
    بومی پیش‌فرض کتابخانه — DnD بومی مرورگر روی لیست‌های تودرتو (محفظه داخل
    محفظه) و داخل container هایی با اسکرول رفتار ناپایدار دارد؛ حالت
    fallback (شبیه‌سازی‌شده با رویدادهای pointer/mouse معمولی) هم پایدارتر
    است هم قابل‌تست با رویدادهای شبیه‌سازی‌شده واقعی (کشف شد حین بازدید
    بصری: با DnD بومی، هیچ راهی برای تست خودکار/شبیه‌سازی درگ در sandbox
    بدون یک ژست کاربر واقعی مورد اعتماد مرورگر وجود نداشت).
  - `moveWidgetNode` در `UpdatePageWidgetValues` فقط وقتی `$widgetTree !== null`
    باشد `authorize('update')` را اجباری می‌کند؛ `PageContentEditor::save()`
    این پارامتر را فقط وقتی `canEditWidgetValues` باشد پاس می‌دهد — وگرنه
    مسیر «operator فقط extra_css/extra_js را روی صفحه‌ی published ویرایش
    می‌کند» (Session قبلی همین ماژول) با یک authorize اضافه نابجا می‌شکست.
  - `$this->fieldValues`/`$this->canEditWidgetValues` داخل partial های
    تودرتوی جدید (`@include` چندلایه) کار می‌کند چون Livewire خودِ کامپوننت
    را برای *کل* چرخه‌ی render (نه فقط ویو ریشه) با `Closure::bind` به
    `$this` وصل می‌کند (`ExtendedCompilerEngine::evaluatePath`) — قبل از
    نوشتن partial ها این را از سورس خودِ پکیج Livewire تأیید کردم، نه حدس.

  **بازدید بصری واقعی این Session** با کاربر تستی موقت
  (`sitebuilder-drag-visual-test@example.com`، روی `127.0.0.1:8123` نه
  دامنه Apache) کامل انجام شد: جابه‌جایی یک ویجت از سطح بالا به داخل یک
  محفظه (تأیید DOM زنده + `POST /livewire/update` واقعی + بعد از Save مستقیم
  از دیتابیس)، جابه‌جایی بین دو محفظه‌ی متفاوت، و رد‌شدن drop یک محفظه داخل
  خودش — هم از مسیر واقعی درگ (client-side `onMove` قبل از رسیدن به سرور)
  هم با فراخوانی مستقیم `Livewire.find(id).call('moveWidgetNode', ...)` از
  کنسول مرورگر برای دور زدن UI و اثبات این‌که چک واقعی سمت سرور است، نه فقط
  زینت رابط کاربری. کاربر، دموی موقت، و صفحه‌ی موقت تست در پایان کامل حذف شدند.

- [x] Session 7: دو ویجت یکپارچه واقعی — contact_form و blog_post_list

  **چه ساخته شد:** دو ویجت جدید در `WidgetKey` (`ContactForm`, `BlogPostList`)
  که برخلاف هر ۱۳ ویجت قبلی **داده‌ی ثابت ندارند** — یکی فرم واقعی
  `App\Livewire\CRM\Public\ContactForm` را embed می‌کند (بدون کپی منطق
  honeypot/rate-limit/validation)، دیگری کوئری زنده‌ی `BlogPost::published()`
  همان شرکت را نشان می‌دهد. سرویس جدید `DynamicWidgetResolver` + کلاس کمکی
  `App\Modules\SiteBuilder\Support\StorageUrl` (استخراج‌شده از
  `WidgetContentRenderer::resolveImageUrl` تا هر دو مسیر رندر بدون تکرار
  منطق از همان تابع استفاده کنند).

  **معماری marker (تصمیم اصلی این Session):** `content_html` یک snapshot
  ثابت است که با `{!! !!}` خام echo می‌شود — هرگز در لحظه‌ی درخواست دوباره
  Blade-compile نمی‌شود، پس نه یک کامپوننت Livewire واقعاً hydrate‌شده در آن
  جا می‌گیرد نه یک کوئری واقعاً تازه. راه‌حل: `WidgetContentRenderer` برای
  این دو ویجت به‌جای HTML نهایی، یک بلوک placeholder ثابت (متن راهنما برای
  پیش‌نمایش ادمین) بین یک جفت **کامنت HTML marker** تولید می‌کند
  (`<!--sb:contact_form:...--> ... <!--/sb:contact_form-->` و مشابه برای
  `blog_post_list`). این marker دقیقاً همان‌طور در `content_html` ذخیره
  می‌شود و در پیش‌نمایش ادمین همان‌طور می‌ماند. فقط `PublicSiteController`
  — تنها جایی که بازدیدکننده‌ی مهمان واقعی و company مشخص وجود دارد —
  `DynamicWidgetResolver::resolve()` را روی یک **کپی رشته‌ای** از
  `content_html` صدا می‌زند و marker ها را با محتوای واقعی جایگزین می‌کند؛
  رکورد `Page` هرگز نوشته نمی‌شود. نتیجه: فرم همیشه واقعاً در همان درخواست
  mount/hydrate می‌شود (`Illuminate\Support\Facades\Blade::render()` یک
  `@livewire(...)` واقعی را از صفر کامپایل و اجرا می‌کند، پس `wire:id`
  معتبر همان درخواست است، نه یک HTML قدیمی کپی‌شده)، و فهرست پست‌ها همیشه
  واقعاً تازه است حتی اگر صفحه مدت‌ها پیش ذخیره شده باشد.

  **چرا پیکربندی داخل marker به‌جای base64(json) کد شد، نه متن خام:**
  `blog_post_list` (تعداد پست/عنوان بخش) و `contact_form` (عنوان بخش) هر دو
  پیکربندی‌شان را *داخل خودِ کامنت* حمل می‌کنند، نه در HTML بین دو کامنت —
  چون `DynamicWidgetResolver` کل بلوک بین شروع/پایان مارکر را با محتوای زنده
  جایگزین می‌کند و هر چیزی که فقط در HTML میانی باشد (نه در خودِ کامنت) از
  بین می‌رود. این پیکربندی از یک فیلد متنی آزاد ادمین (`section_title`) پر
  می‌شود، نه یک select با گزینه‌ی ثابت؛ اگر مستقیم داخل کامنت HTML قرار
  می‌گرفت، عنوانی حاوی توالی `-->` می‌توانست از کامنت خارج بزند و HTML دلخواه
  (`<img onerror=...>` و مشابه) تزریق کند — دقیقاً همان کلاس خطر XSS که
  whitelist دامنه map/video در Session گسترش ویجت‌ها به آن پاسخ داده بود،
  این‌بار در سطح خودِ marker. base64 هیچ‌گاه حاوی `-->` یا هیچ کاراکتر خاص
  HTML دیگری نیست، پس این مسیر بسته شد. تست مستقیم این حمله (`section_title`
  با یک `-->` واقعی در `IntegratedWidgetsTest.php`) این را اثبات می‌کند.

  **کشف حین اجرای واقعی روی دیتابیس توسعه (نه فرض از روی seeder):** طبق
  دستور کارفرما، seeder دموهای تماس/وبلاگ به‌روزرسانی شد تا از این دو ویجت
  واقعی استفاده کند (جایگزین container های جای‌گیر قبلی)، ولی چون
  `CreatePageFromDemo` widget_tree دمو را عیناً کپی می‌کند، این ویرایش
  خودکار روی صفحات واقعی از قبل ساخته‌شده اثر نمی‌گذارد (دقیقاً طبق منطق
  بند ۹.۱۲). قبل از فرض‌کردن که فقط کافی است container جای‌گیر قدیمی را در
  صفحات واقعی پیدا و جایگزین کرد، دیتابیس واقعی مستقیم بررسی شد — و مشخص شد
  **هر سه صفحه‌ی واقعی «تماس با ما» (هر دو شرکت) اصلاً هیچ‌وقت container
  جای‌گیر فرم نداشتند**، چون همه از دموی «شعب متعدد» ساخته شده بودند که
  حتی در نسخه‌ی قبل از این Session هم هیچ فرمی (نه واقعی، نه جای‌گیر)
  نداشت. یک replace ساده‌ی node-به-node برای این صفحات هیچ کاری نمی‌کرد.
  دستور یک‌باره‌ی جدید `sitebuilder:integrate-contact-blog-widgets` برای
  همین دو حالت جدا نوشته شد:
  1. **replace** — جایگزینی node-به-node برای صفحاتی که واقعاً container
     جای‌گیر قدیمی (با id های شناخته‌شده‌ی دموهای پیشین) دارند.
  2. **append** — برای صفحات منتشرشده‌ی دسته «تماس» که هیچ `contact_form`
     ای در widget_tree‌شان نیست (نه حتی جای‌گیر)، یک نود `contact_form`
     واقعی به انتهای درخت اضافه می‌شود؛ فقط افزودنی، هیچ نود موجودی حذف/
     جابه‌جا نمی‌شود. این دقیقاً همان دلیلی است که این کار نمی‌توانست از
     طریق ادیتور دستی انجام شود: طبق تصمیم Session ۶ (drag-and-drop)،
     `PageContentEditor` اصلاً قابلیت «افزودن ویجت جدید» ندارد، پس تنها راه
     یک اصلاح داده‌ی یک‌باره بود، نه یک درخواست از کاربر برای انجام دستی.
  اجرا شد: ۲ صفحه (`blog` هر دو شرکت) از مسیر ۱ اصلاح شدند، ۲ صفحه
  (`tmas-ba-ma`, `contact-us` شرکت‌های arshaman/shared) از مسیر ۲. بعد از
  آن `sitebuilder:regenerate-content-html` هم طبق بند ۹.۱۲ روی کل دیتابیس
  اجرا شد (CSS مشترک `baseStyles` تغییر کرده بود، پس عملاً همه صفحات
  به‌روزرسانی خوردند، نه فقط این دو ویجت).

  **بازدید بصری واقعی این Session** با `php artisan serve` روی
  `127.0.0.1:8000` (همان محدودیت شبکه sandbox نسبت به دامنه Apache
  `arshaman-erp.test`، مستندشده در Session‌های قبلی این ماژول) انجام شد:
  صفحه‌ی واقعی `/site/arshaman/blog` دو پست واقعاً منتشرشده را با خلاصه و
  تاریخ نشان داد؛ صفحه‌ی واقعی `/site/arshaman/tmas-ba-ma` فرم واقعی را پر
  و ارسال کرد و رکورد در `contact_submissions` با `owner_company_id` درست
  تأیید شد — سپس همان رکورد تستی از دیتابیس واقعی حذف شد (بند ۱۰ CLAUDE.md؛
  اینجا نیازی به کاربر تستی/رمز عبور نبود چون کل مسیر بدون auth است).

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): زمان‌بندی انتشار
صفحه (مثل `PublishScheduledPost` بلاگ)، افزودن ویجت جدید از کاتالوگ در
PageContentEditor.

- [x] Session 8: لوگو در هدر عمومی — آخرین قطعه‌ی نقشه‌راه SiteBuilder

  **چه ساخته شد:** فیلد بولین جدید `show_logo` (پیش‌فرض `true`) به
  `editable_fields` ویجت `header_nav` در `SiteBuilderWidgetsExpansionSeeder`
  اضافه شد و با `db:seed --class=SiteBuilderWidgetsExpansionSeeder` روی
  دیتابیس واقعی به‌روزرسانی شد (نه migration، همان الگوی `updateOrCreate`ی
  که از Session ۲ همین ماژول برای گسترش کاتالوگ ویجت استفاده می‌شود).
  `WidgetContentRenderer::renderHeaderNav` حالا قبل از لینک‌های منو
  `site_settings.logo_path` همان شرکت را (از طریق متد جدید
  `renderHeaderLogo`) رندر می‌کند — دقیقاً با همان `resolveImageUrl`/
  `StorageUrl` root-relative بقیه ویجت‌ها، نه یک آدرس هاردکد بر پایه
  APP_URL (همان باگ سراسری تصاویر شکسته‌ی بند ۹.۱۲ که این‌بار برای مسیر
  لوگو تکرار نشد). اگر `logo_path` خالی بود، `site_settings.site_title`
  به‌صورت متنی جایگزین می‌شود؛ اگر آن هم خالی بود، هیچ‌چیز رندر نمی‌شود —
  هرگز یک `<img>` شکسته یا جای خالی توضیح‌ناپذیر.

  **تصمیم‌های این Session:**
  - نوع فیلد `boolean` برای اولین بار به سیستم عمومی `editable_fields`
    اضافه شد (`x-checkbox` در `widget-fields.blade.php`، طبق الگوی دقیق
    `product-form.blade.php`) + یک کلید عمومی جدید `default` در تعریف هر
    فیلد که `PageContentEditor`/`PageCreateFlow` هنگام مقداردهی اولیه یک
    نود بدون آن کلید استفاده می‌کنند (`$field['default'] ?? null`، به‌جای
    `null` خام قبلی) — چون کلید غایب در یک `widget_tree` قدیمی‌تر باید
    پیش‌فرض واقعی فیلد را بگیرد، نه همیشه `null`/`false`.
  - چون هیچ UI ای برای ویرایش مقادیر نودهای `header_nav`/`footer` داخل
    `layout_demos` وجود ندارد (این دو فقط از یک کاتالوگ دموی ثابت در
    `LayoutDemoSelector` انتخاب می‌شوند، نه دستی ویرایش)، مقدار `show_logo`
    فقط از طریق `default` در کاتالوگ یا مقدار seed‌شده‌ی خودِ دموی هدر
    قابل تنظیم است، نه یک فرم زنده — این محدودیت معماری از قبل موجود بود
    (Session ۱)، نه چیزی که این Session معرفی کرده باشد.
  - `renderHeaderLogo` مستقیماً `SiteSetting` را با `withoutGlobalScope('owner_company')`
    از روی `$company->id` کوئری می‌کند (دقیقاً همان الگوی `resolveNavHref`
    برای صفحات) — نه یک پارامتر جدید روی امضای عمومی `render()`، چون فقط
    `PublicSiteController::renderPage` واقعاً یک `$company` غیر-null دارد؛
    در پیش‌نمایش ادمین (`$company === null`) لوگو اصلاً رندر نمی‌شود، دقیقاً
    همان رفتار قبلی لینک‌های منو در نبود شرکت.
  - بازدید بصری واقعی (`php artisan serve` روی `127.0.0.1:8123`، همان
    محدودیت شبکه sandbox نسبت به دامنه Apache مستندشده در کل این ماژول) با
    یک کاربر تستی موقت روی شرکت واقعی `arshaman` انجام شد: یک PNG واقعی
    روی دیسک `public` قرار گرفت، `site_settings.logo_path` به آن اشاره
    کرد، و `/site/arshaman` واقعاً لوگو را با `naturalWidth:1`/`complete:true`
    (بارگذاری موفق، نه فقط حضور در HTML) و درخواست شبکه‌ی `200 OK` روی
    مسیر root-relative `/storage/...` نشان داد؛ سپس حالت بدون لوگو (بازگشت
    به متن `site_title`) هم روی همان صفحه تأیید شد. فایل PNG تستی،
    `site_settings.logo_path` (به `null`، مقدار اصلی پیش از این Session)،
    و کاربر تستی در پایان کامل حذف/بازگردانی شدند (بند ۱۰ CLAUDE.md).
  - تست‌های جدید در `WidgetExpansionTest.php`: لوگوی واقعی با مسیر
    root-relative، fallback متنی، حذف کامل هدر وقتی نه لوگو نه منو موجود
    است، `show_logo=false` حتی با `logo_path` موجود، و عدم رندر لوگو در
    نبود `$company` (پیش‌نمایش ادمین).

با این Session، آخرین قطعه‌ی باقی‌مانده‌ی نقشه‌راه اصلی ماژول SiteBuilder
(طبق فهرست Session ۷) تکمیل شد.

- [x] Session 9: حذف/پیش‌نمایش صفحه، پیش‌نمایش زنده هدر/فوتر، افزودن ویجت
  با کلیک، فرم ثبت‌نام مشتری → CRM، سازماندهی منو

  **چه ساخته شد (پنج بخش مستقل، طبق درخواست صریح کارفرما):**

  1. **حذف/مشاهده صفحه:** `PagePolicy::delete()` (عیناً `update()` —
     holding_admin هر صفحه، operator فقط draft)، Action جدید `DeletePage`
     (soft-delete؛ هوک موجود `Page::booted()['deleting']` خودش ارجاع
     `site_settings` را پاک می‌کند). مسیر پیش‌نمایش ادمین جدید
     `GET /sitebuilder/pages/{page}/preview` (`PublicSiteController::preview()`،
     auth + `PagePolicy::view`، **بدون** فیلتر `published()` — برخلاف
     `show()` عمومی). `PublicSiteController::renderPage()` از `private` به
     `protected` تغییر کرد + پارامتر `bool $preview` برای بنر هشدار زرد در
     `public/sitebuilder/show.blade.php` (دقیقاً الگوی `blog.posts.preview`).
     `PageIndex`: دکمه «مشاهده سایت» (به `public-site.home`)، ستون عملیات
     هر ردیف حالا «مشاهده صفحه» (published → لینک مستقیم عمومی، غیر آن →
     مسیر پیش‌نمایش) + «حذف صفحه» (`wire:confirm` مستقیم، بدون مودال جدا —
     عیناً الگوی `BlogPostIndex`).

  2. **پیش‌نمایش زنده تنظیمات سایت:** آپلود لوگو/فاوآیکون از Session ۱ این
     ماژول از قبل با `WithFileUploads` واقعی کار می‌کرد (فقط تأیید شد، کد
     اضافه لازم نبود). رادیوهای انتخاب دموی هدر/فوتر از `wire:model` به
     `wire:model.live` تغییر کردند؛ دو computed property جدید
     (`headerPreviewHtml`/`footerPreviewHtml`) مستقیم از همان
     `WidgetContentRenderer::render()` (با `Company` واقعی، پس لوگو هم در
     پیش‌نمایش دیده می‌شود) در یک `<iframe srcdoc>` کوچک کنار هر گروه رادیویی.
     بدون debounce Alpine — انتخاب رادیو یک رویداد گسسته است، نه تایپ پیوسته.

  3. **پنل «افزودن ویجت» با کلیک (تغییر تصمیم آگاهانه نسبت به Session ۶):**
     آن Session عمداً گفته بود کتابخانه فقط برای انتخاب/جابه‌جایی است، نه
     افزودن؛ کارفرما این بار صریح یک مسیر افزودن **محدود** خواسته بود:
     کلیک (نه درگ از کتابخانه به صفحه). `WidgetTreeReorderer::addNode()`
     متد عمومی جدید (بدون تغییر امضای متدهای موجود) که همان `insert()`
     خصوصی موجود را با `$targetIndex = PHP_INT_MAX` صدا می‌زند — همان قید
     «فقط داخل یک محفظه» رایگان از کد موجود می‌آید. کاتالوگ «افزودن سریع»
     دقیقاً ۱۰ ویجت (`config/sitebuilder.php` → `quick_add_widgets`):
     `container, title, text_editor, image, icon, button, gallery, slider,
     faq_accordion, contact_form`. `PageContentEditor`/`PageCreateFlow` هر
     دو `activeContainerId` (مقصد افزودن — با دکمه‌ی «انتخاب به‌عنوان مقصد»
     روی هر نود محفظه در `widget-tree-node.blade.php`) و `addWidget()` گرفتند
     (در `PageContentEditor` با همان authorize صریح `moveWidgetNode`؛ در
     `PageCreateFlow` بدون authorize جدا، چون هنوز رکورد `pages` وجود ندارد
     — همان استدلال `moveWidgetNode` آنجا). نود تازه فقط یک آیتم دیگر در
     `widgetTree` است، پس درگ‌اند‌دراپ موجود Session ۶ بدون هیچ کد اضافه
     رویش کار می‌کند (تأیید شده هم با تست هم با بازدید بصری).

     **سه ویجت جدید برای رسیدن به ۱۰ مورد:**
     - `text_editor`: فیلد نوع جدید `richtext` — سمت کلاینت از همان
       `window.initBlogEditor`/`saveBlogEditor` موجود بلاگ استفاده می‌کند
       (تابع عمداً عمومی بود، فقط اسمش تاریخی «Blog» مانده). کنترلر آپلود
       تصویر **جدا** از بلاگ ساخته شد
       (`SiteBuilder\Http\Controllers\SiteBuilderEditorImageUploadController`،
       مسیر `sitebuilder.editor-image-upload`، authorize با
       `PagePolicy::create`) — چون کنترلر بلاگ `BlogPostPolicy::create` را
       authorize می‌کند و به‌اشتراک‌گذاشتن مستقیم route بین دو ماژول قانون
       وابستگی بند ۴ CLAUDE.md را نقض می‌کرد. **sanitize فقط در یک نقطه**:
       `WidgetTreeValueMerger::applyValues()` گسترش یافت — وقتی نوع فیلد
       `richtext` است، مقدار قبل از نوشتن با `Purifier::clean()` (همان
       `config/purifier.php` بلاگ) پاک می‌شود؛ چون `refreshPreview()` هم از
       همین merger عبور می‌کند، پیش‌نمایش و ذخیره نهایی هرگز از هم جدا
       نمی‌افتند.
     - `icon`: فیلدهای `icon_name` (select، whitelist ثابت ۱۴ نام heroicon
       تزئینی — الگوی مستند Session ۲ همین ماژول، نقض بند ۳.۵ نیست چون داده
       سطح دیتابیس است)، `size`، `color` (فقط کلاس CSS متصل به
       `--sb-primary-color`/`--sb-secondary-color` موجود). **کشف حین نوشتن
       تست (نه بازدید بصری این‌بار):** هلپر سراسری `svg()` پکیج blade-icons
       نام کامل ست آیکون را می‌خواهد (`heroicon-o-star`)، نه میان‌بر `o-star`ی
       که `<x-icon>` مری داخلی‌اش با `Mary\View\Components\Icon::icon()`
       می‌سازد؛ بدون این تبدیل، هر آیکون معتبر هم بی‌صدا رندر نمی‌شد (silent
       catch). رفع شد با `svg('heroicon-'.$iconName, ...)`.
     - `slider`: فیلد `slides` (repeater: تصویر+عنوان+متن). رندر با کاروسل
       **خالص CSS** (بدون JS جدید) — رادیوی مخفی به‌ازای هر اسلاید (نامش از
       node id مشتق می‌شود تا چند اسلایدر روی یک صفحه تداخل نکنند) +
       انتخاب‌گر `:nth-of-type(i):checked ~ ...`. چون nowdoc ثابت
       `baseStyles()` نمی‌تواند تعداد اسلاید متغیر را از پیش بداند،
       انتخاب‌گرهای CSS برای حداکثر ۲۰ اسلاید در یک متد جدا
       (`sliderSelectorStyles()`) با حلقه PHP تولید می‌شوند، مستقل از تعداد
       واقعی اسلاید هر نمونه.

  4. **فرم «ثبت‌نام مشتری» → CRM (نه Contact/Lead مستقیم):** طبق بررسی
     معماری موجود، هر Action واقعی CRM (`ContactMatcher::findOrCreateContact`,
     `CreateContactSiteProfile`, `CreateLead`, `RecordInteraction`) یک
     `User $actor` غیرقابل‌حذف برای `Gate::authorize`/`created_by_user_id`
     می‌خواهد که بازدیدکننده مهمان ندارد — تنها الگوی موجود نوشتن CRM بدون
     کاربر واردشده همان `SubmitContactForm`/`ContactSubmission` است (استثنای
     مستند بند ۹). ستون جدید `contact_submissions.source` (VARCHAR(20)
     + CHECK `IN ('contact_form','site_signup')`، پیش‌فرض `contact_form`)
     در یک migration جدا (نه ویرایش قدیمی). Action جدید
     `App\Modules\CRM\Actions\CaptureCustomerSignup` — کپی ساختاری
     `SubmitContactForm` (honeypot silent-drop، rate limit جدا با کلید
     `customer-signup:{ip}:{companyId}`، بدون Gate/actor)، می‌نویسد روی
     همان `contact_submissions` با `source='site_signup'`. کامپوننت عمومی
     جدید `App\Livewire\CRM\Public\CustomerSignupForm` (namespace/الگوی دقیق
     `ContactForm` موجود). `WidgetKey::CustomerSignupForm` + marker pattern
     عیناً `renderContactForm`/`resolveContactForm` (کامنت HTML
     `<!--sb:customer_signup_form:BASE64-->`، فقط کامپوننت Livewire جدا).
     تبدیل این ثبت‌نام به یک `Contact`/`Lead` واقعی عمداً خودکار نیست —
     کار کارمند احراز‌هویت‌شده از همان پنل «پیام‌های تماس با ما» می‌ماند
     (که حالا یک ستون «منبع» هم نشان می‌دهد). دو تا از سه دموی دسته Login
     (`SiteBuilderDemosExpansionSeeder::loginDemos()`) به این ویجت واقعی
     مجهز شدند؛ چون این‌ها فقط کاتالوگ (`page_demos`) هستند نه صفحات واقعی،
     فقط seeder دوباره روی دیتابیس واقعی اجرا شد (idempotent)، بدون نیاز
     به `regenerate-content-html`.

  5. **سازماندهی منو (فقط `layouts/app.blade.php`):** آیتم‌های وبلاگ و
     «پیام‌های تماس با ما» زیر «سایت‌ساز» منتقل شدند — فقط محل نمایش، هیچ
     Policy/route/controller دست نخورد. چون شرط دسترسی «پیام‌های تماس با
     ما» (`holding_admin`/`operator`) محدودتر از شرط عمومی‌تر زیرمنوی
     سایت‌ساز («هر نقشی در شرکت») بود، همان `@if` محدودتر قبلی روی خودِ آن
     یک `<x-menu-item>` (نه کل زیرمنو) نگه داشته شد.

  **تست‌ها:** `WidgetTreeReordererTest` (تست `addNode` جدید)،
  `PageDeletionAndQuickAddTest` (حذف/Policy/پیش‌نمایش ادمین/پیش‌نمایش زنده
  تنظیمات/افزودن ویجت هم در PageContentEditor هم PageCreateFlow/regression
  درگ‌اند‌دراپ روی نود تازه)، `QuickAddWidgetRenderTest` (رندر امن سه ویجت
  جدید + sanitize مرکزی richtext)، `CustomerSignupFormTest` (ثبت موفق/rate
  limit/honeypot/ایزولاسیون شرکت/CHECK دیتابیس/marker resolve)،
  `SiteBuilderNavigationTest` (جای جدید آیتم‌ها، Policy دست‌نخورده). کل
  سوییت پروژه: ۵۱۶ سبز، ۱۱ skip (همان CHECKهای mysql-only).

  **بازدید بصری واقعی** با `php artisan serve` روی `127.0.0.1:8123` (همان
  محدودیت شبکه sandbox نسبت به دامنه Apache، مستندشده در کل این ماژول) و
  یک کاربر تستی موقت روی شرکت واقعی `arshaman`: ساخت یک صفحه از دموی ورود
  برندی (شامل `customer_signup_form` از قبل)، افزودن زنده ویجت آیکون از
  پنل و دیدن SVG واقعی در پیش‌نمایش، انتشار و ارسال واقعی فرم ثبت‌نام از
  سایت عمومی (رکورد واقعی در `contact_submissions` با `source=site_signup`
  و ستون «منبع» درست در پنل ادمین تأیید شد)، پیش‌نمایش زنده تعویض دموی هدر
  (بدون ذخیره ماندن — تأیید شد که `site_settings` واقعی دست‌نخورده ماند)،
  و حذف واقعی صفحه‌ی تستی. صفحه/پیام تماس/کاربر تستی در پایان کامل حذف شدند
  (بند ۱۰ CLAUDE.md).

- [x] Session 10: بررسی گزارش باگ فیلدهای متنی، حذف ویجت icon، تراز متن، حذف تکی ویجت، بهبود UI

  **بخش ۱ — گزارش «فیلدهای متنی قابل تایپ نیستند/دکمه دیده نمی‌شود/عنوان
  فرم تماس دیده نمی‌شود» بازتولید نشد:** با کاربر تستی موقت واقعی روی
  `127.0.0.1:8123`، تایپ واقعی (نه dispatchEvent ساختگی) در هر مسیر ممکن —
  فیلد top-level (`image.alt`)، فیلد تودرتوی داخل container (`title.text`
  ساعات کاری)، فیلد داخل repeater (`faq_accordion.items.N.question`)، فیلد
  یک ویجت تازه‌اضافه‌شده با کلیک از پنل، و در هر دو PageCreateFlow/
  PageContentEditor — همه‌جا مقدار درست تایپ، در widget_tree ذخیره، و در
  content_html/iframe پیش‌نمایش رندر شد؛ button و contact_form.section_title
  هم در content_html نهایی هم در iframe زنده حاضر بودند. **تصمیم:** به‌جای
  حدس‌زدن یک «رفع» برای باگی که بازتولید نشد، یک ریسک واقعی که در کد وجود
  داشت رفع شد — `schedulePreview()`/`refreshPreview()` (هر دو کامپوننت)
  قبلاً هیچ محافظتی در برابر دو درخواست preview هم‌پوشان نداشت (`previewInFlight`
  ثبت می‌شد ولی هرگز قبل از شروع تایمر بعدی چک نمی‌شد)؛ حالا با
  `previewBusy`/`previewPending` یک درخواست در حال رفت‌وبرگشت هرگز با یک
  درخواست دیگر هم‌پوشان نمی‌شود (صف تک‌مرحله‌ای، نه چند تایمر موازی) —
  سخت‌شدن دفاعی، نه رفع یک باگ اثبات‌شده. تست‌های
  `WidgetDeletionTextAlignTest` بخش «رگرسیون بخش ۱» این چهار ادعا را قفل
  می‌کنند تا این کلاس رفتار در آینده نشکند.

  **بخش ۲ — حذف کامل ویجت icon:** `WidgetKey::Icon` حذف شد (enum دیگر آن
  case را ندارد)، `renderIcon()`/`ALLOWED_DECORATIVE_ICONS`/CSS مربوطه از
  `WidgetContentRenderer` حذف شدند، از `config('sitebuilder.quick_add_widgets')`
  حذف شد، و تعریفش از `SiteBuilderQuickAddWidgetsSeeder` برداشته شد.
  **تصمیم دربارهٔ داده‌ی موجود:** بررسی مستقیم دیتابیس واقعی نشان داد هیچ
  صفحه یا دموی واقعی‌ای از این ویجت استفاده نمی‌کرد، پس نیازی به migration
  داده‌ی widget_tree نبود. برای ردیف کاتالوگ خودِ `widgets` (که با enum حذف‌شده
  دیگر هرگز رندر نمی‌شود)، seeder یک‌باره‌ی جدید `SiteBuilderRemoveIconWidgetSeeder`
  (idempotent، با رشته خام `'icon'` چون enum دیگر آن مقدار را ندارد) اضافه
  و در `DatabaseSeeder` ثبت شد. اگر یک صفحه‌ی قدیمی‌تر (محیط دیگر) هنوز یک
  نود icon در widget_tree داشته باشد، مسیر امنِ از‌قبل‌موجود
  `WidgetContentRenderer::renderNode()` برای هر `widget_key` ناشناخته (لاگ +
  حذف بی‌صدا از خروجی) خودکار آن را می‌گیرد — تصمیم آگاهانه به‌جای یک
  migration داده‌ی جدا.

  **بخش ۳ — فیلد عمومی `text_align`:** یک نوع فیلد جدید نیست، فقط یک کلید
  `select` با مقادیر بسته `right`/`left`/`center` (`WidgetContentRenderer::
  ALLOWED_TEXT_ALIGNS`) که هر ویجتی می‌تواند در `editable_fields` خودش
  تعریف کند (فعلاً: `title`, `button`, `text_editor`) — سیستم فیلد عمومی
  موجود (whitelist در `WidgetTreeValueMerger`، پیش‌فرض در `PageContentEditor::mount`/
  `addWidget`، فرم `select` در `widget-fields.blade.php`) از قبل این را
  بدون هیچ کد جدید پشتیبانی کرد. برای `title`/`text_editor` مستقیم
  `style="text-align:...` روی همان تگ اعمال می‌شود. برای `button` این کار
  نکرد چون `<a>` با `display:inline-flex` روی خودش `text-align` را نادیده
  می‌گیرد — راه‌حل: لنگر حالا داخل یک `<div class="sb-widget" style="text-align:...">`
  پیچیده می‌شود (کلاس عمومی `sb-widget` از خودِ `<a>` به این `<div>` منتقل
  شد، نه تکرار).

  **بخش ۴ — حذف تکی هر ویجت:** متد عمومی جدید `WidgetTreeReorderer::remove()`
  (از همان `extract()` خصوصی موجود دوباره استفاده می‌کند — دقیقاً همان
  پیمایش عمیقی که `move()` برای برداشتن نود مبدا استفاده می‌کند). **تصمیم
  حذف محفظه:** cascade — چون `extract()` کل زیردرخت را با خودش برمی‌دارد،
  حذف یک محفظه خودکار همه‌ی فرزندانش را هم حذف می‌کند؛ نگه‌داشتن فرزندان در
  جای دیگر (ریشه یا محفظه‌ی دیگر) یک بازآرایی ساختاری خاموش و غیرمنتظره
  بود. به‌جای مودال جدا، متن `wire:confirm` روی دکمه حذف بسته به نوع نود
  فرق می‌کند («این محفظه و همه‌ی ویجت‌های داخلش حذف می‌شوند» در برابر «این
  ویجت حذف شود؟») — همان الگوی `wire:confirm` مستقیم بلاگ/`FiscalPeriodIndex`،
  نه یک کامپوننت تأییدیه جدید. `deleteWidget()` در هر دو `PageContentEditor`
  (authorize صریح `PagePolicy::update`، بند ۹) و `PageCreateFlow` (بدون
  authorize جدا، همان استدلال `moveWidgetNode`/`addWidget`) اضافه شد؛ اگر
  نود حذف‌شده همان محفظه‌ی «مقصد افزودن» فعلی بود، مقصد به ریشه بازنشانی
  می‌شود.

  **بخش ۵ — بهبود UI پنل (بدون skill `artifact-design`، چون این یک artifact
  نیست؛ `frontend-design`/`mary-ui-component` هر دو صدا زده شدند):** یک
  فهرست/outline جدید (`partials/widget-outline.blade.php`، پشت یک
  `<details>` جمع‌شونده وقتی بیش از یک ویجت وجود دارد) با لینک‌های `#sb-node-anchor-{id}`
  به هر ویجت (هر عمقی، با تورفتگی بر اساس عمق) اضافه شد — بین پنل «افزودن
  ویجت» و درخت واقعی. سرتیتر «چیدمان و محتوای ویجت‌ها» بالای درخت اضافه شد
  تا مرز بصری «افزودن» در برابر «ویرایش/چیدمان» واضح‌تر باشد. دکمه‌ی حذف در
  `widget-tree-node.blade.php` کنار دکمه‌ی «انتخاب به‌عنوان مقصد» (برای
  محفظه‌ها) در همان `x-slot:menu` — یعنی محل عملیات هر کارت حالا همیشه یک
  جای ثابت است (بالا-چپ کارت، نه پراکنده)، نه فقط برای محفظه‌ها. کلید آیکون
  جدید `outline` (`o-list-bullet`) به `config/theme.php` اضافه شد (طبق
  چک‌لیست skill، هیچ نام آیکون مستقیم در Blade).

  **چک‌لیست skill `mary-ui-component` رعایت‌شده:** بدون کد رنگ مستقیم، آیکون
  فقط از `theme_icon()` (کلید جدید `outline` قبل از استفاده به
  `config/theme.php` اضافه شد)، کامپوننت‌های Mary UI (`x-button`, `x-card`,
  `x-badge`) به‌جای HTML خام، بدون `ml-*`/`mr-*`/`pl-*`/`pr-*` جدید.

  **تست‌ها:** `tests/Unit/SiteBuilder/WidgetTreeReordererTest.php` (۴ تست
  جدید برای `remove()`)، `tests/Feature/SiteBuilder/WidgetDeletionTextAlignTest.php`
  (جدید — حذف تکی/محفظه/عملگر operator/create-flow، رندر و whitelist
  `text_align` برای هر سه ویجت، حذف کامل `icon` از enum/config/seeder،
  skip امن یک نود icon باقی‌مانده، و رگرسیون بخش ۱). دو تست icon قدیمی در
  `QuickAddWidgetRenderTest` حذف شدند. کل سوییت پروژه: ۵۳۵ سبز، ۱۱ skip
  (همان CHECKهای mysql-only) — بدون رگرسیون.

  **بازدید بصری واقعی** با `php artisan serve` روی `127.0.0.1:8123` (همان
  محدودیت شبکه sandbox نسبت به دامنه Apache) و یک کاربر تستی موقت روی شرکت
  واقعی `arshaman`: تأیید شد ویجت icon دیگر در پنل «افزودن ویجت» نیست، دکمه
  حذف واقعی یک ویجت (نقشه) را از پیش‌نمایش زنده و بعد از Save از دیتابیس
  واقعی حذف کرد، و تغییر `text_align` دکمه به `center` بلافاصله در iframe
  پیش‌نمایش زنده (`style="text-align:center;"` روی `div` والد، نه خودِ
  `<a>`) منعکس شد. کاربر و صفحه‌ی تستی در پایان کامل حذف شدند (بند ۱۰
  CLAUDE.md).

### ماژول Process (جدید — موتور گردش‌کار عمومی)

- [x] Session 1: هسته‌ی دیتابیس (پنج جدول، بدون موتور اجرا/UI)

  **چه ساخته شد:** `app/Modules/Process` با `new-module-scaffold`. پنج جدول:
  `process_definitions` (تعریف فرایند — یا وصل به یک مدل ماژول دیگر با
  `subject_type`، یا فرایند کاملاً آزاد با `request_form_fields` JSON به
  همان ساختار `editable_fields` ماژول SiteBuilder)، `process_steps`
  (`step_type`/`assignment_type`/`condition_operator` هر سه ENUM نیتیو
  MySQL — استثنای مستند، پایین توضیح داده شده)، `process_transitions`
  (بدون timestamp — یک یال ساده‌ی گراف، طبق دقیقاً همان ستون‌هایی که در
  پرامپت Session خواسته شده بود)، `process_instances` (پلی‌مورفیک
  `subject_type`/`subject_id`، `request_data` JSON برای فرایند آزاد)،
  `process_instance_logs` (snapshot صریح `owner_company_id`، بدون
  `updated_at` — رکورد لاگ غیرقابل‌ویرایش، الگوی دقیق
  `contact_submission_attempts`). شش enum PHP معادل
  (`StepType`/`AssignmentType`/`ConditionOperator`/`TransitionResult`/
  `ProcessStatus`/`LogAction`) با label فارسی. `config/processes.php`
  (رجیستری خالی این Session: `subject_types`/`condition_fields`/
  `result_actions` — سه whitelist که برنامه‌نویس در Session بعدی برای HR پر
  می‌کند؛ `result_actions` عمداً whitelist کلاس Action است، نه نام رشته‌ای
  آزاد قابل‌نمونه‌سازی از ورودی کاربر). `ProcessDefinitionPolicy`
  (`viewAny` = هر نقشی در شرکت، بقیه فقط `holding_admin`). `ProcessSampleSeeder`
  (append-only در `DatabaseSeeder`، یک زنجیره‌ی کاملاً ساختگی
  start→approval→condition→(دو مسیر)→end برای شرکت `arshaman`، تا Session
  بعدی موتور اجرا رویش تست شود).

  **تصمیم‌های این Session:**
  - **استثنای مستند جدید:** `process_steps.step_type`/`assignment_type`/
    `condition_operator`، `process_transitions.on_result`،
    `process_instances.status`، `process_instance_logs.action` — هر شش با
    تصمیم صریح کارفرما از نوع ENUM نیتیو MySQL هستند، نه VARCHAR+CHECK
    استاندارد. جزئیات کامل در `docs/DATABASE_CONVENTIONS.md` بند ۱۵ (الگوی
    دقیق بند ۱۴ برای SiteBuilder). `process_definitions.subject_type`/
    `process_key` این استثنا را ندارند — مقدار آزادند، نه از یک مجموعه بسته.
  - `process_definitions`/`process_steps` عمداً فقط `created_by_user_id`
    (بدون `updated_by_user_id`) و بدون soft delete دارند — دقیقاً طبق ستون‌های
    مشخص‌شده در پرامپت این Session، نه یک تصمیم مستقل از قرارداد سراسری بند
    ۳ CLAUDE.md. اگر Session بعدی نیاز به ویرایش/حذف واقعی تعریف فرایند پیدا
    کرد، این دو ستون باید قبل از ساخت آن Action اضافه شوند (migration
    اصلاحی جدید، نه ویرایش این پنج migration).
  - `process_transitions` بدون هیچ ستون audit/timestamp است — یک یال ساده‌ی
    گراف بین دو `process_step`، دقیقاً طبق تعریف Session (که هیچ ستون
    timestamp برای این جدول نخواسته بود).
  - دو CHECK سطح دیتابیس (`process_definitions`: subject_type/request_form_fields
    متقابلاً انحصاری؛ `process_instances`: subject_type/subject_id هر دو پر
    یا هر دو خالی) با همان الگوی گارد `!== 'sqlite'` بقیه‌ی پروژه — تست‌های
    مربوطه با `markTestSkipped` روی sqlite (محیط تست) skip می‌شوند، دقیقاً
    الگوی `chk_products_fulfillment_type`/`chk_csa_outcome`.
  - `ProcessSampleSeeder` مستقیم مدل‌ها را می‌سازد (نه از طریق یک Action/Gate)
    — همان الگوی `CompanySeeder`/`FiscalPeriodSeeder::buildAttributes`، چون
    seeder کاربر واردشده‌ای برای authorize ندارد؛ `withoutGlobalScopes()` روی
    `updateOrCreate` تعریف برای جلوگیری از رکورد تکراری در هر بار seed
    (همان درسِ مستندشده `FiscalPeriodSeeder`).
  - تست شامل CHECK دوگانه (skip‌شده روی sqlite)، ایزولاسیون شرکت،
    Policy (`holding_admin` فقط)، و بازسازی کامل زنجیره‌ی seed‌شده
    (۵ step + ۵ transition با روابط صحیح) در `tests/Feature/Process/ProcessDefinitionTest.php`.
    کل سوییت پروژه: ۵۴۸ سبز، ۱۳ skip (۲ مورد جدید همین ماژول + همان
    CHECKهای mysql-only قبلی) — بدون رگرسیون.
  - **تأیید مستقیم روی MySQL واقعی (جلسه‌ی تکمیلی همان روز):** بعد از بالا
    آمدن سرویس MySQL محلی، `php artisan migrate --force` (بدون `fresh`، طبق
    بند ۸ CLAUDE.md) روی `arshaman_erp` واقعی بدون خطا اجرا شد؛
    `ProcessSampleSeeder` هم روی همان دیتابیس اجرا شد. با کوئری مستقیم (نه
    فقط پیام موفقیت دستور) تأیید شد: زنجیره‌ی seed‌شده دقیقاً همان ۵ step/۵
    transition با روابط صحیح است، هر دو CHECK constraint
    (`chk_process_definitions_subject_or_form`/`chk_process_instances_subject_pair`)
    یک insert نامعتبر واقعی را با `QueryException` رد کردند، و هر شش ستون
    enum-like (`step_type`/`assignment_type`/`condition_operator`/`on_result`/
    `status`/`action`) واقعاً از نوع native `ENUM` هستند
    (`information_schema.COLUMNS.COLUMN_TYPE`) — نه صرفاً VARCHAR.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): موتور اجرای واقعی
(`ProcessEngine` service)، هیچ UI، اتصال واقعی به HR/مرخصی.

- [x] Session 2: موتور اجرای واقعی (`ProcessEngine`)

  **چه ساخته شد:** `App\Modules\Process\Services\ProcessEngine` — بدون
  binding خاص در `ProcessServiceProvider` (کلاس ساده، از `app()` قابل resolve
  است). دو متد عمومی اصلی: `startInstance()` (می‌سازد، روی مرحله‌ی `start`
  می‌گذارد، بلافاصله با تنها انتقال خروجی آن جلو می‌رود) و `advance()`
  (نتیجه‌ی مرحله‌ی approval فعلی را می‌گیرد و زنجیره را ادامه می‌دهد).
  دو Action نازک `App\Modules\Process\Actions\{ApproveProcessStep,RejectProcessStep}`
  که authorize واقعی (بند ۹ CLAUDE.md) را قبل از صدازدن `advance()` انجام
  می‌دهند؛ چک authorize خودش در `ProcessEngine::assertActorAuthorizedForStep()`
  است (نه در Action تکرار شده) چون به ساختار مرحله (assignment_type/
  assigned_role/assigned_user_id) نیاز دارد که فقط سرویس آن را می‌شناسد.
  استثنای اختصاصی `App\Modules\Process\Exceptions\ProcessCycleDetectedException`
  (فرزند `RuntimeException`) برای تفکیک دقیق خطای چرخه از بقیه‌ی خطاهای موتور.

  **تصمیم‌های این Session:**
  - **قرارداد تعیین status نهایی instance:** هر مرحله‌ی `end` با نتیجه‌ی همان
    انتقالی که به آن رسیده تعیین می‌شود — `approved`/`condition_true` →
    `ProcessStatus::Approved`، `rejected`/`condition_false` →
    `ProcessStatus::Rejected`. عمداً بر پایه‌ی *نتیجه‌ی انتقال* است، نه نام‌گذاری
    `step_key` (مثل `end_approved`/`end_rejected` در seeder) — چون نام‌گذاری
    یک قرارداد نمایشی است و هیچ‌جا در دیتابیس تضمین/اعتبارسنجی نمی‌شود؛ اتکا
    به آن یعنی یک تعریف فرایند با نام‌گذاری متفاوت (یا فارسی) نتیجه‌ی نادرست
    می‌گرفت. هیچ ستون متادیتای جدیدی به `process_steps` اضافه نشد.
  - **زنجیره‌ی خودکار مراحل `condition`:** وقتی `advance()`/`startInstance()`
    به یک مرحله‌ی `condition` می‌رسد، بلافاصله (بدون بازگشت به caller) آن را
    ارزیابی و `advance` داخلی را تکرار می‌کند تا به یک مرحله‌ی `approval`
    واقعی یا `end` برسد — کاملاً طبق درخواست. مرحله‌ی `approval` (یا `start`
    در یک گراف غیرعادی) تنها جایی است که این حلقه‌ی داخلی واقعاً متوقف
    می‌شود و منتظر یک `advance()` بیرونی بعدی می‌ماند.
  - **محافظت در برابر چرخه، دولایه:** (۱) یک مجموعه‌ی `visited` که فقط در
    طول *یک* فراخوانی بیرونی (`startInstance`/`advance`) جمع می‌شود — اگر
    مرحله‌ای در همان زنجیره‌ی خودکار دوباره دیده شود، `ProcessCycleDetectedException`.
    (۲) یک سقف عمق ثابت (`MAX_AUTO_ADVANCE_STEPS = 50`) به‌عنوان لایه‌ی دفاعی
    دوم، مستقل از تشخیص چرخه‌ی دقیق. تست با یک گراف کاملاً ساختگی (دو مرحله‌ی
    condition که به هم برمی‌گردند، ساخته‌شده مستقیم با Eloquent در تست، نه
    از طریق seeder) هر دو راه واقعاً throw کردن را تأیید می‌کند.
  - **خواندن فیلد شرط، فقط از دو مسیر whitelist‌شده:** برای فرایند وصل‌شده
    به یک `subject_type`، از `config('processes.condition_fields.{FQCN}')`؛
    برای فرایند آزاد (`subject_type=null`)، از `request_data` خودِ instance
    — کلیدهای `request_data` از قبل توسط `request_form_fields` همان تعریف
    (که فقط `holding_admin` می‌سازد) محدود شده‌اند، پس whitelist دوم لازم
    نبود. هرگز دسترسی آزاد به یک پراپرتی دلخواه مدل subject.
  - **اعتبارسنجی سازگاری subject در `startInstance()`:** اگر تعریف به یک
    `subject_type` وصل است، سوژه‌ی داده‌شده باید دقیقاً همان کلاس باشد (نه
    فقط «هر مدلی»)؛ اگر تعریف آزاد است، سوژه اصلاً نباید داده شود. هر دو
    با `InvalidArgumentException` رد می‌شوند — این چک صریح خواسته‌ی پرامپت
    نبود ولی از قبل در همان لایه بود، اضافه شد تا `subject_type`/`subject_id`
    نامنطبق با ستون‌های دیتابیس فقط در Session HR (که واقعاً subject واقعی
    می‌سازد) کشف نشود.
  - **تست مستقیم روی MySQL واقعی:** با یک کاربر تستی موقت (نقش `holding_admin`
    در شرکت `arshaman`، بند ۱۰ CLAUDE.md) زنجیره‌ی seed‌شده‌ی
    `ProcessSampleSeeder` واقعاً از `start` تا هر دو `end` (تأیید با مبلغ بالا
    → `end_approved`/`approved`، رد مستقیم → `end_rejected`/`rejected`) اجرا
    و لاگ‌ها تأیید شدند؛ کاربر تستی و instance/logهای ساخته‌شده در پایان کامل
    حذف شدند (تأیید شد صفر رکورد باقی‌مانده).

  **تست‌ها:** `tests/Feature/Process/ProcessEngineTest.php` (۸ تست) — توقف
  در اولین approval واقعی، تأیید توسط نقش مجاز → ادامه‌ی خودکار condition →
  end تأیید‌شده، رد → مستقیم به end ردشده (بدون هیچ لاگ condition_evaluated)،
  رد تأیید توسط کاربر بدون نقش/تخصیص درست (`AuthorizationException`)، تأیید
  توسط `assigned_user_id` مشخص (بدون نقش)، هر دو مسیر شرط (بالا/پایین آستانه)،
  و تشخیص چرخه‌ی واقعی. کل سوییت پروژه: ۵۵۶ سبز، ۱۳ skip (همان CHECKهای
  mysql-only) — بدون رگرسیون.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): هیچ UI، اتصال واقعی
به HR/مرخصی (Session جدا)، ثبت `subject_type`/`condition_fields`/
`result_actions` واقعی در `config/processes.php` (کار Session HR).

- [x] Session 3: اولین اتصال واقعی — فرایند تأیید مرخصی HR

  **چه ساخته شد:** `config/processes.php` رسمی شد: `subject_types` →
  `Leave::class`، `condition_fields` → `['days_count', 'leave_type']`،
  `result_actions` → `ApproveLeave::class`/`RejectLeave::class` (همان Action
  های موجود HR، چیز جدیدی موازی ساخته نشد). Seeder جدید append-only
  `ProcessLeaveDefinitionSeeder` (ثبت‌شده در `DatabaseSeeder` بعد از
  `ProcessSampleSeeder`) — زنجیره‌ی واقعی: start → تأیید سرپرست/HR
  (`accountant`) → بررسی مدت (`days_count <= 5`) → مسیر کوتاه مستقیم
  `end_approved`، مسیر بلند از `تأیید اضافه‌ی مدیر ارشد` (`holding_admin`)
  می‌گذرد؛ رد در هر مرحله‌ی approval → `end_rejected`.

  دو متد عمومی جدید روی `ProcessEngine` — تنها نقطه‌ی اتصال HR به موتور:
  - `startForSubjectIfActive(Model $subject, User $actor)`: اگر برای
    `owner_company_id`/کلاس سوژه یک definition فعال باشد، instance می‌سازد؛
    وگرنه `null`. `RequestLeave::handle()` این را همان داخل `DB::transaction`
    بعد از `Leave::create()` صدا می‌زند — اگر تعریفی نبود، رفتار قبلی
    (بدون فرایند) کاملاً دست‌نخورده می‌ماند.
  - `hasActiveInstance(Model $subject)` / نسخه‌ی دسته‌ای
    `activeInstanceSubjectIds()`: `ApproveLeave`/`RejectLeave` این را قبل از
    هر تغییر مستقیم صدا می‌زنند و اگر instance «در جریان» باشد
    `ValidationException` می‌اندازند («این درخواست از طریق فرایند سازمانی در
    حال بررسی است») — مسیر مستقیم/موازی روی یک درخواست در حال فرایند مسدود
    است. `LeaveIndex` هم از نسخه‌ی دسته‌ای برای پنهان‌کردن دکمه‌های تأیید/رد
    و نمایش یک badge («در حال بررسی در فرآیند») استفاده می‌کند — کامپوننت
    Livewire هرگز مستقیم مدل `ProcessInstance` را کوئری نمی‌کند (بند ۴).

  **منبع واحد حقیقت برای نتیجه‌ی نهایی:** `ProcessEngine::completeInstance()`
  حالا `$actor`/`$comment` را در طول کل زنجیره‌ی خودکار (شامل چند مرحله‌ی
  condition پشت‌سرهم) حمل می‌کند و در انتها `applyResultAction()` را صدا
  می‌زند — این متد از `config('processes.result_actions')` کلاس Action واقعی
  را می‌خواند و با `(subject, actor, comment)` صدایش می‌زند (همان whitelist
  امنیتی الگوی map/video در SiteBuilder، اینجا برای instantiate کلاس Action).
  `$actor` همان آخرین کاربر انسانی‌ای است که این زنجیره را راه انداخته —
  نقش‌های `assigned_role` مراحل تأیید مرخصی عمداً همان دو نقشی هستند که
  `LeavePolicy::review()` هم‌اکنون مجاز می‌داند (`holding_admin`/`accountant`)
  تا این فراخوانی خودکار همیشه از Gate داخلی `ApproveLeave`/`RejectLeave` رد
  شود؛ در `completeInstance()` وضعیت instance **قبل از** این فراخوانی به
  approved/rejected تغییر می‌کند، پس `hasActiveInstance()` خودِ همین فراخوانی
  خودکار را مسدود نمی‌کند.

  **باگ واقعی کشف‌شده حین نوشتن تست یکپارچه (نه فقط از روی مستندات):**
  - `$instance->subject` (رابطه‌ی `morphTo()`) از global scope خودِ مدل سوژه
    (`BelongsToCompany` روی `Leave`) عبور می‌کند که بر پایه‌ی `CompanyContext`
    فعالِ session فیلتر می‌کند — این فراخوانی داخلی موتور اصلاً به یک شرکت
    فعال در session وابسته نیست، پس همیشه `null` برمی‌گرداند و نتیجه‌ی
    فرایند بی‌صدا هرگز روی سوژه اعمال نمی‌شد. رفع شد با متد خصوصی مشترک
    `resolveSubject()` که همیشه `withoutGlobalScopes()->find()` می‌زند —
    هم در `applyResultAction()` هم در `resolveConditionFieldValue()` (که
    همین باگ را برای خواندن `days_count` هم داشت).
  - **تصمیم طراحی از Session ۲ که در طراحی اول این Session نادیده گرفته
    شد:** `ProcessEngine::resolveEndStatus()` نتیجه‌ی نهایی instance را
    همیشه از روی **نوع نتیجه‌ی transition** تعیین می‌کند
    (`condition_true`→`approved`، `condition_false`→`rejected`)، نه از روی
    این‌که کدام end step فیزیکی هدف است. طراحی اول این Session مسیر «مرخصی
    کوتاه، تأیید مستقیم» را با `condition_false` به سمت `end_approved` سوار
    کرده بود — نتیجه این بود که هر مرخصی کوتاه واقعاً **رد** می‌شد، نه تأیید،
    با اینکه transition به‌ظاهر درست به `end_approved` می‌رفت. با تست
    یکپارچه (نه یک فرض دستی) کشف و اصلاح شد: شرط برعکس شد
    (`condition_operator = LessThanOrEqual`)، پس مسیر «مستقیم تأیید» حالا
    سوار `condition_true` است.

  **تصمیم عملیاتی — چرا `ProcessLeaveDefinitionSeeder` روی دیتابیس واقعی
  `arshaman_erp` اجرا نشد:** این Session صریح گفته بود هیچ UI ساخته نمی‌شود
  (تأیید/رد مرحله‌ی فرایند فقط از طریق Action قابل صدازدن است، نه از پنل).
  اگر این seeder روی شرکت واقعی `arshaman` اجرا می‌شد، از همین الان هر
  درخواست مرخصی واقعی جدید در آن شرکت خودکار وارد فرایند می‌شد و دکمه‌های
  تأیید/رد مستقیم `LeaveIndex` برایش مسدود می‌ماند — بدون هیچ راهی برای
  عبور از آن مرحله تا Session بعدی UI بسازد. پس تأیید کامل روی MySQL واقعی
  (هر ۵ سناریوی خواسته‌شده: خودکار شروع فرایند، مسدودشدن مسیر مستقیم، تأیید
  کوتاه، تشدید و تأیید بلند، رد) با یک شرکت/کاربران/تعریف فرایند کاملاً
  موقت داخل یک `DB::beginTransaction()`/`DB::rollBack()` انجام شد — صفر
  رکورد باقی‌مانده روی `arshaman_erp`، تأیید شده مستقیم بعد از rollback.
  خودِ seeder در `DatabaseSeeder` ثبت است و آماده‌ی اجراست؛ فعال‌سازی واقعی
  آن روی شرکت `arshaman` باید هم‌زمان یا بعد از Session UI باشد، نه زودتر.

  **تست‌ها:** `tests/Feature/Process/LeaveProcessIntegrationTest.php` (۵ تست) —
  شروع خودکار + مسدودشدن مسیر مستقیم، تأیید کوتاه (`<=5` روز) با یک تأیید،
  تشدید مرخصی بلند (`>5` روز) به تأیید مدیر ارشد، رد در مرحله‌ی اول، و
  regression صریح برای شرکت بدون definition فعال (رفتار قدیم دست‌نخورده).
  کل سوییت پروژه: ۵۶۱ سبز، ۱۳ skip (همان CHECKهای mysql-only) — بدون
  رگرسیون.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): هیچ UI برای
تأیید/رد مرحله‌ی فرایند (کارمند/سرپرست باید بتوانند از پنل واقعی اقدام
کنند، نه فقط از طریق Action مستقیم)، فعال‌سازی seeder روی شرکت واقعی
`arshaman`، اتصال فرایند به ماژول‌های دیگر غیر از HR.

- [x] Session 4: پنل طراحی فرایند برای holding_admin — اولین UI واقعی ماژول

  **چه ساخته شد:** `App\Modules\Process\Services\ProcessGraphValidator` —
  اعتبارسنجی کامل گراف *قبل* از نوشتن در دیتابیس، روی آرایه‌های خام فرم
  (step_key به‌عنوان شناسه‌ی گره، چون UUID واقعی هنوز ساخته نشده): هر مرحله
  دقیقاً تعداد/نوع گذار خروجی مجاز خودش را دارد (start=۱، approval/
  condition=۲ با نتیجه‌ی متفاوت، end=۰)، فیلد شرط فقط از whitelist
  `config('processes.condition_fields')`، دسترس‌پذیری همه‌ی مراحل از start
  (BFS)، و نبودِ چرخه (DFS سه‌رنگ) — این سه با هم تضمین می‌کنند هر مسیر
  ممکن قطعاً به یک end می‌رسد، بدون نیاز به بررسی جداگانه‌ی «هر مسیر تمام
  می‌شود». Action های `CreateProcessDefinition`/`UpdateProcessDefinition`
  (authorize صریح + `ProcessGraphValidator` + یک `DB::transaction` واحد
  برای definition+steps+transitions با هم، بند ۹ CLAUDE.md) و
  `ToggleProcessDefinitionActive` (مستقل، همیشه مجاز). کامپوننت‌های
  `ProcessDefinitionIndex`/`ProcessDefinitionForm` (مسیرهای `/processes`,
  `/processes/create`, `/processes/{id}/edit`) — فرم ساختاریافته (فهرست
  مراحل + برای هر مرحله انتخاب گذار خروجی از یک select)، عمداً **نه** یک
  canvas گرافیکی آزاد مثل GrapesJS در SiteBuilder که تجربه‌ی خوبی نداشت.
  منوی «طراحی فرایندها» زیر منوی اصلی (نه زیرمنو)، کنار «سال‌های مالی»،
  با همان شرط دقیق `ProcessDefinitionPolicy::create` (`hasRoleInCompany`
  مقید به شرکت فعال، فقط `holding_admin`).

  **تصمیم این Session — ویرایش ساختاری وقتی تعریف سابقه‌ی instance دارد:**
  `process_instances.current_step_id` و `process_instance_logs.step_id` هر
  دو FK با RESTRICT (بدون CASCADE) به `process_steps` دارند — یعنی حذف
  مراحل قدیمی برای بازسازی ساختار، حتی برای یک instance که سال‌ها پیش
  approved/rejected شده، در سطح دیتابیس با `QueryException` شکست می‌خورد.
  به‌جای اجازه‌دادن به این خطای خام، `UpdateProcessDefinition` از همان
  ابتدا آگاهانه تشخیص می‌دهد تعریف سابقه دارد (هر status ای، نه فقط
  `in_progress`) و در آن حالت فقط `name`/`is_active` را می‌پذیرد؛ ارسال
  steps/transitions تازه بی‌صدا نادیده گرفته می‌شود. **امن‌ترین رفتار:**
  تاریخچه‌ی یک فرایند هرگز زیر پای instance های واقعی عوض نمی‌شود؛ برای
  تغییر واقعی گردش‌کار، یک `process_key` جدید بسازید و نسخه‌ی قدیمی را
  غیرفعال کنید (`is_active` مستقل قابل‌تغییر می‌ماند چون فقط جلوی
  instance های *تازه* را می‌گیرد). `ProcessDefinitionForm` همین قید را در
  UI هم منعکس می‌کند (`hasHistory` — همه‌ی فیلدهای ساختاری غیرفعال + یک
  `x-alert` هشدار).

  **بازدید بصری واقعی** با `php artisan serve` روی `127.0.0.1:8123` (همان
  محدودیت شبکه‌ی sandbox نسبت به دامنه‌ی Apache، مستندشده در ماژول
  SiteBuilder) و یک کاربر تستی موقت روی شرکت واقعی `arshaman`: یک فرایند
  شش‌مرحله‌ای واقعی (start → approval → condition(days_count<=5) →
  senior_approval → دو end) کاملاً از طریق پنل ساخته و ذخیره شد؛ ساختار
  دقیق (۶ مرحله، ۷ گذار) مستقیم از دیتابیس واقعی تأیید شد. سپس همان
  definition (نه یک seed جداگانه) با `ProcessEngine` — بدون هیچ UI بخش
  تأیید/رد — برای هر سه مسیر واقعاً اجرا شد: مرخصی کوتاه (مستقیم تأیید)،
  مرخصی بلند (تشدید به تأیید ارشد)، و رد در مرحله‌ی اول؛ هر سه هم وضعیت
  `ProcessInstance` هم `leaves.leave_status` واقعی (از طریق `ApproveLeave`/
  `RejectLeave` واقعی) درست تغییر کردند — اثبات این‌که فرایند ساخته‌شده از
  UI واقعاً قابل‌اجراست، نه فقط ساختاری معتبر. همه‌ی این اجراها داخل یک
  `DB::beginTransaction()`/`rollBack()` انجام شد (صفر رکورد باقی‌مانده).
  کاربر تستی و definition ساخته‌شده از UI در پایان کامل حذف شدند (بند ۱۰
  CLAUDE.md).

  **تست‌ها:** `tests/Feature/Process/ProcessDefinitionDesignerTest.php` —
  ساخت کامل یک تعریف (definition+steps+transitions در یک تراکنش)، رد
  گذار ناقص، رد فیلد شرط خارج از whitelist، رد مرحله‌ی یتیم، رد چرخه در
  گراف، ۴۰۳ برای operator/accountant/viewer روی هر دو مسیر
  `/processes`|`/processes/create`، و قفل‌شدن ساختار وقتی تعریف حداقل یک
  instance دارد (فقط name/is_active تغییر می‌کند). کل سوییت پروژه: ۵۶۹
  سبز، ۱۳ skip (همان CHECKهای mysql-only) — بدون رگرسیون.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): پنل درخواست فرایند
برای کاربر عادی (شروع instance از UI، نه فقط `ProcessEngine::startInstance`
مستقیم)، پنل تأیید/رد مرحله برای approver (کارمند اختصاص‌یافته)، فعال‌سازی
seeder روی شرکت واقعی `arshaman`، جابه‌جایی/چیدمان بصری مراحل (مثل
drag-and-drop ویجت SiteBuilder — این Session عمداً فقط انتخاب از select بود).

- [x] Session 5: «صندوق کارهای من» — آخرین قطعه‌ی نقشه‌راه اصلی ماژول

  **چه ساخته شد:** سه کامپوننت Livewire جدید، همه در دسترس هر کاربری با هر
  نقش (برخلاف `/processes` که فقط holding_admin است): `MyProcessTasks`
  (`/processes/tasks`)، `NewProcessRequest` (`/processes/request`)،
  `MyProcessRequests` (`/processes/my-requests`). `ProcessInstancePolicy`
  جدید (`view`/`approve`/`reject`) — `approve`/`reject` هرگز منطق تخصیص را
  دوباره پیاده نمی‌کنند، فقط `ProcessEngine::assertActorAuthorizedForStep`
  را به یک بولین بی‌طرف‌شده تبدیل می‌کنند (تنها منبع حقیقت همان می‌ماند).
  کلاس کمکی مشترک `App\Modules\Process\Support\ProcessSubjectSummary` —
  هم برای «کارهای من» هم «درخواست‌های من» — خلاصه‌ی سوژه (وصل‌به‌ماژول) یا
  `request_data` (آزاد) را می‌سازد. منوی سایدبار «طراحی فرایندها» به یک
  `x-menu-sub` «فرایندها» تبدیل شد که این سه مورد را کنار «طراحی فرایندها»
  (هنوز holding_admin-فقط) نشان می‌دهد.

  **تصمیم‌های این Session:**
  - **بند ۴ CLAUDE.md حفظ شد بدون استثنا:** `ProcessSubjectSummary` هرگز
    مدل HR (`Leave`) را import نمی‌کند. نگاشت «کدام فیلد سوژه نمایش داده
    شود» یک کلید داده‌محور تازه در `config/processes.php` است
    (`subject_summary_fields`، مثل `subject_type_labels`/`condition_fields`
    قبلی — همه در همان فایل config پروژه‌ای که از قبل `Leave::class` را
    import می‌کرد، نه در کد ماژول Process) و از `data_get()` عمومی
    (پشتیبان مسیر نقطه‌ای حتی روی رابطه‌ها مثل `employee.full_name`)
    عبور می‌کند، نه دسترسی مستقیم به پراپرتی.
  - **کوئری «کارهای من» بدون فیلتر دستی شرکت:** چون `ProcessInstance` از
    قبل `BelongsToCompany` دارد، global scope خودش کوئری را به شرکت فعال
    سوییچر محدود می‌کند — نیازی به `where('owner_company_id', ...)` دستی
    نبود، فقط فهرست نقش‌های کاربر *در همان شرکت* (`companyRoles()->where
    ('owner_company_id', $companyId)`) برای تطبیق `assignment_type=role`
    لازم بود. `is_super_admin` مثل خودِ `assertActorAuthorizedForStep`
    (که از طریق `hasRoleInCompany` بای‌پس سراسری super_admin را می‌گیرد)
    همه‌ی نقش‌ها را می‌بیند، نه فقط نقش‌های خودش.
  - **تست ۴۰۳ روی مسیر مستقیم Action** (نه فقط پنهان‌بودن دکمه) با دو لایه
    تأیید شد: هم فراخوانی مستقیم `ApproveProcessStep`/`RejectProcessStep`
    با یک actor بدون دسترسی (`toThrow(AuthorizationException::class)`)،
    هم از طریق خودِ کامپوننت Livewire با `Livewire::test(...)->call('approve')
    ->assertForbidden()` — چون Livewire در تست، `AuthorizationException`
    پرتاب‌شده از یک متد کامپوننت را به پاسخ ۴۰۳ واقعی تبدیل می‌کند (نه یک
    exception قابل `toThrow`)؛ این تفاوت رفتار حین نوشتن تست کشف و اصلاح شد.
  - `NewProcessRequest` عمداً `whereNull('subject_type')` را در همان
    computed property فهرست می‌گذارد (نه یک Policy جدا) — فرایند
    وصل‌به‌ماژول اصلاً نباید اینجا دیده شود، صرف‌نظر از نقش کاربر.

  **بازدید بصری کامل از ابتدا تا انتها، فقط از طریق UI (بدون هیچ tinker
  برای تأیید/رد)** با کاربر تستی موقت (`process-visual-test@example.com`،
  نقش `holding_admin` در شرکت واقعی `arshaman`) روی `127.0.0.1:8123`: یک
  تعریف فرایند آزاد دومرحله‌ای (تأیید ۱ → تأیید ۲ → پایان، هر دو مرحله
  role=holding_admin) فقط با tinker *ساخته* شد (چون طراحی گرافیکی این
  زنجیره در Session قبلی این ماژول تست شده بود و اینجا تمرکز روی پنل‌های
  تازه بود، نه خودِ طراح)، سپس کل چرخه‌ی واقعی از مرورگر طی شد: درخواست از
  `/processes/request` با فرم پویای واقعی (نام تجهیز + تعداد) → مشاهده در
  `/processes/my-requests` با وضعیت «در جریان» → تأیید مرحله ۱ از
  `/processes/tasks` با نظر متنی → بازگشت خودکار همان درخواست به فهرست
  کارها برای مرحله ۲ → تأیید مرحله ۲ → فهرست کارها خالی شد → «درخواست‌های
  من» وضعیت «تأیید نهایی» را نشان داد → تاریخچه‌ی کامل (شروع → تأیید ۱ با
  نظر → تأیید ۲ → تکمیل خودکار سیستم) در مودال تاریخچه تأیید شد. تعریف
  فرایند تستی (با همه‌ی step/transition/instance/log آن) و کاربر تستی در
  پایان کامل حذف شدند (بند ۱۰ CLAUDE.md).

  **تست‌ها:** `tests/Feature/Process/MyProcessPanelsTest.php` (۹ تست) —
  دیدن کار فقط توسط نقش تخصیص‌یافته (نه نقش دیگر)، تأیید/رد واقعی از پنل
  با پیشرفت واقعی instance، مسدودشدن ۴۰۳ هم در سطح Action هم در سطح
  کامپوننت Livewire، ثبت درخواست آزاد با `request_data` درست، غیاب
  فرایندهای وصل‌به‌ماژول از «درخواست جدید»، تاریخچه‌ی «درخواست‌های من»
  محدود به درخواست‌های خودِ کاربر، و ایزولاسیون شرکت. کل سوییت پروژه: ۵۷۸
  سبز، ۱۳ skip (همان CHECKهای mysql-only) — بدون رگرسیون.

با این Session، «صندوق کارهای من» — آخرین قطعه‌ی صریحاً باقی‌مانده‌ی نقشه‌راه
ماژول Process — تکمیل شد. آنچه هنوز باز است (فعال‌سازی seeder روی شرکت واقعی
`arshaman`، جابه‌جایی بصری مراحل، اعلان هنگام رسیدن نوبت) در `docs/BACKLOG.md` می‌ماند.

- [x] Session 6: محدودسازی واقعی تأیید/رد + نظارت هلدینگ + حذف واقعی +
  ویرایش تصمیم اخیر + کلید خودکار

  **بخش ۱ — بررسی «bypass» اولیه:** تأیید شد که `hasRoleInCompany` هیچ
  بای‌پس مخصوص `holding_admin` ندارد — تنها بای‌پس واقعی `is_super_admin`
  است (مستند در `User::hasRoleInCompany`). یعنی اگر یک holding_admin واقعی
  توانسته مرحله‌ای واگذارشده به نقش دیگری را تأیید کند، یا آن کاربر واقعاً
  `is_super_admin` بوده، یا (که در این schema اصلاً ممکن نیست چون
  `user_company_roles` یک نقش در هر شرکت را UNIQUE می‌کند) نقش دوم را هم
  داشته. هیچ تغییری در `ProcessInstancePolicy`/`ProcessEngine::assertActorAuthorizedForStep`
  لازم نبود — فقط با تست مستقیم (کاربر holding_admin بدون نقش دیگر → رد
  می‌شود؛ کاربر با نقش واقعاً واگذارشده → مجاز) تأیید و مستند شد.

  **چه ساخته شد (بخش‌های ۱، ۳، ۴، ۵):**
  - `ProcessOversight` (`/processes/oversight`، فقط holding_admin — Policy
    جدید `ProcessInstancePolicy::oversight`): فهرست کل instance های شرکت
    (در جریان + تمام‌شده)، مدت‌زمان مرحله‌ی فعلی (از `ProcessEngine::lastLog()`
    عمومی جدید، نه یک ستون تازه)، تاریخچه، و «یادآوری» (Action
    `RecordProcessReminder` → `ProcessEngine::remind()`، فقط یک لاگ جدید
    `LogAction::Reminder`، بدون تغییر current_step/status). یادآوری در
    `MyProcessTasks` به‌صورت یک `x-alert` برجسته بالای کار نشان داده
    می‌شود اگر آخرین لاگ instance دقیقاً همان یادآوری باشد.
  - **کشف واقعی حین پیاده‌سازی:** `created_at` با دقت ثانیه یعنی دو لاگ
    پشت‌سرهم (مثل started و reminder بلافاصله بعدش) در `latest('created_at')`
    خام تساوی می‌خورند و ترتیب غیرقطعی می‌شود. چون `HasUuids` پیش‌فرض پروژه
    از `Str::orderedUuid()` (UUID زمان‌محور) استفاده می‌کند، `id` یک
    تای‌برک واقعاً هم‌ترتیب زمانی است — متد عمومی جدید
    `ProcessEngine::lastLog()` با `orderByDesc('created_at')->orderByDesc('id')`
    این را حل کرد و به‌جای تکرار کوئری، هم `MyProcessTasks` (بنر یادآوری)
    هم `ProcessOversight` (مدت‌زمان مرحله) هم خودِ موتور (تشخیص آخرین
    تصمیم قابل‌بازگردانی) از همین یک متد استفاده می‌کنند.
  - حذف واقعی: ستون `deleted_at` (`SoftDeletes`) روی `process_definitions`.
    Action جدید `DeleteProcessDefinition` — بدون هیچ instance (حتی
    تاریخی) → حذف کامل (مراحل+گذارها+خودِ تعریف در یک تراکنش، چون
    RESTRICT FK اجازه نمی‌داد)؛ با حداقل یک instance → فقط soft-delete
    (تاریخچه/لاگ دست‌نخورده). دکمه‌ی حذف در `ProcessDefinitionIndex` با
    `wire:confirm` که پیام‌اش بسته به `instances_count` فرق می‌کند.
  - ویرایش تصمیم اخیر: `ProcessEngine::reverseLastDecision()` — فقط اگر
    آخرین لاگ instance دقیقاً همان تصمیم (approved/rejected) actor باشد
    (یعنی هیچ اتفاقی — حتی ارزیابی خودکار شرط یا تکمیل فرایند — بعدش
    نیفتاده). رکورد لاگ اصلی هرگز حذف/محتوایش ویرایش نمی‌شود؛ فقط یک
    ستون متادیتای جدید `reversed_at` روی همان رکورد ست می‌شود و یک لاگ
    جدید (`LogAction::Reversed`) اضافه می‌شود. `current_step_id` به همان
    مرحله برمی‌گردد. دکمه‌ی «تاریخچه» هم به `MyProcessTasks` اضافه شد
    (نیازمند گسترش `ProcessInstancePolicy::view` تا مسئول فعلی مرحله را
    هم — نه فقط کسی که قبلاً اقدام کرده — قبل از تصمیم‌گیری مجاز کند).
  - تولید خودکار `process_key`/`step_key`: هر دو فیلد کامل از UI حذف
    شدند. `process_key` سمت سرور در لحظه‌ی `save()` از روی نام تولید
    می‌شود (`resolveUniqueProcessKey` — پسوند عددی در تصادم، دقیقاً الگوی
    autosave اسلاگ ماژول Blog)؛ `step_key` از قبل در `emptyStep()` همین
    الگو را داشت (فقط UI‌اش حذف شد). تأیید شد اتصال HR/مرخصی
    (`ProcessEngine::startForSubjectIfActive`) فقط با `subject_type` کار
    می‌کند، نه `process_key` — بدون رگرسیون.

  **بازدید بصری واقعی** با `php artisan serve` روی `127.0.0.1:8123` و دو
  کاربر تستی موقت (holding_admin + accountant) روی شرکت واقعی `arshaman`:
  ساخت یک فرایند از UI بدون هیچ فیلد کلید، درخواست و مشاهده‌ی آن در
  نظارت، ارسال یادآوری واقعی و دیدن بنرش در «کارهای من»، تأیید یک زنجیره‌ی
  دومرحله‌ای و بازگردانی واقعی تصمیم (تأیید شد در دیتابیس: `reversed_at`
  ست شد، لاگ `reversed` اضافه شد، `current_step_id` برگشت، کار دوباره در
  فهرست ظاهر شد)، و حذف واقعی یک تعریف با سابقه (soft-delete، پنهان از
  فهرست فعال). **محدودیت شناخته‌شده:** دکمه‌های `wire:confirm` (حذف/
  بازگردانی) با کلیک ساده در این محیط sandbox قابل تأیید نبودند (دیالوگ
  `confirm()` بومی مرورگر)؛ برای اثبات مسیر واقعی سرور، `window.confirm`
  موقتاً override و متد Livewire مستقیم از کنسول صدا زده شد — نه یک
  میان‌بر تست، بلکه دور زدن محدودیت خودِ ابزار خودکارسازی مرورگر برای
  دیدن نتیجه‌ی واقعی روی دیتابیس. کاربران/تعاریف/instance های تستی در
  پایان کامل حذف شدند (بند ۱۰ CLAUDE.md).

  **تست‌ها:** `tests/Feature/Process/ProcessOversightAndControlsTest.php`
  (۱۷ تست جدید). کل سوییت پروژه: ۵۹۵ سبز، ۱۳ skip (همان CHECKهای
  mysql-only) — بدون رگرسیون.

- [x] Session 7: رفع باگ تاریخ، شرط روی فرم فرایند آزاد، فرم اختیاری هر
  مرحله، ایزوله‌سازی خطای موتور، کلید خودکار، ترتیب نمایش، برچسب فارسی شرط

  **بخش ۱ — باگ نمایش تاریخ:** `ProcessSubjectSummary::formatValue()`
  مقادیر `DateTimeInterface` (مثل `Leave.start_date`/`end_date` با cast
  `'date'`) را قبلاً چون `is_scalar(Carbon)` false است، به شاخه‌ی
  `json_encode` می‌فرستاد — دقیقاً همان میلادی خام با صفرهای اضافی گزارش‌شده.
  حالا از `App\Support\Jalali::toDisplay()`/`toDisplayTime()` عبور می‌کند؛
  اگر ساعت دقیقاً نیمه‌شب بود (ستون DATE خالص)، فقط تاریخ نشان داده می‌شود.

  **بخش ۲ — شرط روی فرم خودِ فرایند آزاد:** `ProcessGraphValidator::validate()`
  پارامتر چهارم `$requestFormFields` گرفت — برای فرایند آزاد، whitelist
  فیلد شرط از کلیدهای همان فرم (نه یک منبع سراسری) می‌آید؛ برای فرایند
  وصل‌به‌ماژول همان `config('processes.condition_fields')` قبلی. همین دو
  whitelist موازی در `ProcessEngine::resolveConditionFieldValue()` هم
  تکرار شد. **کشف حین تست:** این متد داخلی موتور برای خواندن
  `$instance->definition->request_form_fields` باید حتماً
  `ProcessDefinition::withoutGlobalScopes()->find(...)` بزند (نه رابطه‌ی
  Eloquent خام) — دقیقاً همان درسِ `resolveSubject()` قبلی: بدون
  `CompanyContext` فعال (مثل یک تست یا فرآیند خودکار)، global scope
  `BelongsToCompany` بی‌صدا `null` برمی‌گرداند.

  **بخش ۳ — فرم اختیاری هر مرحله:** ستون‌های جدید `process_steps.step_form_fields`
  (JSON nullable، همان ساختار `request_form_fields`) و
  `process_instance_logs.step_data` (JSON nullable). کلاس جدید و مشترک
  `App\Modules\Process\Support\StepFormValidator` — هم
  `ApproveProcessStep` هم `RejectProcessStep` قبل از صدازدن
  `ProcessEngine::advance()` مقادیر ارسالی را با آن اعتبارسنجی می‌کنند؛
  `advance()`/`log()` پارامتر `stepData` گرفتند تا فقط در همان یک رکورد
  لاگ تصمیم (نه لاگ‌های خودکار condition/completed) ذخیره شود. UI: در
  `ProcessDefinitionForm` یک چک‌باکس «این مرحله فرم اضافه دارد؟» (فقط
  toggle نمایشی Alpine محلی، بدون نیاز به یک property جدا در Livewire —
  چون حضور فیلدهای دارای برچسب در `extractPayload()` خودش تعیین‌کننده است)
  و در `MyProcessTasks` مودال تأیید/رد فیلدهای همان مرحله را پویا نشان
  می‌دهد (`getCommentStepFormFieldsProperty()`).

  **بخش ۴ — ایزوله‌سازی خطای موتور (بند ۴ Session جاری، حیاتی):**
  `RequestLeave::handle()` فراخوانی
  `ProcessEngine::startForSubjectIfActive()` را در یک `try/catch(Throwable)`
  جدا از بقیه‌ی تراکنش گرفت — خطای موتور فقط `Log::error()` می‌شود، هرگز
  اجازه نمی‌دهد رکورد `Leave` ثبت‌نشده بماند. **تأیید واقعی در مرورگر:** یک
  تعریف فرایند فعال برای `Leave` بدون هیچ مرحله‌ی `start` روی دیتابیس واقعی
  ساخته شد (خطای تضمین‌شده‌ی `ProcessEngine::startInstance()`)، سپس از
  پنل خودِ کارمند (`/my/leaves`) یک مرخصی واقعی ثبت شد — رکورد با موفقیت
  در جدول ظاهر شد و خطا دقیقاً در `storage/logs/laravel.log` لاگ شد، بدون
  هیچ ۵۰۰ یا رکورد ثبت‌نشده.

  **بخش ۵ — کلید خودکار فیلدهای فرم:** ورودی «کلید» از UI فرم درخواست حذف
  شد؛ `ProcessDefinitionForm::newFieldKey()` دقیقاً همان الگوی
  `emptyStep()`/`step_key` را برای `requestFormFields` و
  `step_form_fields` تکرار می‌کند (slug برچسب موجود + پسوند تصادفی، یک‌بار
  در لحظه‌ی افزودن، نه از روی تایپ کاربر).

  **بخش ۶ — حفظ ترتیب نمایش:** ستون `display_order` (unsigned smallint) به
  `process_steps`/`process_transitions` اضافه شد؛ `CreateProcessDefinition`/
  `UpdateProcessDefinition` آن را از روی ترتیب واقعی آرایه‌ی ورودی پر
  می‌کنند؛ `ProcessDefinition::steps()`/`ProcessStep::outgoingTransitions()`
  هر دو `orderBy('display_order')` گرفتند — فقط نمایش، بدون اثر روی
  `ProcessEngine::moveFrom()` که مستقل بر پایه‌ی `from_step_id`/`on_result`
  کوئری می‌زند.

  **بخش ۷ — برچسب فارسی/راهنمای فیلد شرط ماژول‌محور:** کلید جدید
  `condition_field_labels` در `config/processes.php` (label+hint فارسی
  برای `days_count`/`leave_type` مرخصی).
  `ProcessDefinitionForm::conditionFieldOptions`/`conditionFieldHints`
  حالا هر دو منبع (فرایند آزاد از خودِ فرم، فرایند وصل‌به‌ماژول از این
  config) را به‌جای نام انگلیسی خام برمی‌گردانند. **کشف حین بازدید بصری:**
  select فیلد شرط باید `wire:model.live` باشد (نه `wire:model` ساده) وگرنه
  راهنمای متنی زیر آن — چون سمت سرور رندر می‌شود — تا یک round-trip دیگر
  به‌روزرسانی نمی‌شد؛ در مرورگر واقعی انتخاب می‌شد ولی هیچ راهنمایی ظاهر
  نمی‌شد. رفع شد و دوباره در مرورگر واقعی تأیید شد.

  **بازدید بصری واقعی این Session** با `php artisan serve` روی
  `127.0.0.1:8123` و یک کاربر تستی موقت روی شرکت واقعی `arshaman`: افزودن
  فیلد فرم آزاد بدون کلید دستی، دیدن برچسبش در select فیلد شرط، سوییچ به
  `Leave` و دیدن برچسب/راهنمای فارسی زنده، و کل بخش ۴ طبق بالا. کاربر،
  پرسنل تستی، مرخصی تستی، و تعریف‌های فرایند تستی در پایان کامل حذف شدند
  (بند ۱۰ CLAUDE.md).

  **تست‌ها:** `tests/Feature/Process/ProcessSessionFixesTest.php` (۱۳ تست
  جدید). کل سوییت پروژه: ۶۰۹ سبز، ۱۳ skip (همان CHECKهای mysql-only) —
  بدون رگرسیون.

نساز این Session (خارج از scope، در `docs/BACKLOG.md`): بازطراحی UI
ویزارد/مرحله‌به‌مرحله، جابه‌جایی درگ‌اند‌دراپ گذارها در UI.

- [x] Session 8: بازطراحی کامل دیتابیس — حذف کامل JSON، نرمال‌سازی، ENUM محدود subject_type

  **چه ساخته شد (شش migration جدید، `2026_08_19_100001` تا `100006`):**
  - **بخش ۱:** `process_definitions.subject_type`/`process_instances.subject_type`
    از VARCHAR آزاد به ENUM نیتیو MySQL با یک مقدار مجاز فعلی
    (`'App\Modules\HR\Models\Leave'`، دقیقاً FQCN، نه نام کوتاه — نگاه کن
    `docs/DATABASE_CONVENTIONS.md` بند ۱۶). روی sqlite با تکنیک rename+PRAGMA
    بازسازی کامل (همان الگوی بند ۱۵)، روی mysql فقط `ALTER ... MODIFY COLUMN`.
  - **بخش ۲:** جدول جدید `process_form_fields` (پلی‌مورفیک محض
    formable_type/formable_id، بدون FK واقعی روی formable_id — دقیقاً الگوی
    subject_type/subject_id در `process_instances`؛ `field_type` هفت مقدار:
    `text,number,textarea,file,select,boolean` — طبق تصمیم صریح کارفرما «select»
    اضافه و «boolean» حفظ شد، نه هفت‌تای دیگر). جایگزین کامل
    `process_definitions.request_form_fields`/`process_steps.step_form_fields`
    (هر دو ستون JSON حذف شدند). مهاجرت داده با شمارش تأیید (تعداد JSON قبل =
    تعداد ردیف نوشته‌شده بعد، وگرنه throw قبل از drop).
  - **بخش ۳:** `process_steps.condition_field` (VARCHAR آزاد) حذف، جایگزین با
    `condition_field_id` (FK واقعی به `process_form_fields`، فقط شرط روی فرم
    آزاد همان تعریف) و `condition_module_field` (VARCHAR، شرط روی فیلد ماژول).
    CHECK دستی `chk_process_steps_condition_source` (guard غیر-sqlite، دقیقاً
    یکی پر وقتی step_type=condition).
  - **بخش ۴:** دو جدول جدید `process_instance_field_values`/
    `process_instance_log_field_values` (هر دو FK واقعی به `process_form_fields`
    + UNIQUE روی (parent_id, process_form_field_id)). جایگزین کامل
    `process_instances.request_data`/`process_instance_logs.step_data`.
  - سرویس جدید `ProcessFormFieldResolver` (نگاشت field_key↔id، ذخیره/خواندن
    دسته‌ای مقادیر) — منبع واحد بین `ProcessEngine`، Action ها، و پنل‌های
    Livewire. مدل‌های جدید `ProcessFormField`/`ProcessInstanceFieldValue`/
    `ProcessInstanceLogFieldValue`. `FormFieldValidator`/`StepFormValidator`
    از آرایه‌ی JSON خام به `Collection<ProcessFormField>` تغییر امضا دادند.
  - **بخش ۵:** درگ‌اند‌دراپ فیلدهای فرم (درخواست + هر مرحله) و خودِ مراحل در
    فهرست خلاصه‌ی مرحله ۳ ویزارد — تابع عمومی جدید `window.initProcessFieldSortable`
    در `resources/js/process-sortable.js` (کنار `initProcessTransitionSortable`
    موجود)، متدهای `moveRequestFieldRow`/`moveStepFormFieldRow`/`moveStepRow`
    در `ProcessDefinitionForm` (مرحله‌ی start همیشه اول می‌ماند، دستگیره‌ی درگ
    رویش مخفی است — مثل دکمه‌ی حذف).
  - **بخش ۶:** خلاصه‌ی شرط («شرط: {فیلد} {عملگر} {مقدار}») بالای گذارهای هر
    مرحله‌ی condition در مرحله ۴ ویزارد — computed property
    `getConditionSummaryProperty()`.

  **تصمیم‌های این Session:**
  - `UpdateProcessDefinition`/`CreateProcessDefinitionVersion` قبل از بازسازی
    ساختار، `process_form_fields` مرحله‌ی قدیمی/تعریف را هم صریح پاک می‌کنند
    (نه فقط steps/transitions) — چون این مسیر فقط وقتی تعریف صفر instance
    دارد اجرا می‌شود، حذف امن است (همان تحلیل قبلی حذف steps).
  - **باگ واقعی کشف‌شده حین اجرای واقعی روی MySQL (نه فقط sqlite تست):**
    `DB::statement("... ENUM('{$fqcn}') ...")` با یک رشته‌ی PHP حاوی بک‌اسلش
    خام (`App\Modules\HR\Models\Leave`) نوشته شده بود — در یک رشته‌ی SQL خام
    mysql، بک‌اسلش کاراکتر escape است (مگر `NO_BACKSLASH_ESCAPES`)، پس mysql
    بی‌صدا هر بک‌اسلش را حذف می‌کرد و ستون واقعی `ENUM('AppModulesHRModelsLeave')`
    ساخته می‌شد — هر seed/insert بعدی با «Data truncated» شکست می‌خورد. روی
    sqlite (که این ALTER خام را اصلاً اجرا نمی‌کند، فقط Blueprint با parameter
    binding) این باگ اصلاً دیده نمی‌شد؛ فقط اجرای واقعی روی MySQL آن را نشان
    داد. رفع شد با `str_replace('\\', '\\\\', ...)` قبل از ساخت رشته‌ی SQL.
    **درسِ تکرارشونده‌ی پروژه (بند ۹.۱۲-مانند): رفتار دقیق یک لایه‌ی خارجی
    (اینجا: قوانین escape رشته‌ی خام mysql) را هرگز فقط از تست sqlite فرض نکن
    — قبل از اعلام «تمام»، حتماً یک‌بار روی MySQL واقعی هم اجرا کن.**
  - `ProcessSubjectSummary::forRequestData()` کشف حین بازدید بصری واقعی: فیلد
    select مقدار خام (`value`, مثل `high`) را ذخیره می‌کند نه برچسب نمایشی —
    بدون نگاشت صریح از `options`، پنل‌های «کارهای من»/«درخواست‌های من» مقدار
    انگلیسی/کد خام را به‌جای برچسب فارسی («فوری») نشان می‌دادند. رفع شد.
  - migration بخش ۲ (`2026_08_19_100003`) باید CHECK قدیمی
    `chk_process_definitions_subject_or_form` را قبل از `dropColumn('request_form_fields')`
    حذف کند (mysql رد می‌کند: «Check constraint uses this column»)، هرگز روی
    sqlite (که این CHECK را از اول نداشت).

  **تأیید مستقیم روی MySQL واقعی:** هر ۶ migration با `php artisan migrate --force`
  روی `arshaman_erp` واقعی اجرا شد (بدون `fresh`، طبق بند ۸ CLAUDE.md؛ یک بار
  به‌خاطر باگ CHECK بالا شکست خورد، rollback کامل ۶ پله + اصلاح + دوباره
  اجرا). داده‌ی واقعی موجود (یک `process_definitions` با `request_form_fields`
  و یک `condition_field`) بدون گم‌شدن مهاجرت شد (تأیید مستقیم با کوئری،
  نه فقط پیام موفقیت). `ProcessSampleSeeder`/`ProcessLeaveDefinitionSeeder`
  (هر دو به‌روزرسانی‌شده برای معماری جدید) دوباره روی همان دیتابیس اجرا شدند.

  **بازدید بصری واقعی** با `php artisan serve` روی `127.0.0.1:8123` و یک
  کاربر تستی موقت (holding_admin، شرکت واقعی `arshaman`): فرایند جامع
  «درخواست خرید تجهیزات» از صفر ساخته شد — فرم درخواست سه‌فیلدی (متن، عدد،
  select با دو گزینه)، مرحله‌ی تأیید، مرحله‌ی شرط (خلاصه‌ی «شرط: تعداد
  بزرگ‌تر از 5» واقعاً دیده شد)، مرحله‌ی تکمیل اطلاعات با فیلد فایل، دو پایان.
  اجرای واقعی کامل: ثبت درخواست با select واقعی، تأیید سرپرست، ارزیابی خودکار
  شرط (۸>۵ → مسیر true)، تکمیل مرحله‌ی فایل، رسیدن به «تأیید نهایی» — تاریخچه‌ی
  کامل هر پنج رویداد (با مقدار «فوری» درست‌نمایش‌داده‌شده و لینک دانلود واقعی
  فایل) در `/processes/my-requests` تأیید شد. درگ‌اند‌دراپ فیلدها/مراحل به‌خاطر
  محدودیت شبیه‌سازی درگ واقعی در ابزار خودکارسازی مرورگر headless (بدون
  کامپوزیت صفحه) از طریق ۳ تست Feature جدید (`ProcessFormFieldDragTest`)
  تأیید شد، نه با درگ واقعی ماوس. کاربر تستی، تعریف فرایند تستی (همه‌ی
  steps/form fields/transitions اش)، instance تستی، و فایل آپلودشده در پایان
  کامل از دیتابیس واقعی `arshaman_erp` حذف شدند (بند ۱۰ CLAUDE.md) — تأیید
  شد صفر رکورد باقی‌مانده.

  **تست‌ها:** ۵ فایل تست جدید (`SubjectTypeEnumTest`, `FormFieldsMigrationTest`,
  `ConditionFieldFkTest`, `InstanceDataMigrationTest`, `ProcessFormFieldDragTest`)
  + همه‌ی تست‌های موجود ماژول Process که مستقیم ستون‌های JSON/`condition_field`
  خام را assert می‌کردند به رابطه‌ها/جداول جدید اصلاح شدند (نه حذف). کل سوییت
  پروژه: ۶۵۳ سبز، ۱۵ skip (همان CHECKهای mysql-only + دو تست ENUM جدید) —
  بدون رگرسیون.

با این Session، دیگر هیچ ستون JSON برای فرم/مقدار در ماژول Process باقی
نمانده؛ همه‌چیز از طریق جداول واقعی با کلید خارجی معتبر کار می‌کند.

> این بخش را بعد از هر Session به‌روز کن. این حافظه بلندمدت پروژه است.
