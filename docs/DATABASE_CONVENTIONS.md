# DATABASE_CONVENTIONS.md — قرارداد نام‌گذاری دیتابیس
## پروژه ERP آرشامان

> هدف این سند: هیچ‌کس (نه Claude Code، نه تو، نه یک برنامه‌نویس جدید که بعداً می‌آید) نباید برای فهمیدن
> «این ستون دقیقاً چه چیزی را نگه می‌دارد» کد را باز کند. اسم ستون خودش باید جواب بدهد.
> این سند مکمل بخش ۳ فایل `CLAUDE.md` است؛ آنجا فقط خلاصه و ارجاع به این‌جا آمده.

---

## اصل کلی

هر ستون باید به این سؤال جواب بدهد: **«اگر فقط اسم این ستون را ببینم، بدون دیدن کد یا مدل، می‌فهمم چه رابطه‌ای با چه چیزی دارد؟»**
اگر جواب «نه، باید بروم کد را نگاه کنم» است، اسم ستون ضعیف است.

---

## ۱. ستون‌های ردگیری (Audit) — همیشه با نقش کامل

```
created_by   →  created_by_user_id     (FK → users.id)
updated_by   →  updated_by_user_id     (FK → users.id)
             →  deleted_by_user_id     (جدید — کِی soft delete شد، کی حذف کرد)
```

این سه ستون روی **همه جدول‌های عملیاتی** می‌آیند (نه جدول‌های سراسری خیلی ساده مثل `roles`/`permissions` که تغییرشان نادر و توسط seeder است).

**قانون تکمیلی:** هر سه باید constraint واقعی `FOREIGN KEY ... REFERENCES users(id)` داشته باشند، نه فقط CHAR(36) بدون قید — این هم درستی داده را تضمین می‌کند هم (چون FK در MySQL خودکار ایندکس می‌سازد) به راندمان کوئری کمک می‌کند.

---

## ۲. کلید خارجی — قانون: همیشه نقش کامل، حتی اگر روشن باشد

طبق تصمیم پروژه، **هر FK نام نقشش را با خودش حمل می‌کند**، حتی وقتی جدول فقط یک ارتباط با آن جدول مقصد دارد. یعنی به‌جای تکیه به قرارداد ضمنی، همیشه صریح می‌نویسیم:

```
company_id      →  owner_company_id      (شرکتی که این رکورد را مالک است — از طریق BelongsToCompany)
user_id         →  به‌جای user_id خام، بگو نقشش چیست:
                    requested_by_user_id / approved_by_user_id / assigned_to_user_id / invited_by_user_id
role_id         →  assigned_role_id       (وقتی در جدول واسط user_company_roles نقش را نگه می‌دارد)
```

برای جدول‌هایی که واقعاً فقط یک رابطه ساده و طبیعی با جدول مقصد دارند و نقش دیگری غیر از «همان موجودیت» ندارند
(مثل `product_id` در `order_lines`، یا `currency_id` در `exchange_rates`)، نام نقش با نام موجودیت یکی می‌شود —
این خودش «نقش کامل» است، چیزی برای اضافه‌کردن نیست.

### owner_company_id — تغییر پایه‌ای

از این به بعد، trait اصلی چندشرکتی‌بودن به این شکل است (نسخه قبلی `CLAUDE.md` را جایگزین می‌کند):

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

هر جدول عملیاتی که قبلاً `company_id` داشت (یا در مستندات فازهای بعدی طراحی شده)، از این به بعد `owner_company_id` می‌نویسیم.

---

## ۳. وضعیت‌ها (Enum/Status) — هرگز فقط `status`

اگر جدولی ممکن است بیش از یک بعد وضعیت داشته باشد (حالا یا در آینده)، اسم بعد را در ستون بیاور:

```
status          →  order_status         (چون بعداً payment_status هم جدا لازم می‌شود)
                →  shipment_status
                →  invoice_status
```

اگر یک جدول قطعاً فقط یک بعد وضعیت خواهد داشت و هیچ‌وقت بعد دوم پیدا نمی‌کند (نادر است)، همان `status` قابل قبول است — ولی پیش‌فرض همیشه نام‌دار کردن است.

---

## ۳.۱. طول ستون‌های enum-like

| نوع enum | طول پیشنهادی | مثال |
|---|---|---|
| دو-حالته کوتاه (self/admin) | VARCHAR(10) | recorded_by |
| معمول (۳ تا ۶ مقدار) | VARCHAR(20) | employment_status, leave_status, payroll_status |
| ترکیبی/چندکلمه‌ای (project_based, physical_goods) | VARCHAR(30) | business_type, contract_type |

---

## ۳.۲. محافظت CHECK در سطح دیتابیس

هر ستون enum-like که پایه یک عملیات مالی یا قفل‌شونده است (مثل `payroll_status`،
`leave_status`، `expense_posting_status`، `employment_status`) باید علاوه بر enum
سطح PHP، یک **CHECK CONSTRAINT** در دیتابیس هم داشته باشد به‌عنوان لایه دفاعی دوم،
چون منبع دیگری غیر از Laravel (seeder، import، query دستی) هم می‌تواند به جدول
بنویسد.

تغییر مقادیر مجاز بعداً فقط نیاز به `ALTER TABLE ... DROP CHECK ... ADD CHECK`
دارد، نه migration سنگین.

---

## ۴. مبالغ و ارز

```
amount          →  sale_amount / cost_amount / shipping_amount / gross_amount / net_amount
```
هر ستون مبلغی یا کنار خودش `currency_id` دارد، یا صراحتاً یک snapshot با نام گویاست:
```
exchange_rate           →  exchange_rate_at_order    (وقتی چند نرخ در جدول‌های مختلف معنا دارند و باید مشخص شود مال چه لحظه‌ای است)
```
در جایی که فقط یک نرخ در کل جدول معنا دارد (مثل `orders.exchange_rate`، طبق تعریف بخش ۵.۲ CLAUDE.md که خودش snapshot سفارش است)، همان `exchange_rate` کافی است چون نقش کامل است.

---

## ۵. بولین‌ها

پیشوند `is_`/`has_` (از قبل رعایت شده): `is_active`, `is_super_admin`, `has_paid`.

---

## ۶. تاریخ و زمان

- `_at` برای لحظه دقیق با ساعت: `paid_at`, `shipped_at`, `delivered_at`, `accepted_at`
- `_date` برای تاریخ تقویمی بدون ساعت: `effective_date`, `expires_date`

---

## ۷. ستون‌های Snapshot

هر ستونی که مقدارش هنگام ثبت **کپی** شده (نه reference زنده)، اگر نامش به‌تنهایی این را نمی‌رساند، باید گویا شود.
مثال‌های فعلی پروژه (`orders.exchange_rate`, `order_lines.cost_amount`, `order_lines.fulfillment_type`) چون در بخش ۵.۲ CLAUDE.md صراحتاً به‌عنوان snapshot مستند شده‌اند، نیازی به تغییر اسم ندارند — مستندسازی جای تغییر اسم را همان‌جا گرفته.

---

## ۸. جدول‌های واسط (Pivot/Junction)

نام‌گذاری: `جدول_اول_جدول_دوم` به ترتیب منطقی (نه الفبایی صرف)، طبق الگوی موجود:
```
role_permission        (نقش ↔ مجوز)
user_company_roles     (کاربر ↔ شرکت ↔ نقش — قلب سیستم دسترسی)
```
ستون‌های داخل این جدول‌ها هم از قانون بخش ۲ پیروی می‌کنند (`user_id`, `owner_company_id` یا `assigned_role_id` طبق نقش).

---

## ۹. ایندکس و Constraint — نام‌گذاری (از `schema_auth_mysql.sql` رسمی شد)

```
fk_<جدول>_<نقش‌ستون>      مثال: fk_ucr_user, fk_inv_company
uq_<جدول>_<ستون‌ها>        مثال: uq_user_company (روی user_id+owner_company_id)
idx_<جدول>_<ستون‌ها>       مثال: idx_activity_company
```

**قانون راندمان:** هر FK باید ایندکس داشته باشد. در MySQL هر `FOREIGN KEY` خودکار ایندکس می‌سازد — پس هرگز یک رابطه FK را بدون قید `CONSTRAINT ... FOREIGN KEY` تعریف نکن، حتی اگر فقط برای ردگیری (audit) باشد.

---

## ۱۰. استثنا: ستون‌های پکیج‌های خارجی

جدول‌هایی که توسط پکیج‌های خارجی ساخته می‌شوند (`sessions` توسط Laravel، `activity_log` توسط Spatie) ساختار داخلی‌شان دست ما نیست — نام ستون‌هایشان (`user_id`, `causer_id`, `subject_id`, `subject_type`) را همان‌طور که پکیج تعریف کرده نگه می‌داریم. این قرارداد فقط روی جدول‌هایی که خودمان طراحی می‌کنیم اعمال می‌شود.

---

## ۱۱. جدول مرجع سریع (قبل → بعد)

| قبل | بعد |
|---|---|
| `company_id` | `owner_company_id` |
| `created_by` | `created_by_user_id` |
| `updated_by` | `updated_by_user_id` |
| `invited_by` | `invited_by_user_id` |
| `status` (بدون بعد مشخص) | `<چیزی>_status` |
| `amount` (بدون واحد/نقش) | `<نقش>_amount` |

---

## ۱۲. چه چیزی در `schema_auth_mysql.sql` عوض شد

- `companies.created_by/updated_by` → `created_by_user_id/updated_by_user_id` + قید FK واقعی به `users(id)` اضافه شد
- `users.created_by/updated_by` → همان تغییر
- `user_company_roles.created_by` → `created_by_user_id` + قید FK
- `user_invitations.invited_by` → `invited_by_user_id` (قید FK از قبل وجود داشت، فقط نام عوض شد)
- هیچ‌کدام از جدول‌های این ماژول هنوز `company_id` به‌معنای BelongsToCompany ندارند (چون `companies`/`users`/`roles` سراسری‌اند) — قانون `owner_company_id` از اولین ماژول عملیاتی بعدی (مثلاً Parties یا Products) اعمال می‌شود.

---

## ۱۳. اصلاحات طول/CHECK اعمال‌شده در migration های اصلاحی

**`2026_08_03_000001_fix_datatypes_and_add_checks`** — اولین دور: `employees.national_id`
از VARCHAR(10) به CHAR(10)، به‌علاوه CHECK روی `employees.employment_status`/`contract_type`،
`attendances.recorded_by`، `leaves.leave_type` (شامل `hourly`)/`leave_status`،
`payroll_runs.payroll_status`/`period_month` (فرمت)، `payslips.expense_posting_status`،
`companies.business_type`، `monthly_attendance_summaries.period_month` (فرمت).

**`2026_08_04_000001_shrink_column_widths_and_add_more_checks`** — دور دوم، گسترده‌تر
روی چهار ماژول Auth/HR/CRM/Core:
- کوچک‌کردن VARCHAR های بیش‌ازحد بزرگ (`companies.name/slug`, `users.full_name`,
  `roles.name/display_name`, `permissions.name/display_name`, `user_invitations.full_name`,
  `employees.full_name/address/position`, `holidays.title`, `payslips.expense_posting_status`,
  `contacts.full_name`, `contact_site_profiles.site_full_name`, `currencies.name`,
  `fiscal_periods.name`) طبق جدول طول‌های بخش ۳.۱.
- تبدیل به CHAR برای ستون‌های طول‌ثابت (`companies.base_currency`, `user_invitations.token`,
  `employees.phone`, `payroll_runs.period_month`, `monthly_attendance_summaries.period_month`,
  `parties.economic_code`, `currencies.code`) — بعد از تأیید اینکه داده موجود دقیقاً هم‌طول است.
- CHECK جدید: `employees.position` (enum جدید `App\Modules\HR\Enums\EmployeePosition`،
  با backfill داده آزاد قبلی به `graphic_designer`)، `employees.phone` (فرمت موبایل ایران)،
  `interactions.interaction_type`، `leads.source`/`pipeline_stage`، `rfm_segments.segment`،
  `parties.party_type`.
- **رد شد (جدول هنوز ساخته نشده):** `campaigns.channel`, `campaign_logs.channel/status`,
  `tickets.status/priority` — نگاه کن `docs/BACKLOG.md`، بخش «CHECK constraint های
  campaigns/campaign_logs/tickets».
- **عمداً دست‌نخورده ماند:** `leaves.leave_type` — CHECK همان migration قبلی
  (`IN ('annual','sick','unpaid','hourly')`) باقی می‌ماند؛ لیست این دور `hourly` را نداشت
  ولی تنگ‌ترکردن، رکورد واقعی approved با این نوع را می‌شکست.

**`2026_08_05_000001_adjust_party_column_widths`** — تکمیل ماژول Core (طرف‌حساب‌ها):
`parties.name` از VARCHAR(200) به VARCHAR(150) (بلندتر از `employees.full_name`
چون نام اشخاص حقوقی می‌تواند بلندتر باشد)، `parties.email` از VARCHAR(200) به
VARCHAR(255). **عمداً بدون CHECK دیتابیس برای فرمت `economic_code`** (دقیقاً ۱۲ رقم):
دو رکورد واقعی فعلی کد اقتصادی ۴ رقمی تستی دارند که CHECK REGEXP را همان لحظه
migration می‌شکست؛ کارفرما validation سطح Laravel (`PartyForm::rules()`) را به‌جای
پاک‌کردن آن داده تأیید کرد. `chk_parties_role` و `chk_parties_party_type` از
migration های قبلی (`2026_08_01`/`2026_08_04`) بدون تغییر باقی ماندند.

**`2026_08_06_100001_create_product_categories_table` و
`2026_08_06_100002_create_products_table`** — ماژول Catalog (Epic 5):
`products.name` از ابتدا VARCHAR(150) طبق قرارداد طول جدید (نه ۲۰۰/۲۵۵ گرد)،
`sale_price`/`cost_price` هر دو DECIMAL(18,2)، `cost_price` عمداً nullable
(بند ۵.۳ CLAUDE.md — عدم قطعیت نباید صفر فرض شود)، `currency_id` nullable FK
به `currencies` (خالی = تومان)، CHECK جدید `chk_products_fulfillment_type`
(`physical`/`digital`/`service`) با همان guard غیر-sqlite الگوی قبلی.

---

## ۱۴. استثنا: ENUM نیتیو MySQL به‌جای VARCHAR+CHECK (ماژول SiteBuilder)

طبق بخش ۳.۲ این سند، قرارداد استاندارد پروژه برای ستون‌های enum-like همیشه
`VARCHAR` سطح دیتابیس + enum PHP سطح اپ + CHECK constraint دستی (با
`DB::statement`، skip‌شده روی sqlite) است — **نه** نوع `ENUM` نیتیو MySQL.

**استثنا:** `page_categories.category_key` و `pages.page_status` (ماژول
SiteBuilder، Session ۱) با تصمیم صریح کارفرما از نوع `ENUM` نیتیو MySQL
ساخته شدند (`$table->enum(...)` در Laravel، نه `string`+`DB::statement`).
Laravel خودش این را به‌ازای هر درایور درست ترجمه می‌کند: روی MySQL واقعاً
`ENUM(...)` می‌سازد، روی SQLite (محیط تست پروژه) به `VARCHAR` + `CHECK`
تبدیل می‌کند — پس برخلاف بقیه CHECK های دستی پروژه، اینجا نیازی به گارد
`Schema::getConnection()->getDriverName() !== 'sqlite'` نیست؛ خودِ Laravel
هر دو محیط را پوشش می‌دهد.

**چرا اینجا فرق دارد:** تصمیم مستقیم کارفرما برای این دو ستون خاص، نه یک
تغییر قرارداد کلی پروژه. بقیه ستون‌های enum-like ماژول‌های دیگر (و حتی بقیه
ستون‌های همین ماژول در Session‌های بعدی) همچنان از الگوی VARCHAR+CHECK
استاندارد پیروی می‌کنند مگر خلاف آن صریحاً مستند شود.

**هزینه‌اش:** افزودن مقدار جدید به یک ENUM نیتیو MySQL در آینده (مثلاً یک
`category_key` یا `page_status` جدید) نیاز به `ALTER TABLE ... MODIFY COLUMN
... ENUM(...)` دارد — یک migration که کل تعریف ستون را بازنویسی می‌کند، نه
فقط `DROP CHECK`/`ADD CHECK` سبک الگوی استاندارد بخش ۳.۲. روی جدول‌های بزرگ
این `ALTER` می‌تواند از CHECK-swap سنگین‌تر باشد (بسته به نسخه/موتور
MySQL، گاهی rebuild کامل جدول). همچنین چون این ستون‌ها نوع native‌شان
مستقیم در schema دیده می‌شود، هر ابزار خارجی که مستقیم به دیتابیس وصل
می‌شود (نه از طریق Laravel) لیست مقادیر مجاز را از metadata ستون می‌بیند —
که هم مزیت (خوداسنادی) هم محدودیت (وابستگی بیشتر schema به لیست دقیق
مقادیر) دارد.

---

## ۱۵. استثنا: ENUM نیتیو MySQL برای شش ستون ماژول Process

طبق تصمیم صریح کارفرما (مثل استثنای بند ۱۴ برای SiteBuilder)، شش ستون
enum-like ماژول جدید `Process` (موتور گردش‌کار عمومی) هم از نوع `ENUM`
نیتیو MySQL هستند، نه VARCHAR+CHECK استاندارد بخش ۳.۲:

- `process_steps.step_type`
- `process_steps.assignment_type`
- `process_steps.condition_operator`
- `process_transitions.on_result`
- `process_instances.status`
- `process_instance_logs.action`

همان الگوی SiteBuilder: `$table->enum(...)` در Laravel، بدون گارد
`Schema::getConnection()->getDriverName() !== 'sqlite'` — چون Laravel خودش
روی mysql واقعی ENUM می‌سازد و روی sqlite (محیط تست) به VARCHAR+CHECK
ترجمه می‌کند.

`process_definitions.subject_type` و `process_definitions.process_key` این
استثنا را **ندارند** — این دو مقدار آزاد (نام کلاس ماژول / کلید فرایند)
هستند، نه از یک مجموعه بسته‌ی مقادیر، پس اصلاً ENUM/CHECK لیستی معنا ندارد.
